<?php
declare(strict_types=1);

function appStripeSecretKey(): string
{
    $secretKey = trim((string) (envValue('STRIPE_SECRET_KEY') ?? envValue('STRIPE_API_KEY') ?? ''));
    if ($secretKey === '') {
        throw new RuntimeException('A cobrança Stripe ainda não está configurada.');
    }

    return $secretKey;
}

function appStripeRequestForm(string $method, string $url, array $payload = [], ?string $secretKey = null): array
{
    $method = strtoupper(trim($method));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new RuntimeException('Método Stripe inválido.');
    }

    $secretKey = $secretKey !== null && trim($secretKey) !== '' ? trim($secretKey) : appStripeSecretKey();
    $encodedPayload = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $requestUrl = $url;
    $content = '';
    if ($method === 'GET' && $encodedPayload !== '') {
        $requestUrl .= (str_contains($requestUrl, '?') ? '&' : '?') . $encodedPayload;
    } elseif ($method === 'POST') {
        $content = $encodedPayload;
    }

    $headers = ['Authorization: Bearer ' . $secretKey];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $responseBody = '';
    $statusCode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($requestUrl);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com a Stripe.');
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POSTFIELDS] = $content;
        }
        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Falha de conexão com a Stripe: ' . $error);
        }
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($requestUrl, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Falha ao conectar com a API da Stripe.');
        }
        foreach (($http_response_header ?? []) as $headerLine) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', (string) $headerLine, $matches)) {
                $statusCode = (int) ($matches[1] ?? 0);
                break;
            }
        }
    }

    $decoded = json_decode((string) $responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('A Stripe retornou uma resposta inválida.');
    }
    if ($statusCode >= 400) {
        $message = trim((string) ($decoded['error']['message'] ?? 'Não foi possível processar a solicitação na Stripe.'));
        throw new RuntimeException($message !== '' ? $message : 'Não foi possível processar a solicitação na Stripe.');
    }

    return $decoded;
}

