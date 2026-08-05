<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (configuredAppUrl() !== '' && !requestTargetsConfiguredAppHost()) {
    header('Location: ' . appUrl('account-settings'));
    exit;
}

$pdo = db();
$currentUser = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'logout':
                logoutUser();
                flash('success', 'Sessão encerrada.');
                redirectTo('');

            case 'account_update_profile':
                updateUserProfile(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['name'] ?? ''),
                    $_FILES['avatar'] ?? []
                );
                flash('success', 'Perfil atualizado.');
                redirectTo('account-settings');

            case 'account_update_password':
                updateUserPassword(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['new_password_confirm'] ?? '')
                );
                flash('success', 'Senha atualizada.');
                redirectTo('account-settings#security');

            case 'account_open_billing_portal':
                $subscription = userSubscriptionByUserId((int) $currentUser['id']);
                $subscriptionId = trim((string) ($subscription['stripe_subscription_id'] ?? ''));
                if (!$subscription || !str_starts_with($subscriptionId, 'sub_')) {
                    throw new RuntimeException('O plano Enterprise é gerenciado diretamente com o suporte Bexon.');
                }
                $portalUrl = appStripeCreatePortalSession(
                    (string) ($subscription['stripe_customer_id'] ?? ''),
                    appUrl('account-settings#plan')
                );
                header('Location: ' . $portalUrl);
                exit;

            case 'account_change_plan':
                $targetPlanKey = normalizeBillingPlanKey((string) ($_POST['plan_key'] ?? ''), null);
                $targetInterval = normalizeBillingInterval((string) ($_POST['billing_interval'] ?? ''), null);
                $targetPlan = $targetPlanKey !== '' ? billingPlan($targetPlanKey) : null;
                if (!$targetPlan || $targetInterval === '' || ($targetPlan['checkout_enabled'] ?? true) === false) {
                    throw new RuntimeException('Escolha um plano disponível.');
                }

                $subscription = userSubscriptionByUserId((int) $currentUser['id']);
                $subscriptionId = trim((string) ($subscription['stripe_subscription_id'] ?? ''));
                if (!$subscription || !str_starts_with($subscriptionId, 'sub_')) {
                    header('Location: ' . siteUrl(
                        'home?action=checkout&plan=' . rawurlencode($targetPlanKey)
                        . '&interval=' . rawurlencode($targetInterval)
                    ));
                    exit;
                }

                $currentPlanKey = billingSubscriptionPlanKey($subscription);
                $currentInterval = normalizeBillingInterval((string) ($subscription['billing_interval'] ?? 'year'));
                if ($currentPlanKey === $targetPlanKey && $currentInterval === $targetInterval) {
                    throw new RuntimeException('Este já é o seu plano atual.');
                }

                $seatUsage = billingSponsorSeatUsage((int) $currentUser['id']);
                $targetMaxUsers = max(0, (int) ($targetPlan['max_users'] ?? 0));
                if ($targetMaxUsers > 0 && (int) ($seatUsage['used_users'] ?? 1) > $targetMaxUsers) {
                    throw new RuntimeException(sprintf(
                        'Para mudar para %s, reduza o uso para no máximo %d pessoa%s. Hoje há %d vagas ocupadas ou reservadas.',
                        (string) ($targetPlan['name'] ?? 'este plano'),
                        $targetMaxUsers,
                        $targetMaxUsers === 1 ? '' : 's',
                        (int) ($seatUsage['used_users'] ?? 1)
                    ));
                }

                $isDowngrade = billingPlanRank($targetPlanKey) < billingPlanRank($currentPlanKey)
                    || ($targetPlanKey === $currentPlanKey && $currentInterval === 'year' && $targetInterval === 'month');
                if ($isDowngrade) {
                    appStripeScheduleSubscriptionPlan($subscription, $targetPlan, $targetInterval);
                    upsertUserSubscription($pdo, (int) $currentUser['id'], [
                        'pending_plan_key' => $targetPlanKey,
                        'pending_billing_interval' => $targetInterval,
                        'pending_change_at' => $subscription['current_period_end'] ?? null,
                    ]);
                    flash('success', 'Mudança agendada para o fim do período atual. Até lá, seu plano continua igual.');
                } else {
                    $stripeSubscription = appStripeUpdateSubscriptionPlan($subscription, $targetPlan, $targetInterval);
                    upsertUserSubscription($pdo, (int) $currentUser['id'], array_merge(
                        appStripeSubscriptionRecordAttributes($stripeSubscription, $targetPlan, $targetInterval),
                        [
                            'pending_plan_key' => '',
                            'pending_billing_interval' => '',
                            'pending_change_at' => null,
                        ]
                    ));
                    flash('success', 'Plano atualizado. A Stripe aplicará o ajuste proporcional desta cobrança.');
                }
                redirectTo('account-settings#plan');

            case 'account_cancel_plan_change':
                $subscription = userSubscriptionByUserId((int) $currentUser['id']);
                if (!$subscription || trim((string) ($subscription['pending_plan_key'] ?? '')) === '') {
                    throw new RuntimeException('Não existe uma mudança de plano agendada.');
                }
                appStripeCancelScheduledPlanChange($subscription);
                upsertUserSubscription($pdo, (int) $currentUser['id'], [
                    'pending_plan_key' => '',
                    'pending_billing_interval' => '',
                    'pending_change_at' => null,
                ]);
                flash('success', 'Mudança agendada cancelada. Seu plano atual será mantido.');
                redirectTo('account-settings#plan');

            case 'account_delete_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não pode ser removido.');
                }
                deleteWorkspaceOwnedByUser($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Workspace removido.');
                redirectTo('account-settings');

            case 'account_leave_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite sair.');
                }
                leaveWorkspace($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Você saiu do workspace.');
                redirectTo('account-settings');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        $planActions = ['account_open_billing_portal', 'account_change_plan', 'account_cancel_plan_change'];
        redirectTo($action === 'account_update_password'
            ? 'account-settings#security'
            : (in_array($action, $planActions, true) ? 'account-settings#plan' : 'account-settings'));
    }
}

