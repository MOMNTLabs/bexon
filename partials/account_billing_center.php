<?php
$billingStatusLabels = [
    'active' => 'Ativo',
    'trialing' => 'Período de teste',
    'past_due' => 'Pagamento pendente',
    'unpaid' => 'Pagamento pendente',
    'canceled' => 'Cancelado',
    'inactive' => 'Sem assinatura',
];
$billingStatusLabel = $accountPlanKey === 'free'
    ? 'Plano pessoal'
    : ($billingStatusLabels[$accountSubscriptionStatus] ?? ucfirst($accountSubscriptionStatus ?: 'Ativo'));
$billingStatusTone = in_array($accountSubscriptionStatus, ['past_due', 'unpaid', 'canceled'], true) ? 'warning' : 'success';
$billingPeriodEndLabel = $formatBillingDate($accountSubscription['current_period_end'] ?? null);
$billingTrialEndLabel = $formatBillingDate($accountSubscription['trial_end'] ?? null);
$billingCancelAtLabel = $formatBillingDate($accountSubscription['cancel_at'] ?? null);
$billingPendingAtLabel = $formatBillingDate($accountSubscription['pending_change_at'] ?? null);
$billingMaxUsers = max(0, (int) ($accountSeatUsage['max_users'] ?? ($accountPlan['max_users'] ?? 0)));
$billingUsedUsers = max(1, (int) ($accountSeatUsage['used_users'] ?? 1));
$billingRemainingInvites = $accountSeatUsage['remaining_invites'] ?? 0;
$billingUnlimited = !empty($accountSeatUsage['is_unlimited']) || $accountPlanKey === 'enterprise';
$billingUsagePercent = $billingUnlimited || $billingMaxUsers <= 0
    ? 0
    : min(100, (int) round(($billingUsedUsers / $billingMaxUsers) * 100));
$billingDefaultComparisonInterval = $accountBillingInterval !== '' ? $accountBillingInterval : billingDefaultInterval();
?>