function appStripeConfiguredBillingId(string $planKey, string $billingInterval): string
{
    $planKey = normalizeBillingPlanKey($planKey);
    $billingInterval = normalizeBillingInterval($billingInterval);
    $prefix = 'STRIPE_' . strtoupper($planKey);
    $keys = $billingInterval === 'year'
        ? [$prefix . '_ANNUAL_PRICE_ID', $prefix . '_YEARLY_PRICE_ID']
        : [$prefix . '_MONTHLY_PRICE_ID', $prefix . '_PRICE_ID'];
    foreach ($keys as $key) {
        $value = trim((string) (envValue($key) ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string) (envValue('STRIPE_PRODUCT_ID') ?? ''));
}

function appStripePriceIdForPlan(array $plan, string $billingInterval): string
{
    $planKey = normalizeBillingPlanKey((string) ($plan['key'] ?? ''), null);
    if ($planKey === '') {
        throw new RuntimeException('Plano inválido.');
    }
    $billingInterval = normalizeBillingInterval($billingInterval);
    $billingId = appStripeConfiguredBillingId($planKey, $billingInterval);
    if (str_starts_with($billingId, 'price_')) {
        return $billingId;
    }
    if (!str_starts_with($billingId, 'prod_')) {
        throw new RuntimeException('O preço deste plano ainda não está configurado na Stripe.');
    }

    $prices = appStripeRequestForm('GET', 'https://api.stripe.com/v1/prices', [
        'product' => $billingId,
        'active' => 'true',
        'type' => 'recurring',
        'limit' => 100,
    ]);
    $expectedAmount = billingPlanChargeCents($plan, $billingInterval);
    foreach ((array) ($prices['data'] ?? []) as $price) {
        if (!is_array($price)) {
            continue;
        }
        $recurring = is_array($price['recurring'] ?? null) ? $price['recurring'] : [];
        if ((string) ($recurring['interval'] ?? '') !== $billingInterval) {
            continue;
        }
        $metadata = is_array($price['metadata'] ?? null) ? $price['metadata'] : [];
        $attributes = billingPlanAttributesFromStripeMetadata($metadata);
        $metadataPlan = (string) ($attributes['plan_key'] ?? '');
        $matchesPlan = $metadataPlan === $planKey;
        if (!$matchesPlan) {
            $matchesPlan = (int) ($price['unit_amount'] ?? -1) === $expectedAmount;
        }
        $priceId = trim((string) ($price['id'] ?? ''));
        if ($matchesPlan && str_starts_with($priceId, 'price_')) {
            return $priceId;
        }
    }

    throw new RuntimeException('Não foi encontrado um preço Stripe compatível com o plano selecionado.');
}

function appStripeSubscription(string $subscriptionId): array
{
    $subscriptionId = trim($subscriptionId);
    if (!str_starts_with($subscriptionId, 'sub_')) {
        throw new RuntimeException('Assinatura Stripe inválida.');
    }

    return appStripeRequestForm(
        'GET',
        'https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscriptionId),
        ['expand[]' => 'schedule']
    );
}

function appStripeSubscriptionPrimaryItem(array $subscription): array
{
    $items = is_array($subscription['items']['data'] ?? null) ? $subscription['items']['data'] : [];
    $item = $items[0] ?? null;
    if (!is_array($item) || trim((string) ($item['id'] ?? '')) === '') {
        throw new RuntimeException('Não foi possível identificar o item da assinatura.');
    }

    return $item;
}

function appStripeSubscriptionRecordAttributes(array $subscription, array $fallbackPlan, string $fallbackInterval): array
{
    $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
    $attributes = billingPlanAttributesFromStripeMetadata($metadata);
    $planKey = normalizeBillingPlanKey((string) ($attributes['plan_key'] ?? ($fallbackPlan['key'] ?? '')), null);
    $interval = normalizeBillingInterval((string) ($attributes['billing_interval'] ?? $fallbackInterval), null);
    $plan = $planKey !== '' ? billingPlan($planKey) : $fallbackPlan;

    return [
        'stripe_customer_id' => trim((string) ($subscription['customer'] ?? '')),
        'stripe_subscription_id' => trim((string) ($subscription['id'] ?? '')),
        'plan_key' => $planKey,
        'billing_interval' => $interval,
        'max_users' => max(0, (int) ($plan['max_users'] ?? 0)),
        'subscription_status' => trim((string) ($subscription['status'] ?? 'active')) ?: 'active',
        'checkout_status' => 'completed',
        'trial_end' => stripeTimestampToIso($subscription['trial_end'] ?? null),
        'current_period_end' => stripeTimestampToIso($subscription['current_period_end'] ?? null),
        'cancel_at' => stripeTimestampToIso($subscription['cancel_at'] ?? null),
        'raw_payload_json' => json_encode($subscription, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function appStripeUpdateSubscriptionPlan(array $storedSubscription, array $targetPlan, string $billingInterval): array
{
    $subscriptionId = trim((string) ($storedSubscription['stripe_subscription_id'] ?? ''));
    $subscription = appStripeSubscription($subscriptionId);
    $item = appStripeSubscriptionPrimaryItem($subscription);
    $priceId = appStripePriceIdForPlan($targetPlan, $billingInterval);
    $metadata = billingPlanMetadata($targetPlan, $billingInterval);

    return appStripeRequestForm('POST', 'https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscriptionId), [
        'items' => [[
            'id' => (string) ($item['id'] ?? ''),
            'price' => $priceId,
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
        ]],
        'proration_behavior' => 'create_prorations',
        'payment_behavior' => 'error_if_incomplete',
        'metadata' => array_merge($metadata, [
            'bexon_user_id' => (string) ($storedSubscription['user_id'] ?? ''),
        ]),
    ]);
}

function appStripeScheduleSubscriptionPlan(array $storedSubscription, array $targetPlan, string $billingInterval): array
{
    $subscriptionId = trim((string) ($storedSubscription['stripe_subscription_id'] ?? ''));
    $subscription = appStripeSubscription($subscriptionId);
    $item = appStripeSubscriptionPrimaryItem($subscription);
    $currentPrice = is_array($item['price'] ?? null) ? $item['price'] : [];
    $currentPriceId = trim((string) ($currentPrice['id'] ?? ''));
    $targetPriceId = appStripePriceIdForPlan($targetPlan, $billingInterval);
    $periodStart = (int) ($subscription['current_period_start'] ?? 0);
    $periodEnd = (int) ($subscription['current_period_end'] ?? 0);
    if ($currentPriceId === '' || $periodStart <= 0 || $periodEnd <= $periodStart) {
        throw new RuntimeException('Não foi possível determinar o período atual da assinatura.');
    }

    $schedule = $subscription['schedule'] ?? null;
    $scheduleId = is_array($schedule) ? trim((string) ($schedule['id'] ?? '')) : trim((string) $schedule);
    if ($scheduleId === '') {
        $schedule = appStripeRequestForm('POST', 'https://api.stripe.com/v1/subscription_schedules', [
            'from_subscription' => $subscriptionId,
        ]);
        $scheduleId = trim((string) ($schedule['id'] ?? ''));
    }
    if (!str_starts_with($scheduleId, 'sub_sched_')) {
        throw new RuntimeException('Não foi possível agendar a mudança do plano.');
    }

    $metadata = billingPlanMetadata($targetPlan, $billingInterval);
    return appStripeRequestForm('POST', 'https://api.stripe.com/v1/subscription_schedules/' . rawurlencode($scheduleId), [
        'end_behavior' => 'release',
        'phases' => [
            [
                'start_date' => $periodStart,
                'end_date' => $periodEnd,
                'items' => [[
                    'price' => $currentPriceId,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ]],
            ],
            [
                'start_date' => $periodEnd,
                'items' => [[
                    'price' => $targetPriceId,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ]],
                'metadata' => array_merge($metadata, [
                    'bexon_user_id' => (string) ($storedSubscription['user_id'] ?? ''),
                ]),
            ],
        ],
    ]);
}

function appStripeCreatePortalSession(string $customerId, string $returnUrl): string
{
    $customerId = trim($customerId);
    if (!str_starts_with($customerId, 'cus_')) {
        throw new RuntimeException('Esta assinatura não possui uma conta de cobrança Stripe vinculada.');
    }
    $payload = [
        'customer' => $customerId,
        'return_url' => $returnUrl,
    ];
    $configurationId = trim((string) (envValue('STRIPE_BILLING_PORTAL_CONFIGURATION_ID') ?? ''));
    if ($configurationId !== '') {
        $payload['configuration'] = $configurationId;
    }
    $session = appStripeRequestForm('POST', 'https://api.stripe.com/v1/billing_portal/sessions', $payload);
    $url = trim((string) ($session['url'] ?? ''));
    if (!str_starts_with($url, 'https://')) {
        throw new RuntimeException('A Stripe não retornou o endereço do portal de cobrança.');
    }

    return $url;
}

function appStripeCancelScheduledPlanChange(array $storedSubscription): void
{
    $subscriptionId = trim((string) ($storedSubscription['stripe_subscription_id'] ?? ''));
    $subscription = appStripeSubscription($subscriptionId);
    $schedule = $subscription['schedule'] ?? null;
    $scheduleId = is_array($schedule) ? trim((string) ($schedule['id'] ?? '')) : trim((string) $schedule);
    if (!str_starts_with($scheduleId, 'sub_sched_')) {
        throw new RuntimeException('Não existe uma mudança de plano agendada na Stripe.');
    }

    appStripeRequestForm(
        'POST',
        'https://api.stripe.com/v1/subscription_schedules/' . rawurlencode($scheduleId) . '/release'
    );
}

function billingPlanRank(string $planKey): int
{
    return ['free' => 0, 'solo' => 1, 'team' => 2, 'business' => 3, 'enterprise' => 4][normalizeBillingPlanKey($planKey, null)] ?? 0;
}