$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
$currentWorkspace = $currentWorkspaceId !== null ? workspaceById((int) $currentWorkspaceId) : null;
$isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
$workspaceMemberships = workspaceMembershipsDetailedForUser((int) $currentUser['id']);
$accountSubscription = userSubscriptionByUserId((int) $currentUser['id']);
$accountPlanKey = is_array($accountSubscription) ? billingSubscriptionPlanKey($accountSubscription) : 'free';
$accountPlanKey = $accountPlanKey !== '' ? $accountPlanKey : 'free';
$accountPlan = billingPlan($accountPlanKey) ?? billingPlan('free') ?? ['key' => 'free', 'name' => 'Free', 'max_users' => 1];
$accountBillingInterval = is_array($accountSubscription)
    ? normalizeBillingInterval((string) ($accountSubscription['billing_interval'] ?? 'year'))
    : '';
$accountSeatUsage = billingSponsorSeatUsage((int) $currentUser['id']);
$accountBillingPlans = publicBillingPlanDefinitions();
$accountSubscriptionStatus = strtolower(trim((string) ($accountSubscription['subscription_status'] ?? 'inactive')));
$accountHasStripeSubscription = str_starts_with(trim((string) ($accountSubscription['stripe_subscription_id'] ?? '')), 'sub_');
$accountHasStripeCustomer = str_starts_with(trim((string) ($accountSubscription['stripe_customer_id'] ?? '')), 'cus_');
$accountIsEnterpriseOverride = userHasEnterpriseBillingOverride((int) $currentUser['id'])
    && !$accountHasStripeSubscription;