<section class="account-billing-center account-settings-panel-item account-settings-panel-plan" data-account-settings-panel="plan" hidden>
    <div class="account-billing-summary">
        <div class="account-billing-summary-main">
            <div class="account-billing-plan-title">
                <span class="account-billing-kicker">Seu plano</span>
                <div>
                    <h3><?= e((string) ($accountPlan['name'] ?? 'Plano pessoal')) ?></h3>
                    <span class="account-billing-status is-<?= e($billingStatusTone) ?>"><?= e($billingStatusLabel) ?></span>
                </div>
            </div>

            <div class="account-billing-meta">
                <?php if ($accountBillingInterval !== ''): ?>
                    <div>
                        <span>Cobrança</span>
                        <strong><?= $accountBillingInterval === 'year' ? 'Anual' : 'Mensal' ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($billingTrialEndLabel !== '' && $accountSubscriptionStatus === 'trialing'): ?>
                    <div>
                        <span>Teste até</span>
                        <strong><?= e($billingTrialEndLabel) ?></strong>
                    </div>
                <?php elseif ($billingCancelAtLabel !== ''): ?>
                    <div>
                        <span>Acesso até</span>
                        <strong><?= e($billingCancelAtLabel) ?></strong>
                    </div>
                <?php elseif ($billingPeriodEndLabel !== '' && $accountPlanKey !== 'free'): ?>
                    <div>
                        <span>Próxima renovação</span>
                        <strong><?= e($billingPeriodEndLabel) ?></strong>
                    </div>
                <?php endif; ?>
                <div>
                    <span>Vagas</span>
                    <strong><?= $billingUnlimited ? 'Sem limite definido' : e($billingUsedUsers . ' de ' . max(1, $billingMaxUsers)) ?></strong>
                </div>
            </div>

            <?php if (!$billingUnlimited && $billingMaxUsers > 1): ?>
                <div class="account-billing-seat-usage" aria-label="<?= e($billingUsedUsers . ' de ' . $billingMaxUsers . ' vagas utilizadas') ?>">
                    <div class="account-billing-seat-track"><span style="width: <?= e((string) $billingUsagePercent) ?>%"></span></div>
                    <p>
                        <span><?= e((string) ($accountSeatUsage['active_invitees'] ?? 0)) ?> convidados ativos</span>
                        <?php if ((int) ($accountSeatUsage['pending_invitees'] ?? 0) > 0): ?>
                            <span><?= e((string) $accountSeatUsage['pending_invitees']) ?> pendentes</span>
                        <?php endif; ?>
                        <strong><?= e((string) $billingRemainingInvites) ?> convites disponíveis</strong>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="account-billing-summary-actions">
            <?php if ($accountHasStripeCustomer && !$accountIsEnterpriseOverride): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="account_open_billing_portal">
                    <button type="submit" class="btn btn-mini">Gerenciar cobrança</button>
                </form>
                <small>Cartão, faturas e cancelamento</small>
            <?php elseif ($accountIsEnterpriseOverride || $accountPlanKey === 'enterprise'): ?>
                <a href="<?= e(appBillingPlanMailtoUrl($accountPlan)) ?>" class="btn btn-mini">Falar com suporte</a>
                <small>Plano gerenciado com a equipe Bexon</small>
            <?php else: ?>
                <a href="#comparar-planos" class="btn btn-mini">Escolher plano</a>
                <small>Ative uma assinatura para criar workspaces</small>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_array($accountPendingPlan)): ?>
        <div class="account-billing-pending" role="status">
            <div>
                <strong>Mudança agendada</strong>
                <span>
                    <?= e((string) ($accountPendingPlan['name'] ?? 'Novo plano')) ?>
                    · <?= $accountPendingInterval === 'month' ? 'mensal' : 'anual' ?>
                    <?= $billingPendingAtLabel !== '' ? 'em ' . e($billingPendingAtLabel) : 'no fim do período atual' ?>
                </span>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="account_cancel_plan_change">
                <button type="submit" class="btn btn-mini btn-ghost">Manter plano atual</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="account-billing-rule-card">
        <div class="account-billing-rule-icon" aria-hidden="true">◎</div>
        <div>
            <strong>Uma assinatura, pessoas compartilhadas entre seus workspaces</strong>
            <p>
                Você ocupa uma vaga. Cada pessoa convidada ocupa apenas mais uma, mesmo participando de vários workspaces criados por você.
                Os convidados mantêm o workspace pessoal deles e acessam somente os workspaces para os quais foram convidados.
            </p>
        </div>
    </div>

    <?php if ($accountSponsoredWorkspaces): ?>
        <details class="account-billing-sponsored">
            <summary>Acessos recebidos por convite <span><?= count($accountSponsoredWorkspaces) ?></span></summary>
            <div>
                <?php foreach ($accountSponsoredWorkspaces as $membership): ?>
                    <article>
                        <?= renderWorkspaceAvatar($membership, 'avatar small') ?>
                        <div>
                            <strong><?= e((string) ($membership['name'] ?? 'Workspace')) ?></strong>
                            <span>Coberto pelo plano <?= e((string) ($membership['sponsor_plan_name'] ?? 'do proprietário')) ?> de <?= e((string) ($membership['owner_name'] ?? 'outro usuário')) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <section class="account-billing-compare" id="comparar-planos" data-account-plan-compare data-default-billing-interval="<?= e($billingDefaultComparisonInterval) ?>">
        <div class="account-billing-compare-head">
            <div>
                <span class="account-billing-kicker">Comparar</span>
                <h3>Encontre a capacidade certa para sua equipe</h3>
                <p>Todos os planos incluem as ferramentas do Bexon. O que muda é a quantidade de pessoas e o nível de atendimento.</p>
            </div>
            <div class="account-billing-toggle" role="group" aria-label="Período de cobrança">
                <button type="button" data-account-billing-interval="year" aria-pressed="<?= $billingDefaultComparisonInterval === 'year' ? 'true' : 'false' ?>" class="<?= $billingDefaultComparisonInterval === 'year' ? 'is-active' : '' ?>">
                    Anual <span>2 meses de economia</span>
                </button>
                <button type="button" data-account-billing-interval="month" aria-pressed="<?= $billingDefaultComparisonInterval === 'month' ? 'true' : 'false' ?>" class="<?= $billingDefaultComparisonInterval === 'month' ? 'is-active' : '' ?>">Mensal</button>
            </div>
        </div>

        <div class="account-billing-plan-grid">
            <?php foreach ($accountBillingPlans as $billingPlan): ?>
                <?php
                $billingPlanKey = (string) ($billingPlan['key'] ?? '');
                $isCurrentPlan = $billingPlanKey === $accountPlanKey;
                $isEnterprisePlan = ($billingPlan['checkout_enabled'] ?? true) === false;
                $monthlyLabel = appBillingPlanPriceLabel($billingPlan, 'month');
                $annualLabel = appBillingPlanPriceLabel($billingPlan, 'year');
                $monthlyNote = appBillingPlanBillingNote($billingPlan, 'month');
                $annualNote = appBillingPlanBillingNote($billingPlan, 'year');
                $initialLabel = $billingDefaultComparisonInterval === 'month' ? $monthlyLabel : $annualLabel;
                $initialNote = $billingDefaultComparisonInterval === 'month' ? $monthlyNote : $annualNote;
                ?>
                <article
                    class="account-billing-plan-card<?= $isCurrentPlan ? ' is-current' : '' ?>"
                    data-account-plan-card
                    data-account-plan-key="<?= e($billingPlanKey) ?>"
                    data-price-month="<?= e($monthlyLabel) ?>"
                    data-price-year="<?= e($annualLabel) ?>"
                    data-note-month="<?= e($monthlyNote) ?>"
                    data-note-year="<?= e($annualNote) ?>"
                >
                    <div class="account-billing-plan-card-head">
                        <div>
                            <h4><?= e((string) ($billingPlan['name'] ?? 'Plano')) ?></h4>
                            <span><?= e(appBillingPlanUsersLabel($billingPlan)) ?></span>
                        </div>
                        <?php if ($isCurrentPlan): ?><em>Atual</em><?php endif; ?>
                    </div>
                    <p class="account-billing-plan-price" data-account-plan-price><?= e($initialLabel) ?><?= $isEnterprisePlan ? '' : '/mês' ?></p>
                    <small data-account-plan-note><?= e($initialNote) ?></small>
                    <p><?= e((string) ($billingPlan['summary'] ?? '')) ?></p>
                    <ul>
                        <?php foreach ((array) ($billingPlan['features'] ?? []) as $feature): ?>
                            <li><?= e((string) $feature) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($isEnterprisePlan || $accountIsEnterpriseOverride): ?>
                        <a href="<?= e(appBillingPlanMailtoUrl($billingPlan)) ?>" class="btn btn-mini btn-block">Falar com suporte</a>
                    <?php else: ?>
                        <form method="post" data-account-plan-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_change_plan">
                            <input type="hidden" name="plan_key" value="<?= e($billingPlanKey) ?>">
                            <input type="hidden" name="billing_interval" value="<?= e($billingDefaultComparisonInterval) ?>" data-account-plan-interval-input>
                            <button
                                type="submit"
                                class="btn btn-mini btn-block"
                                data-account-plan-submit
                                <?= $isCurrentPlan && $accountBillingInterval === $billingDefaultComparisonInterval ? 'disabled' : '' ?>
                            >
                                <?php if ($isCurrentPlan && $accountBillingInterval === $billingDefaultComparisonInterval): ?>
                                    Plano atual
                                <?php elseif (billingPlanRank($billingPlanKey) > billingPlanRank($accountPlanKey)): ?>
                                    Fazer upgrade
                                <?php elseif ($isCurrentPlan): ?>
                                    Trocar cobrança
                                <?php else: ?>
                                    Mudar de plano
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="account-billing-change-note">
            Upgrades entram em vigor imediatamente com ajuste proporcional. Downgrades e a mudança do anual para o mensal começam na próxima renovação.
        </p>
    </section>
</section>