$accountPendingPlanKey = normalizeBillingPlanKey((string) ($accountSubscription['pending_plan_key'] ?? ''), null);
$accountPendingPlan = $accountPendingPlanKey !== '' ? billingPlan($accountPendingPlanKey) : null;
$accountPendingInterval = normalizeBillingInterval((string) ($accountSubscription['pending_billing_interval'] ?? ''), null);
$accountSponsoredWorkspaces = [];
foreach ($workspaceMemberships as $membership) {
    if (!empty($membership['is_personal']) || !empty($membership['is_owner'])) {
        continue;
    }
    $membershipLimit = workspaceBillingLimit((int) ($membership['id'] ?? 0));
    $membership['sponsor_plan_name'] = (string) ($membershipLimit['plan_name'] ?? '');
    $accountSponsoredWorkspaces[] = $membership;
}
$formatBillingDate = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable $e) {
        return '';
    }
};
$flashes = getFlashes();
$stylesAssetVersion = is_file(__DIR__ . '/assets/styles.css')
    ? (string) filemtime(__DIR__ . '/assets/styles.css')
    : '103';
$themeBexonAssetVersion = is_file(__DIR__ . '/assets/theme-bexon.css')
    ? (string) filemtime(__DIR__ . '/assets/theme-bexon.css')
    : '1';
$complianceAssetVersion = assetVersion('assets/compliance.js');
$pwaAssetVersion = assetVersion('assets/pwa.js');
$manifestAssetVersion = assetVersion('manifest.webmanifest');
$profileIconAssetVersion = assetVersion('assets/Bexon---Perfil.png');
$pwaIcon180AssetVersion = assetVersion('assets/pwa-icon-180.png');
$pwaIcon192AssetVersion = assetVersion('assets/pwa-icon-192.png');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="application-name" content="<?= e(APP_NAME) ?>">
    <meta name="theme-color" content="#040714">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
    <title><?= e(APP_NAME) ?> - Configurações da Conta</title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Perfil.png?v=' . $profileIconAssetVersion)) ?>">
    <link rel="icon" sizes="192x192" href="<?= e(appPath('assets/pwa-icon-192.png?v=' . $pwaIcon192AssetVersion)) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Perfil.png?v=' . $profileIconAssetVersion)) ?>">
    <link rel="apple-touch-icon" href="<?= e(appPath('assets/pwa-icon-180.png?v=' . $pwaIcon180AssetVersion)) ?>">
    <link rel="manifest" href="<?= e(appPath('manifest.webmanifest?v=' . $manifestAssetVersion)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <script src="<?= e(appPath('assets/compliance.js?v=' . $complianceAssetVersion)) ?>" defer></script>
    <script src="<?= e(appPath('assets/pwa.js?v=' . $pwaAssetVersion)) ?>" defer></script>
</head>
<body class="is-dashboard is-workspace-settings is-account-settings">
    <div class="bg-layer bg-layer-one" aria-hidden="true"></div>
    <div class="bg-layer bg-layer-two" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <div class="app-shell account-settings-shell">
        <?php if ($flashes): ?>
            <div class="flash-stack" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" data-flash>
                        <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                        <button type="button" class="flash-close" data-flash-close aria-label="Fechar">&#10005;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <aside class="account-settings-sidebar" aria-label="Navegação das configurações">
            <a href="<?= e(appPath()) ?>" class="account-settings-sidebar-brand" aria-label="<?= e(APP_NAME) ?>">
                <img
                    src="<?= e(appPath('assets/Bexon - Logo Horizontal Negativa.png?v=1')) ?>"
                    alt="<?= e(APP_NAME) ?>"
                    width="116"
                    height="29"
                >
            </a>

            <div class="account-settings-sidebar-context">
                <?php if (is_array($currentWorkspace)): ?>
                    <?= renderWorkspaceAvatar($currentWorkspace, 'avatar account-settings-workspace-avatar') ?>
                <?php else: ?>
                    <?= renderUserAvatar($currentUser, 'avatar account-settings-workspace-avatar') ?>
                <?php endif; ?>
                <div>
                    <strong><?= e((string) ($currentWorkspace['name'] ?? $currentUser['name'] ?? 'Conta')) ?></strong>
                    <span>Configurações pessoais</span>
                </div>
            </div>

            <nav class="account-settings-tabs account-settings-tabs-desktop" role="tablist" aria-label="Seções da conta">
                <button type="button" class="account-settings-tab is-active" role="tab" aria-selected="true" data-account-settings-tab="profile">
                    <span class="account-settings-tab-icon" aria-hidden="true">&#9675;</span>
                    Perfil
                </button>
                <button type="button" class="account-settings-tab" role="tab" aria-selected="false" data-account-settings-tab="security">
                    <span class="account-settings-tab-icon" aria-hidden="true">&#9671;</span>
                    Segurança
                </button>
                <button type="button" class="account-settings-tab" role="tab" aria-selected="false" data-account-settings-tab="plan">
                    <span class="account-settings-tab-icon" aria-hidden="true">&#9670;</span>
                    Plano
                </button>
            </nav>

            <a href="<?= e(appPath('#tasks')) ?>" class="account-settings-sidebar-back">
                <span aria-hidden="true">&#8592;</span>
                Voltar ao workspace
            </a>

            <div class="account-settings-sidebar-user">
                <?= renderUserAvatar($currentUser, 'avatar small') ?>
                <div>
                    <strong><?= e((string) $currentUser['name']) ?></strong>
                    <span><?= e((string) $currentUser['email']) ?></span>
                </div>
            </div>
        </aside>

        <header class="top-nav dashboard-nav">
            <div class="top-nav-leading">
                <a href="<?= e(appPath('#tasks')) ?>" class="btn btn-mini btn-ghost nav-back-button" aria-label="Voltar para dashboard">
                    <span aria-hidden="true">&#8592;</span>
                    <span>Voltar</span>
                </a>
                <a href="<?= e(appPath()) ?>" class="brand" aria-label="<?= e(APP_NAME) ?>">
                    <img
                        src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>"
                        alt="<?= e(APP_NAME) ?>"
                        class="brand-lockup"
                        width="116"
                        height="29"
                    >
                </a>
            </div>

            <div class="user-chip">
                <?= renderUserAvatar($currentUser) ?>
                <div>
                    <strong><?= e((string) $currentUser['name']) ?></strong>
                    <span><?= e((string) $currentUser['email']) ?></span>
                </div>
            </div>
            <div class="top-nav-actions">
                <a
                    href="<?= e(appPath('account-settings')) ?>"
                    class="icon-gear-button top-account-settings-button"
                    aria-label="Configurações da conta"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                        <circle cx="12" cy="12" r="3.2"></circle>
                    </svg>
                </a>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-pill btn-logout"><span>Sair</span></button>
                </form>
            </div>
        </header>

        <main class="workspace-settings-page">
            <section class="panel workspace-settings-panel">
                <div class="panel-header workspace-settings-header">
                    <h2>Configurações da conta</h2>
                    <p>Gerencie seus dados, segurança e assinatura em um só lugar.</p>
                </div>

                <nav class="account-settings-tabs account-settings-tabs-mobile" role="tablist" aria-label="Seções da conta">
                    <button type="button" class="account-settings-tab is-active" role="tab" aria-selected="true" data-account-settings-tab="profile">Perfil</button>
                    <button type="button" class="account-settings-tab" role="tab" aria-selected="false" data-account-settings-tab="security">Segurança</button>
                    <button type="button" class="account-settings-tab" role="tab" aria-selected="false" data-account-settings-tab="plan">Plano</button>
                </nav>

                <div class="workspace-settings-grid account-settings-grid">
                    <section class="workspace-settings-card account-profile-card account-settings-panel-item" data-account-settings-panel="profile">
                        <h3>Perfil</h3>
                        <form method="post" class="workspace-settings-form account-profile-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_update_profile">
                            <div class="account-profile-photo-row">
                                <div class="account-profile-avatar-preview" data-account-avatar-preview>
                                    <?= renderUserAvatar($currentUser, 'avatar account-profile-avatar') ?>
                                </div>
                                <div class="account-profile-photo-field">
                                    <span class="account-profile-photo-label">Foto de perfil</span>
                                    <label class="account-profile-upload">
                                    <input
                                        class="account-profile-file-input"
                                        type="file"
                                        name="avatar"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        data-account-avatar-input
                                    >
                                        <span class="account-profile-upload-button">Trocar foto</span>
                                        <span class="account-profile-upload-name" data-account-avatar-name>Nenhum arquivo selecionado</span>
                                    </label>
                                    <small>PNG, JPG, WebP ou GIF.</small>
                                </div>
                            </div>
                            <label>
                                <span>Nome</span>
                                <input
                                    type="text"
                                    name="name"
                                    maxlength="80"
                                    value="<?= e((string) $currentUser['name']) ?>"
                                    required
                                >
                            </label>
                            <button type="submit" class="btn btn-mini">Salvar perfil</button>
                        </form>
                    </section>

                    <?php include __DIR__ . '/partials/account_billing_center.php'; ?>

                    <section class="workspace-settings-card account-password-card account-settings-panel-item" data-account-settings-panel="security" hidden>
                        <h3>Senha</h3>
                        <form method="post" class="workspace-settings-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_update_password">
                            <label>
                                <span>Senha atual</span>
                                <span class="account-password-control">
                                    <input type="password" name="current_password" autocomplete="current-password" required>
                                    <button type="button" class="account-password-toggle" data-account-password-toggle aria-label="Mostrar senha" aria-pressed="false">Ver</button>
                                </span>
                            </label>
                            <label>
                                <span>Nova senha</span>
                                <span class="account-password-control">
                                    <input type="password" name="new_password" autocomplete="new-password" required>
                                    <button type="button" class="account-password-toggle" data-account-password-toggle aria-label="Mostrar senha" aria-pressed="false">Ver</button>
                                </span>
                            </label>
                            <label>
                                <span>Confirmar nova senha</span>
                                <span class="account-password-control">
                                    <input type="password" name="new_password_confirm" autocomplete="new-password" required>
                                    <button type="button" class="account-password-toggle" data-account-password-toggle aria-label="Mostrar senha" aria-pressed="false">Ver</button>
                                </span>
                            </label>
                            <button type="submit" class="btn btn-mini">Atualizar senha</button>
                        </form>
                    </section>

                    <section class="workspace-settings-card account-install-card account-settings-panel-item" data-account-settings-panel="profile" data-pwa-install-entry="settings" hidden>
                        <h3>Aplicativo no celular</h3>
                        <p class="account-install-copy" data-pwa-install-message>
                            Adicione o Bexon como aplicativo para abrir mais rapido e usar em tela cheia.
                        </p>
                        <div class="account-install-actions">
                            <button type="button" class="btn btn-mini" data-pwa-install-trigger>Instalar app</button>
                        </div>
                    </section>

                    <section class="workspace-settings-card account-privacy-card account-settings-panel-item" data-account-settings-panel="profile">
                        <h3>Privacidade e dados</h3>
                        <p class="workspace-settings-member-empty">
                            Consulte suas opcoes de privacidade, direitos LGPD, termos e politica de cookies.
                        </p>
                        <div class="account-privacy-links">
                            <a href="<?= e(siteUrl('dados')) ?>" class="btn btn-mini">Meus dados</a>
                            <a href="<?= e(siteUrl('privacidade')) ?>" class="btn btn-mini btn-ghost">Privacidade</a>
                            <a href="<?= e(siteUrl('termos')) ?>" class="btn btn-mini btn-ghost">Termos</a>
                        </div>
                    </section>
                </div>

                <section class="workspace-settings-card account-workspaces-card account-settings-panel-item" data-account-settings-panel="profile">
                    <h3>Workspaces</h3>
                    <ul class="workspace-settings-members">
                        <?php if (!$workspaceMemberships): ?>
                            <li class="workspace-settings-member-empty">Nenhum workspace encontrado.</li>
                        <?php else: ?>
                            <?php foreach ($workspaceMemberships as $workspaceItem): ?>
                                <?php
                                $workspaceId = (int) ($workspaceItem['id'] ?? 0);
                                $workspaceName = (string) ($workspaceItem['name'] ?? 'Workspace');
                                $workspaceRole = normalizeWorkspaceRole((string) ($workspaceItem['member_role'] ?? 'member'));
                                $workspaceRoleLabel = workspaceRoles()[$workspaceRole] ?? 'Usuário';
                                $isOwner = (bool) ($workspaceItem['is_owner'] ?? false);
                                $isPersonalWorkspace = !empty($workspaceItem['is_personal']);
                                $isActiveWorkspace = $currentWorkspaceId === $workspaceId;
                                $memberCount = (int) ($workspaceItem['member_count'] ?? 0);
                                $creatorName = trim((string) ($workspaceItem['creator_name'] ?? ''));
                                ?>
                                <li class="workspace-settings-member-item account-workspace-item<?= $isActiveWorkspace ? ' is-active-workspace' : '' ?><?= $isPersonalWorkspace ? ' is-personal-workspace' : '' ?>">
                                    <?= renderWorkspaceAvatar($workspaceItem, 'avatar small account-workspace-avatar') ?>
                                    <div class="workspace-settings-member-meta account-workspace-meta">
                                        <div class="account-workspace-title-row">
                                            <strong><?= e($workspaceName) ?></strong>
                                            <div class="account-workspace-badges">
                                                <?php if ($isActiveWorkspace): ?>
                                                    <span class="account-workspace-badge account-workspace-badge-active">Workspace ativo</span>
                                                <?php endif; ?>
                                                <?php if ($isPersonalWorkspace): ?>
                                                    <span class="account-workspace-badge account-workspace-badge-personal">Pessoal</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="account-workspace-meta-row">
                                            <span class="workspace-member-role workspace-role-<?= e($workspaceRole) ?>"><?= e($workspaceRoleLabel) ?></span>
                                            <span class="account-workspace-meta-text">
                                            <?= $isOwner ? 'Criado por você' : ('Criado por ' . e($creatorName !== '' ? $creatorName : 'outro usuário')) ?>
                                            &middot; <?= $isPersonalWorkspace ? 'Workspace pessoal' : (e((string) $memberCount) . ' membro(s)') ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="account-workspace-actions">
                                        <?php if (!$isPersonalWorkspace && $isOwner): ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="account_delete_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceId) ?>">
                                                <button type="submit" class="btn btn-mini btn-danger">Remover</button>
                                            </form>
                                        <?php elseif (!$isPersonalWorkspace): ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="account_leave_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceId) ?>">
                                                <button type="submit" class="btn btn-mini btn-ghost">Sair</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>
            </section>
        </main>
        <?php include __DIR__ . '/partials/pwa_install_modal.php'; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var validTabs = ["profile", "security", "plan"];
            var tabButtons = Array.prototype.slice.call(document.querySelectorAll("[data-account-settings-tab]"));
            var panelItems = Array.prototype.slice.call(document.querySelectorAll(".account-settings-panel-item"));

            function panelName(item) {
                if (item.classList.contains("account-settings-panel-plan")) {
                    return "plan";
                }
                return item.getAttribute("data-account-settings-panel") || "profile";
            }

            function activateTab(tab, updateUrl) {
                var activeTab = validTabs.indexOf(tab) >= 0 ? tab : "profile";
                tabButtons.forEach(function (button) {
                    var isActive = button.getAttribute("data-account-settings-tab") === activeTab;
                    button.classList.toggle("is-active", isActive);
                    button.setAttribute("aria-selected", isActive ? "true" : "false");
                });
                panelItems.forEach(function (item) {
                    var isActive = panelName(item) === activeTab;
                    item.classList.toggle("is-account-panel-hidden", !isActive);
                    if (isActive && !item.hasAttribute("data-pwa-install-entry")) {
                        item.hidden = false;
                    }
                });
                document.body.setAttribute("data-account-settings-section", activeTab);
                if (updateUrl) {
                    history.replaceState(null, "", "#" + activeTab);
                }
            }

            tabButtons.forEach(function (button) {
                button.addEventListener("click", function () {
                    activateTab(button.getAttribute("data-account-settings-tab") || "profile", true);
                });
            });

            activateTab(window.location.hash.replace("#", ""), false);
            window.addEventListener("hashchange", function () {
                activateTab(window.location.hash.replace("#", ""), false);
            });

            document.querySelectorAll("[data-account-password-toggle]").forEach(function (button) {
                button.addEventListener("click", function () {
                    var control = button.closest(".account-password-control");
                    var input = control ? control.querySelector("input") : null;
                    if (!input) {
                        return;
                    }
                    var reveal = input.type === "password";
                    input.type = reveal ? "text" : "password";
                    button.textContent = reveal ? "Ocultar" : "Ver";
                    button.setAttribute("aria-label", reveal ? "Ocultar senha" : "Mostrar senha");
                    button.setAttribute("aria-pressed", reveal ? "true" : "false");
                });
            });

            var avatarInput = document.querySelector("[data-account-avatar-input]");
            var avatarName = document.querySelector("[data-account-avatar-name]");
            var avatarPreview = document.querySelector("[data-account-avatar-preview]");
            if (avatarInput) {
                avatarInput.addEventListener("change", function () {
                    var file = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
                    if (avatarName) {
                        avatarName.textContent = file ? file.name : "Nenhum arquivo selecionado";
                    }
                    if (!file || !avatarPreview || !file.type.match(/^image\//)) {
                        return;
                    }
                    var reader = new FileReader();
                    reader.addEventListener("load", function () {
                        var image = document.createElement("img");
                        image.className = "avatar account-profile-avatar";
                        image.alt = "Prévia da foto de perfil";
                        image.src = String(reader.result || "");
                        avatarPreview.replaceChildren(image);
                    });
                    reader.readAsDataURL(file);
                });
            }

            var planCompare = document.querySelector("[data-account-plan-compare]");
            if (planCompare) {
                var currentPlanKey = <?= json_encode($accountPlanKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                var currentInterval = <?= json_encode($accountBillingInterval, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                var hasStripeSubscription = <?= $accountHasStripeSubscription ? 'true' : 'false' ?>;
                var planRanks = {free: 0, solo: 1, team: 2, business: 3, enterprise: 4};
                var intervalButtons = Array.prototype.slice.call(planCompare.querySelectorAll("[data-account-billing-interval]"));
                var planCards = Array.prototype.slice.call(planCompare.querySelectorAll("[data-account-plan-card]"));

                function applyBillingInterval(interval) {
                    intervalButtons.forEach(function (button) {
                        var active = button.getAttribute("data-account-billing-interval") === interval;
                        button.classList.toggle("is-active", active);
                        button.setAttribute("aria-pressed", active ? "true" : "false");
                    });
                    planCards.forEach(function (card) {
                        var price = card.querySelector("[data-account-plan-price]");
                        var note = card.querySelector("[data-account-plan-note]");
                        var input = card.querySelector("[data-account-plan-interval-input]");
                        var submit = card.querySelector("[data-account-plan-submit]");
                        var planKey = card.getAttribute("data-account-plan-key") || "";
                        if (price) {
                            var priceValue = card.getAttribute("data-price-" + interval) || "";
                            price.textContent = priceValue + (planKey === "enterprise" ? "" : "/mês");
                        }
                        if (note) {
                            note.textContent = card.getAttribute("data-note-" + interval) || "";
                        }
                        if (input) input.value = interval;
                        if (!submit) return;
                        var isCurrent = planKey === currentPlanKey && interval === currentInterval;
                        submit.disabled = isCurrent;
                        if (isCurrent) {
                            submit.textContent = "Plano atual";
                        } else if (!hasStripeSubscription) {
                            submit.textContent = "Mudar de plano";
                        } else if ((planRanks[planKey] || 0) > (planRanks[currentPlanKey] || 0)) {
                            submit.textContent = "Fazer upgrade";
                        } else if (planKey === currentPlanKey) {
                            submit.textContent = "Trocar cobrança";
                        } else {
                            submit.textContent = "Mudar no próximo ciclo";
                        }
                    });
                }

                intervalButtons.forEach(function (button) {
                    button.addEventListener("click", function () {
                        applyBillingInterval(button.getAttribute("data-account-billing-interval") || "year");
                    });
                });
                applyBillingInterval(planCompare.getAttribute("data-default-billing-interval") || "year");
            }
        });

        document.addEventListener("click", function (event) {
            var target = event.target instanceof Element ? event.target : null;
            var closeButton = target ? target.closest("[data-flash-close]") : null;
            var flash = closeButton ? closeButton.closest("[data-flash]") : null;
            if (flash) flash.remove();
        });
    </script>
</body>
</html>
