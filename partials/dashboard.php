<?php
if (($currentWorkspaceId ?? null) !== null) {
    $workspaceIdForTaskTags = (int) $currentWorkspaceId;
    $taskTitleTagOptions = taskTitleTagOptionsByWorkspace($workspaceIdForTaskTags);
    $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceIdForTaskTags);
    $hasPersistedTaskTitleTagOptions = hasTaskTitleTagOptionsByWorkspace($workspaceIdForTaskTags);
} else {
    $taskTitleTagOptions = normalizeTaskTitleTagOptionsList(taskTitleTagPresets());
    $taskTitleTagColors = [];
    $hasPersistedTaskTitleTagOptions = false;
}
if (!$hasPersistedTaskTitleTagOptions) {
    foreach ($tasks as $taskWithTagOption) {
        $taskTagOption = normalizeTaskTitleTag((string) ($taskWithTagOption['title_tag'] ?? ''));
        if ($taskTagOption !== '') {
            $taskTitleTagOptions[] = $taskTagOption;
        }
    }
}
$taskTitleTagOptions = normalizeTaskTitleTagOptionsList($taskTitleTagOptions);
$canCreateWorkspace = userCanCreateOwnedWorkspace((int) ($currentUser['id'] ?? 0));
$taskTitleTagOptionsPayload = [];
$taskTitleTagColorsPayload = [];
foreach ($taskTitleTagOptions as $taskTitleTagOptionValue) {
    $taskTitleTagColorValue = taskTitleTagColorForTag((string) $taskTitleTagOptionValue, $taskTitleTagColors);
    $taskTitleTagOptionsPayload[] = [
        'value' => (string) $taskTitleTagOptionValue,
        'color' => $taskTitleTagColorValue,
    ];
    $taskTitleTagColorsPayload[(string) $taskTitleTagOptionValue] = $taskTitleTagColorValue;
}
$statusMetaByKey = is_array($statusConfig['meta_by_key'] ?? null) ? $statusConfig['meta_by_key'] : [];
$workspaceSwitchView = normalizeDashboardViewKey((string) ($_GET['view'] ?? 'overview'));
$serverSelectedDashboardView = resolveWorkspaceDashboardView(
    $workspaceSwitchView !== '' ? $workspaceSwitchView : 'overview',
    $currentWorkspaceId ?? null,
    $currentWorkspace ?? null,
    !empty($showUsersDashboardTab),
    'overview'
);
$workspaceSwitchRedirectParams = [];
if ($serverSelectedDashboardView === 'accounting') {
    $workspaceSwitchAccountingPeriod = normalizeAccountingPeriodKey((string) ($_GET['accounting_period'] ?? ''));
    if ($workspaceSwitchAccountingPeriod !== '') {
        $workspaceSwitchRedirectParams['accounting_period'] = $workspaceSwitchAccountingPeriod;
    }
} elseif ($serverSelectedDashboardView === 'tasks') {
    $workspaceSwitchTaskScope = normalizeTaskPageMode((string) ($_GET['task_scope'] ?? ''));
    if ($workspaceSwitchTaskScope !== '') {
        $workspaceSwitchRedirectParams['task_scope'] = $workspaceSwitchTaskScope;
    }

    $workspaceSwitchTaskLayout = normalizeTaskLayoutKey((string) ($_GET['task_layout'] ?? ''));
    if ($workspaceSwitchTaskLayout === 'calendar') {
        $workspaceSwitchRedirectParams['task_layout'] = 'calendar';
        $workspaceSwitchCalendarMonth = normalizeTaskCalendarMonth((string) ($_GET['calendar_month'] ?? ''));
        if ($workspaceSwitchCalendarMonth !== '') {
            $workspaceSwitchRedirectParams['calendar_month'] = $workspaceSwitchCalendarMonth;
        }
    }

    $workspaceSwitchTaskGroup = normalizeTaskGroupName((string) ($_GET['group'] ?? ''));
    if ($workspaceSwitchTaskGroup !== '') {
        $workspaceSwitchRedirectParams['group'] = $workspaceSwitchTaskGroup;
    }
}
$workspaceSwitchRedirectPath = dashboardPath($serverSelectedDashboardView, $workspaceSwitchRedirectParams);
?>
<script
    type="application/json"
    id="task-title-tag-options-data"
    data-workspace-id="<?= e((string) ($currentWorkspaceId ?? 0)) ?>"
    data-current-user-id="<?= e((string) ((int) ($currentUser['id'] ?? 0))) ?>"
><?= json_encode(
    [
        'options' => $taskTitleTagOptionsPayload,
        'tag_colors' => $taskTitleTagColorsPayload,
        'palette' => taskTitleTagPalette(),
        'default_color' => taskTitleTagDefaultColor(),
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<main class="dashboard dashboard-compact">
    <section class="workspace-layout tasklist-layout">
        <aside class="panel users-sidebar" id="team">
            <div class="workspace-mobile-header-row">
                <a href="<?= e(appPath()) ?>" class="sidebar-brand" aria-label="<?= e(APP_NAME) ?>">
                    <img
                        src="assets/Bexon - Logo Horizontal Negativa.png?v=1"
                        alt="<?= e(APP_NAME) ?>"
                        class="sidebar-brand-lockup brand-lockup"
                        width="116"
                        height="29"
                    >
                </a>
                <button
                    type="button"
                    class="sidebar-view-toggle workspace-mobile-overview-button is-active"
                    data-dashboard-view-toggle
                    data-dashboard-return-toggle
                    data-dashboard-back-label="Voltar"
                    data-view="overview"
                    aria-pressed="true"
                    aria-label="Dashboard geral"
                    title="Dashboard geral"
                >
                    <span class="sidebar-view-toggle-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M4 11.5 12 5l8 6.5"></path>
                            <path d="M6.5 10v9h11v-9"></path>
                            <path d="M10 19v-5h4v5"></path>
                        </svg>
                    </span>
                </button>
                <div class="workspace-mobile-header-actions" data-dashboard-mobile-header-actions></div>
            </div>
            <div class="users-sidebar-body">
                <div class="panel-header workspace-sidebar-header">
                    <button
                        type="button"
                        class="sidebar-view-toggle workspace-sidebar-overview-toggle is-active"
                        data-dashboard-view-toggle
                        data-dashboard-return-toggle
                        data-dashboard-back-label="Voltar"
                        data-view="overview"
                        aria-pressed="true"
                        aria-label="Dashboard geral"
                        title="Dashboard geral"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M4 11.5 12 5l8 6.5"></path>
                                <path d="M6.5 10v9h11v-9"></path>
                                <path d="M10 19v-5h4v5"></path>
                            </svg>
                        </span>
                    </button>
                    <div class="workspace-sidebar-heading-row">
                        <button
                            type="button"
                            class="sidebar-view-toggle workspace-mobile-menu-button"
                            data-mobile-sidebar-toggle
                            aria-expanded="false"
                            aria-controls="workspace-sidebar-menu"
                            aria-label="Abrir menu do workspace"
                            title="Menu"
                        >
                            <span class="workspace-mobile-menu-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M4 7h16"></path>
                                    <path d="M4 12h16"></path>
                                    <path d="M4 17h16"></path>
                                </svg>
                            </span>
                        </button>
                        <details class="workspace-sidebar-picker">
                            <summary aria-label="Trocar workspace">
                                <?php include __DIR__ . '/workspace_sidebar_picker_summary.php'; ?>
                                <span class="workspace-sidebar-picker-caret" aria-hidden="true">&#9662;</span>
                            </summary>
                            <div class="workspace-sidebar-picker-menu">
                                <div class="workspace-sidebar-picker-list">
                                    <?php foreach ($userWorkspaces as $workspaceOption): ?>
                                        <?php
                                        $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
                                        $workspaceOptionName = (string) ($workspaceOption['name'] ?? 'Workspace');
                                        $isCurrentWorkspace = $currentWorkspaceId === $workspaceOptionId;
                                        ?>
                                        <?php if ($isCurrentWorkspace): ?>
                                            <span class="workspace-sidebar-picker-current">
                                                <?= renderWorkspaceAvatar($workspaceOption, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
                                                <span class="workspace-sidebar-picker-item-text" title="<?= e($workspaceOptionName) ?>"><?= e($workspaceOptionName) ?></span>
                                            </span>
                                        <?php else: ?>
                                            <form method="post" class="workspace-sidebar-picker-form">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="switch_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceOptionId) ?>">
                                                <input type="hidden" name="redirect_to" value="<?= e($workspaceSwitchRedirectPath) ?>">
                                                <button type="submit" class="workspace-sidebar-picker-option" title="<?= e($workspaceOptionName) ?>">
                                                    <?= renderWorkspaceAvatar($workspaceOption, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
                                                    <span class="workspace-sidebar-picker-item-text" title="<?= e($workspaceOptionName) ?>"><?= e($workspaceOptionName) ?></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!empty($canCreateWorkspace)): ?>
                                    <button
                                        type="button"
                                        class="workspace-sidebar-create-trigger"
                                        data-open-workspace-create-modal
                                    >
                                        <span aria-hidden="true">+</span>
                                        <span>Criar workspace</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </details>
                        <div class="workspace-sidebar-heading-actions">
                            <?php if (!empty($showUsersDashboardTab)): ?>
                                <button
                                    type="button"
                                    class="sidebar-view-toggle workspace-users-settings-button"
                                    data-dashboard-view-toggle
                                    data-dashboard-return-toggle
                                    data-dashboard-back-label="Voltar"
                                    data-view="users"
                                    aria-pressed="false"
                                    aria-label="ConfiguraÃ§Ãµes do workspace"
                                    title="ConfiguraÃ§Ãµes do workspace"
                                >
                                    <span class="workspace-users-settings-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M4 6h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 18h16"></path>
                                            <circle cx="9" cy="6" r="2"></circle>
                                            <circle cx="15" cy="12" r="2"></circle>
                                            <circle cx="11" cy="18" r="2"></circle>
                                        </svg>
                                    </span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php include __DIR__ . '/workspace_sidebar_menu.php'; ?>
                <?php include __DIR__ . '/workspace_plan_card.php'; ?>
            </div>
        </aside>

        <header class="top-nav dashboard-nav dashboard-content-nav">
            <section class="stats-strip dashboard-stats dashboard-nav-stats" aria-label="Indicadores do painel">
                <div class="stat-cell">
                    <span data-dashboard-stat-label data-workspace-label="Tarefas" data-overview-label="Minhas tarefas">Tarefas</span>
                    <strong
                        data-dashboard-stat-total
                        data-dashboard-stat-value
                        data-workspace-value="<?= e((string) $stats['total']) ?>"
                        data-overview-value="<?= e((string) ($overviewStats['total'] ?? 0)) ?>"
                    ><?= e((string) $stats['total']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span>Concluídas</span>
                    <strong
                        data-dashboard-stat-done
                        data-dashboard-stat-value
                        data-workspace-value="<?= e((string) $stats['done']) ?> (<?= e((string) $completionRate) ?>%)"
                        data-overview-value="<?= e((string) ($overviewStats['done'] ?? 0)) ?> (<?= e((string) ($overviewCompletionRate ?? 0)) ?>%)"
                    ><?= e((string) $stats['done']) ?> (<?= e((string) $completionRate) ?>%)</strong>
                </div>
                <div class="stat-cell">
                    <span data-dashboard-stat-label data-workspace-label="Para hoje" data-overview-label="Hoje">Para hoje</span>
                    <strong
                        data-dashboard-stat-due-today
                        data-dashboard-stat-value
                        data-workspace-value="<?= e((string) $stats['due_today']) ?>"
                        data-overview-value="<?= e((string) ($overviewStats['due_today'] ?? 0)) ?>"
                    ><?= e((string) $stats['due_today']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span data-dashboard-stat-label data-workspace-label="Urgentes" data-overview-label="Urgentes hoje">Urgentes</span>
                    <strong
                        data-dashboard-stat-urgent
                        data-dashboard-stat-value
                        data-workspace-value="<?= e((string) $stats['urgent']) ?>"
                        data-overview-value="<?= e((string) ($overviewStats['urgent_today'] ?? 0)) ?>"
                    ><?= e((string) $stats['urgent']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span data-dashboard-stat-label data-workspace-label="Minhas abertas" data-overview-label="Abertas">Minhas abertas</span>
                    <strong
                        data-dashboard-stat-my-open
                        data-dashboard-stat-value
                        data-workspace-value="<?= e((string) $myOpenTasks) ?>"
                        data-overview-value="<?= e((string) ($overviewStats['open'] ?? 0)) ?>"
                    ><?= e((string) $myOpenTasks) ?></strong>
                </div>
            </section>

            <div class="dashboard-nav-main">
                <div class="user-chip">
                    <?= renderUserAvatar($currentUser) ?>
                    <div>
                        <strong><?= e((string) $currentUser['name']) ?></strong>
                        <span><?= e((string) $currentUser['email']) ?></span>
                    </div>
                </div>

                <div class="top-nav-actions">
                    <div class="header-notification-menu" data-header-notifications>
                        <button
                            type="button"
                            class="header-notification-button"
                            data-header-notifications-toggle
                            aria-label="NotificaÃ§Ãµes"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            <span class="header-notification-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M7.5 9.5a4.5 4.5 0 1 1 9 0v2.2c0 1.2.4 2.4 1.1 3.4l.9 1.3H5.5l.9-1.3c.7-1 1.1-2.2 1.1-3.4V9.5Z"></path>
                                    <path d="M10 19a2 2 0 0 0 4 0"></path>
                                </svg>
                            </span>
                            <span class="header-notification-count" data-header-notifications-count hidden>0</span>
                        </button>
                        <div
                            class="header-notification-dropdown"
                            data-header-notifications-dropdown
                            role="menu"
                            aria-label="Lista de notificaÃ§Ãµes"
                            hidden
                        >
                            <div class="header-notification-dropdown-head">
                                <strong>NotificaÃ§Ãµes</strong>
                            </div>
                            <div class="header-notification-list" data-header-notifications-list>
                                <p class="header-notification-empty">Sem notificaÃ§Ãµes.</p>
                            </div>
                        </div>
                    </div>
                    <a
                        href="<?= e(appPath('account-settings')) ?>"
                        class="icon-gear-button top-account-settings-button"
                        aria-label="ConfiguraÃ§Ãµes da conta"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                            <circle cx="12" cy="12" r="3.2"></circle>
                        </svg>
                    </a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-pill btn-logout" aria-label="Sair">
                            <span class="btn-logout-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M10 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19H10"></path>
                                    <path d="M14 8l4 4-4 4"></path>
                                    <path d="M8.5 12H18"></path>
                                </svg>
                            </span>
                            <span class="btn-logout-label">Sair</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <aside class="dashboard-install-toast" data-pwa-install-entry="dashboard" role="region" aria-label="Instalar aplicativo" hidden>
            <div class="dashboard-install-toast-copy">
                <strong>Instale o Bexon</strong>
                <span data-pwa-install-message>
                    Abra o Bexon direto da tela inicial.
                </span>
            </div>
            <div class="dashboard-install-toast-actions">
                <button type="button" class="dashboard-install-toast-action dashboard-install-toast-action-primary" data-pwa-install-trigger>
                    Ver passos
                </button>
                <button type="button" class="dashboard-install-toast-action dashboard-install-toast-action-secondary" data-pwa-install-dismiss>
                    Depois
                </button>
            </div>
        </aside>

        <section class="overview-wrap panel" id="overview" data-dashboard-view-panel="overview"<?= $serverSelectedDashboardView !== 'overview' ? ' hidden' : '' ?>>
            <?php if (false): ?>
            <?php
            $overviewExecutiveTone = (string) ($globalDashboardOverview['executive_status_tone'] ?? 'stable');
            $overviewExecutiveFocusTotal = (int) ($globalDashboardOverview['executive_focus_total'] ?? 0);
            $overviewExecutiveHeadline = $overviewExecutiveFocusTotal > 0
                ? $overviewExecutiveFocusTotal . ' ponto(s) pedem atenÃ§Ã£o hoje'
                : 'OperaÃ§Ã£o sob controle hoje';
            $overviewWorkspaceSummaries = array_values((array) ($globalDashboardOverview['workspace_summaries'] ?? []));
            $overviewTopWorkspaceSummaries = array_slice($overviewWorkspaceSummaries, 0, 4);
            $overviewMaxAttentionScore = 0;
            $overviewNegativeWorkspaceTotal = 0;
            foreach ($overviewWorkspaceSummaries as $overviewWorkspaceSummaryItem) {
                $overviewMaxAttentionScore = max(
                    $overviewMaxAttentionScore,
                    (int) ($overviewWorkspaceSummaryItem['attention_score'] ?? 0)
                );
                if ((int) ($overviewWorkspaceSummaryItem['balance_total_cents'] ?? 0) < 0) {
                    $overviewNegativeWorkspaceTotal++;
                }
            }
            $overviewImportantHighlights = [];
            $overviewDueSoonTotal = (int) ($globalDashboardOverview['due_soon_total'] ?? 0);
            $overviewTasksTodayTotal = (int) ($globalDashboardOverview['tasks_today_total'] ?? 0);
            $overviewUrgentTasksTodayTotal = (int) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0);
            $overviewLowStockTotal = (int) ($globalDashboardOverview['low_stock_total'] ?? 0);

            if ($overviewDueSoonTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Contas a vencer',
                    'value' => $overviewDueSoonTotal,
                ];
            }

            if ($overviewTasksTodayTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Tarefas do dia',
                    'value' => $overviewTasksTodayTotal,
                ];
            }

            if ($overviewUrgentTasksTodayTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Tarefas urgentes',
                    'value' => $overviewUrgentTasksTodayTotal,
                ];
            }

            if ($overviewLowStockTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Baixo estoque',
                    'value' => $overviewLowStockTotal,
                ];
            }
            $overviewPriorityTasksTodayTotal = (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0);
            $overviewDueTodayTotal = (int) ($globalDashboardOverview['due_today_total'] ?? 0);
            $overviewDueTomorrowTotal = (int) ($globalDashboardOverview['due_tomorrow_total'] ?? 0);
            $overviewAttentionWorkspaceTotal = (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0);
            $overviewBalanceTotalCents = (int) ($globalDashboardOverview['balance_total_cents'] ?? 0);
            $showOverviewTasksStatCard = $overviewTasksTodayTotal > 0;
            $showOverviewDueStatCard = $overviewDueSoonTotal > 0 || $overviewDueTodayTotal > 0 || $overviewDueTomorrowTotal > 0;
            $showOverviewBalanceStatCard = $overviewBalanceTotalCents !== 0;
            $showOverviewLowStockStatCard = $overviewLowStockTotal > 0;
            ?>
            <div class="panel-header board-header overview-board-header">
                <div>
                    <h2>Dashboard geral</h2>
                    <p>Resumo pessoal de todos os workspaces.</p>
                </div>
                <?php if (!empty($overviewImportantHighlights)): ?>
                    <div class="board-summary overview-board-summary" aria-label="Indicadores importantes">
                        <?php foreach ($overviewImportantHighlights as $overviewImportantHighlight): ?>
                            <span class="overview-summary-pill">
                                <?= e((string) ($overviewImportantHighlight['label'] ?? '')) ?>: <?= e((string) ($overviewImportantHighlight['value'] ?? 0)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="overview-executive-grid" style="--overview-order: 0;">
                <section class="overview-executive-strip is-<?= e($overviewExecutiveTone) ?>" aria-label="Prioridades do dia">
                    <div class="overview-executive-main">
                        <span class="overview-executive-kicker">Prioridade do dia</span>
                        <div class="overview-executive-heading">
                            <h3><?= e($overviewExecutiveHeadline) ?></h3>
                            <span class="overview-executive-badge is-<?= e($overviewExecutiveTone) ?>">
                                <?= e((string) ($globalDashboardOverview['executive_status_label'] ?? 'OperaÃ§Ã£o estÃ¡vel')) ?>
                            </span>
                        </div>
                        <p><?= e((string) ($globalDashboardOverview['executive_status_note'] ?? '')) ?></p>
                        <?php if (!empty($overviewImportantHighlights)): ?>
                            <div class="overview-executive-chips" aria-label="Indicadores rÃ¡pidos">
                                <?php foreach ($overviewImportantHighlights as $overviewImportantHighlight): ?>
                                    <span class="overview-executive-chip">
                                        <?= e((string) ($overviewImportantHighlight['label'] ?? '')) ?>: <?= e((string) ($overviewImportantHighlight['value'] ?? 0)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <dl class="overview-executive-focus">
                        <div class="overview-focus-card is-critical">
                            <dt>Urgentes hoje</dt>
                            <dd><?= e((string) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0)) ?></dd>
                            <small>Tarefas com prioridade maxima</small>
                        </div>
                        <div class="overview-focus-card is-due">
                            <dt>Vence hoje</dt>
                            <dd><?= e((string) ($globalDashboardOverview['due_today_total'] ?? 0)) ?></dd>
                            <small>Contas com decisÃ£o imediata</small>
                        </div>
                        <div class="overview-focus-card is-balance">
                            <dt>Fluxo do mes</dt>
                            <dd><?= e((string) ($globalDashboardOverview['balance_month_movement_display'] ?? 'R$ 0,00')) ?></dd>
                            <small>Movimento consolidado atual</small>
                        </div>
                    </dl>
                </section>

                <aside class="overview-radar-card" aria-label="Radar de workspaces">
                    <div class="overview-radar-top">
                        <div>
                            <span class="overview-radar-kicker">Radar</span>
                            <h3>Workspaces sob maior pressao</h3>
                            <p>Comparativo rÃ¡pido para decidir onde entrar primeiro.</p>
                        </div>
                        <div class="overview-radar-summary">
                            <span class="overview-summary-pill">Criticos <?= e((string) ($globalDashboardOverview['critical_workspace_total'] ?? 0)) ?></span>
                            <span class="overview-summary-pill">Saldo negativo <?= e((string) $overviewNegativeWorkspaceTotal) ?></span>
                        </div>
                    </div>
                    <?php if (empty($overviewTopWorkspaceSummaries)): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhum comparativo disponÃ­vel</strong>
                                <span>Assim que houver workspaces visÃ­veis, o radar aparece aqui.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <ol class="overview-radar-list">
                            <?php foreach ($overviewTopWorkspaceSummaries as $radarIndex => $workspaceSummary): ?>
                                <?php
                                $workspaceSummaryId = (int) ($workspaceSummary['workspace_id'] ?? 0);
                                $workspaceAttentionTone = (string) ($workspaceSummary['attention_tone'] ?? 'stable');
                                $workspaceAttentionScore = (int) ($workspaceSummary['attention_score'] ?? 0);
                                $workspaceAttentionShare = $overviewMaxAttentionScore > 0
                                    ? (int) max(
                                        $workspaceAttentionScore > 0 ? 14 : 0,
                                        round(($workspaceAttentionScore / $overviewMaxAttentionScore) * 100)
                                    )
                                    : 0;
                                ?>
                                <li class="overview-radar-item is-<?= e($workspaceAttentionTone) ?>" style="--overview-item-order: <?= e((string) ($radarIndex + 1)) ?>;">
                                    <div class="overview-radar-item-top">
                                        <span class="overview-radar-rank"><?= e(str_pad((string) ($radarIndex + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                        <div class="overview-radar-item-main">
                                            <div class="overview-radar-item-heading">
                                                <strong><?= e((string) ($workspaceSummary['workspace_name'] ?? 'Workspace')) ?></strong>
                                                <?php if ($workspaceSummaryId === (int) ($currentWorkspaceId ?? 0)): ?>
                                                    <span class="overview-workspace-current">Ativo</span>
                                                <?php endif; ?>
                                            </div>
                                            <p><?= e((string) ($workspaceSummary['attention_note'] ?? 'Sem pendÃªncias imediatas.')) ?></p>
                                        </div>
                                        <span class="overview-workspace-health is-<?= e($workspaceAttentionTone) ?>">
                                            <?= e((string) ($workspaceSummary['attention_label'] ?? 'EstÃ¡vel')) ?>
                                        </span>
                                    </div>
                                    <div class="overview-radar-meter" style="--attention-share: <?= e((string) $workspaceAttentionShare) ?>%;">
                                        <span class="overview-radar-meter-fill is-<?= e($workspaceAttentionTone) ?>"></span>
                                    </div>
                                    <dl class="overview-radar-kpis">
                                        <div>
                                            <dt>Score</dt>
                                            <dd><?= e((string) $workspaceAttentionScore) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Hoje</dt>
                                            <dd><?= e((string) ($workspaceSummary['tasks_today_count'] ?? 0)) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Saldo</dt>
                                            <dd><?= e((string) ($workspaceSummary['balance_total_display'] ?? 'R$ 0,00')) ?></dd>
                                        </div>
                                    </dl>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if ($showOverviewTasksStatCard || $showOverviewDueStatCard || $showOverviewBalanceStatCard || $showOverviewLowStockStatCard): ?>
                <section class="stats-strip overview-stats" style="--overview-order: 1;" aria-label="Resumo geral do usuÃ¡rio">
                    <?php if ($showOverviewTasksStatCard): ?>
                        <article class="stat-cell overview-stat-card is-tasks">
                            <span>Tarefas de hoje</span>
                            <strong><?= e((string) $overviewTasksTodayTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewPriorityTasksTodayTotal) ?> com alta prioridade</small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewDueStatCard): ?>
                        <article class="stat-cell overview-stat-card is-due">
                            <span>Vencimentos prÃ³ximos</span>
                            <strong><?= e((string) $overviewDueSoonTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewDueTodayTotal) ?> hoje, <?= e((string) $overviewDueTomorrowTotal) ?> amanh?</small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewBalanceStatCard): ?>
                        <article class="stat-cell overview-stat-card is-balance">
                            <span>Saldo atual</span>
                            <strong><?= e((string) ($globalDashboardOverview['balance_total_display'] ?? 'R$ 0,00')) ?></strong>
                            <small class="overview-stat-note">Mov. mes <?= e((string) ($globalDashboardOverview['balance_month_movement_display'] ?? 'R$ 0,00')) ?></small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewLowStockStatCard): ?>
                        <article class="stat-cell overview-stat-card is-stock">
                            <span>Baixo estoque</span>
                            <strong><?= e((string) $overviewLowStockTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewAttentionWorkspaceTotal) ?> workspace(s) em monitoramento</small>
                        </article>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="overview-panels-grid" style="--overview-order: 2;">
                <section class="overview-card overview-card-tasks">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Tarefas do dia</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['tasks_today_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="tasks">
                            Abrir tarefas
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rÃ¡pido de tarefas">
                        <span class="overview-card-inline-chip is-critical">Urgentes <?= e((string) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip">Alta prioridade <?= e((string) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0)) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['tasks_today'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhuma tarefa sua para hoje</strong>
                                <span>Seu foco est? livre neste momento.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="tasks">
                                Abrir tarefas
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['tasks_today'] as $taskToday): ?>
                                <?php $taskPriorityClass = normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium')); ?>
                                <li class="overview-list-item overview-list-item-task priority-<?= e($taskPriorityClass) ?>">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($taskToday['title'] ?? 'Tarefa')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($taskToday['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($taskToday['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <span class="overview-priority priority-<?= e($taskPriorityClass) ?>">
                                        <?= e((string) ($taskToday['priority_label'] ?? 'Media')) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="overview-card overview-card-due">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Contas para vencer</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['due_soon_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="accounting">
                            Abrir contabilidade
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rÃ¡pido de vencimentos">
                        <span class="overview-card-inline-chip is-critical">Hoje <?= e((string) ($globalDashboardOverview['due_today_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip is-attention">AmanhÃ£ <?= e((string) ($globalDashboardOverview['due_tomorrow_total'] ?? 0)) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['due_soon'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Sem vencimentos no curto prazo</strong>
                                <span>Nenhuma conta vence na janela configurada.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="accounting">
                                Abrir contabilidade
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['due_soon'] as $dueSoonItem): ?>
                                <?php
                                $dueDaysLabel = trim((string) ($dueSoonItem['days_until_label'] ?? ''));
                                $dueDateLabel = trim((string) ($dueSoonItem['next_due_display'] ?? ''));
                                $dueWhenLabel = $dueDaysLabel !== '' && $dueDateLabel !== ''
                                    ? $dueDaysLabel . ' (' . $dueDateLabel . ')'
                                    : ($dueDaysLabel !== '' ? $dueDaysLabel : $dueDateLabel);
                                $dueDaysUntil = $dueSoonItem['days_until'] ?? null;
                                $dueToneClass = 'calm';
                                if ($dueDaysUntil === 0) {
                                    $dueToneClass = 'critical';
                                } elseif ($dueDaysUntil === 1) {
                                    $dueToneClass = 'attention';
                                }
                                ?>
                                <li class="overview-list-item overview-list-item-due tone-<?= e($dueToneClass) ?>">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($dueSoonItem['label'] ?? 'Vencimento')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($dueSoonItem['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($dueSoonItem['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <div class="overview-list-aside">
                                        <span class="overview-when is-<?= e($dueToneClass) ?>"><?= e($dueWhenLabel) ?></span>
                                        <span class="overview-amount"><?= e((string) ($dueSoonItem['amount_display'] ?? 'R$ 0,00')) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="overview-card overview-card-stock">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Itens com baixo estoque</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['low_stock_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="inventory">
                            Abrir estoque
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rÃ¡pido de estoque">
                        <span class="overview-card-inline-chip is-attention">Itens abaixo do mÃ­nimo <?= e((string) ($globalDashboardOverview['low_stock_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip">Workspaces em foco <?= e((string) (((int) ($globalDashboardOverview['critical_workspace_total'] ?? 0)) + ((int) ($globalDashboardOverview['attention_workspace_total'] ?? 0)))) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['low_stock'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhum item abaixo do mÃ­nimo</strong>
                                <span>Seu estoque visÃ­vel estÃ¡ equilibrado agora.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="inventory">
                                Abrir estoque
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['low_stock'] as $lowStockItem): ?>
                                <li class="overview-list-item overview-list-item-stock">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($lowStockItem['label'] ?? 'Item')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($lowStockItem['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($lowStockItem['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <div class="overview-list-aside">
                                        <span class="overview-stock-meta"><?= e((string) ($lowStockItem['quantity_display'] ?? '0')) ?>/<?= e((string) ($lowStockItem['min_quantity_display'] ?? '0')) ?> <?= e((string) ($lowStockItem['unit_label'] ?? 'un')) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <section class="overview-card overview-workspaces-card" style="--overview-order: 3;">
                <div class="overview-card-top">
                    <header class="overview-card-head">
                        <h3>Resumo por workspace</h3>
                        <span class="overview-card-count"><?= e((string) count($overviewWorkspaceSummaries)) ?></span>
                    </header>
                    <p class="overview-card-subtitle">Saldo referente a <?= e((string) ($globalDashboardOverview['accounting_period_label'] ?? '')) ?></p>
                </div>
                <?php if (empty($overviewWorkspaceSummaries)): ?>
                    <div class="overview-empty-state">
                        <span class="overview-empty-icon" aria-hidden="true"></span>
                        <div class="overview-empty-copy">
                            <strong>Nenhum workspace encontrado</strong>
                            <span>Sua conta ainda nÃ£o possui workspaces visÃ­veis para resumir.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overview-workspaces-list">
                        <?php foreach ($overviewWorkspaceSummaries as $workspaceIndex => $workspaceSummary): ?>
                            <?php
                            $workspaceSummaryId = (int) ($workspaceSummary['workspace_id'] ?? 0);
                            $isActiveWorkspaceSummary = $workspaceSummaryId === (int) ($currentWorkspaceId ?? 0);
                            $workspaceAttentionTone = (string) ($workspaceSummary['attention_tone'] ?? 'stable');
                            $workspaceAttentionScore = (int) ($workspaceSummary['attention_score'] ?? 0);
                            $workspaceAttentionShare = $overviewMaxAttentionScore > 0
                                ? (int) max(
                                    $workspaceAttentionScore > 0 ? 14 : 0,
                                    round(($workspaceAttentionScore / $overviewMaxAttentionScore) * 100)
                                )
                                : 0;
                            ?>
                            <article class="overview-workspace-item is-<?= e($workspaceAttentionTone) ?><?= $isActiveWorkspaceSummary ? ' is-active-workspace' : '' ?>" style="--overview-item-order: <?= e((string) ($workspaceIndex + 1)) ?>;">
                                <div class="overview-workspace-meta">
                                    <div class="overview-workspace-title">
                                        <div class="overview-workspace-title-row">
                                            <strong><?= e((string) ($workspaceSummary['workspace_name'] ?? 'Workspace')) ?></strong>
                                            <span class="overview-workspace-health is-<?= e($workspaceAttentionTone) ?>">
                                                <?= e((string) ($workspaceSummary['attention_label'] ?? 'EstÃ¡vel')) ?>
                                            </span>
                                        </div>
                                        <div class="overview-workspace-badges">
                                            <span class="overview-workspace-role"><?= e((string) ($workspaceSummary['workspace_role_label'] ?? 'UsuÃ¡rio')) ?></span>
                                            <?php if ((int) ($workspaceSummary['urgent_tasks_today_count'] ?? 0) > 0): ?>
                                                <span class="overview-workspace-chip is-critical"><?= e((string) ($workspaceSummary['urgent_tasks_today_count'] ?? 0)) ?> urgente(s)</span>
                                            <?php elseif ((int) ($workspaceSummary['due_today_count'] ?? 0) > 0): ?>
                                                <span class="overview-workspace-chip is-attention"><?= e((string) ($workspaceSummary['due_today_count'] ?? 0)) ?> vence hoje</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="overview-workspace-actions">
                                        <?php if ($isActiveWorkspaceSummary): ?>
                                            <span class="overview-workspace-current">Ativo</span>
                                        <?php elseif ($workspaceSummaryId > 0): ?>
                                            <form method="post" class="overview-workspace-open-form">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="switch_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceSummaryId) ?>">
                                                <input type="hidden" name="redirect_to" value="<?= e($workspaceSwitchRedirectPath) ?>">
                                                <button type="submit" class="overview-workspace-open">Abrir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="overview-workspace-note"><?= e((string) ($workspaceSummary['attention_note'] ?? 'Sem pendÃªncias imediatas.')) ?></p>
                                <div class="overview-workspace-meter">
                                    <div class="overview-workspace-meter-meta">
                                        <span>Pressao operacional</span>
                                        <strong>Score <?= e((string) $workspaceAttentionScore) ?></strong>
                                    </div>
                                    <div class="overview-radar-meter" style="--attention-share: <?= e((string) $workspaceAttentionShare) ?>%;">
                                        <span class="overview-radar-meter-fill is-<?= e($workspaceAttentionTone) ?>"></span>
                                    </div>
                                </div>
                                <dl class="overview-workspace-kpis">
                                    <div class="overview-workspace-kpi is-today">
                                        <dt>Hoje</dt>
                                        <dd><?= e((string) ($workspaceSummary['tasks_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-critical">
                                        <dt>Urgente</dt>
                                        <dd><?= e((string) ($workspaceSummary['urgent_tasks_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-due">
                                        <dt>Vence hoje</dt>
                                        <dd><?= e((string) ($workspaceSummary['due_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-tomorrow">
                                        <dt>AmanhÃ£</dt>
                                        <dd><?= e((string) ($workspaceSummary['due_tomorrow_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-stock">
                                        <dt>Baixo</dt>
                                        <dd><?= e((string) ($workspaceSummary['low_stock_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-balance">
                                        <dt>Saldo</dt>
                                        <dd><?= e((string) ($workspaceSummary['balance_total_display'] ?? 'R$ 0,00')) ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php else: ?>
                <?php require __DIR__ . '/dashboard_overview_summary.php'; ?>
            <?php endif; ?>
        </section>

        <section class="tasklist-wrap panel" id="tasks" data-dashboard-view-panel="tasks"<?= $serverSelectedDashboardView !== 'tasks' ? ' hidden' : '' ?>>
            <?php require __DIR__ . '/tasks_panel_content.php'; ?>
        </section>
        <script>
            (() => {
                const workspaceId = String(document.body?.dataset?.workspaceId || "").trim() || "0";
                const storageKey = `wf_group_done_hidden:${workspaceId}:tasks`;
                let decoded = {};
                try {
                    const raw = window.localStorage?.getItem(storageKey) || "";
                    decoded = raw ? JSON.parse(raw) : {};
                } catch (_error) {
                    return;
                }

                if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
                    return;
                }

                const hiddenMap = {};
                Object.entries(decoded).forEach(([groupName, hidden]) => {
                    const normalizedGroupName = String(groupName || "").trim().toLocaleLowerCase();
                    if (!normalizedGroupName || !hidden) return;
                    hiddenMap[normalizedGroupName] = true;
                });

                const cookieName = `wf_group_done_hidden_tasks_${workspaceId}`;
                const hiddenGroupNames = Object.keys(hiddenMap);
                if (!hiddenGroupNames.length) {
                    document.cookie = `${cookieName}=; path=/; max-age=0; SameSite=Lax`;
                } else {
                    document.cookie = `${cookieName}=${encodeURIComponent(
                        JSON.stringify(hiddenMap)
                    )}; path=/; max-age=31536000; SameSite=Lax`;
                }

                let visibleTaskCount = 0;
                document.querySelectorAll("[data-task-groups-list] [data-task-group]").forEach((groupSection) => {
                    if (!(groupSection instanceof HTMLElement)) return;
                    const groupName = String(groupSection.dataset.groupName || "").trim().toLocaleLowerCase();
                    const shouldHideDone = Boolean(hiddenMap[groupName]);
                    const dropzone = groupSection.querySelector("[data-task-dropzone]");
                    if (!(dropzone instanceof HTMLElement)) return;

                    groupSection.classList.toggle("is-done-hidden", shouldHideDone);
                    const toggleButton = groupSection.querySelector("[data-toggle-group-done]");
                    const rawGroupName = String(groupSection.dataset.groupName || "Geral").trim() || "Geral";
                    if (toggleButton instanceof HTMLButtonElement) {
                        const nextLabel = shouldHideDone
                            ? String(toggleButton.dataset.labelShow || "").trim() || "Exibir concluídas"
                            : String(toggleButton.dataset.labelHide || "").trim() || "Ocultar concluídas";
                        const labelNode = toggleButton.querySelector(".task-project-done-toggle-label");
                        if (labelNode instanceof HTMLElement) {
                            labelNode.textContent = String(toggleButton.dataset.labelShort || "").trim() || nextLabel;
                        } else {
                            toggleButton.textContent = nextLabel;
                        }
                        toggleButton.classList.toggle("is-active", shouldHideDone);
                        toggleButton.setAttribute("aria-pressed", shouldHideDone ? "true" : "false");
                        toggleButton.setAttribute("aria-label", `${nextLabel} do grupo ${rawGroupName}`);
                    }

                    let groupVisibleTaskCount = 0;
                    let groupHiddenDoneCount = 0;
                    dropzone.querySelectorAll("[data-task-item]").forEach((taskItem) => {
                        if (!(taskItem instanceof HTMLElement)) return;
                        const isDoneTask = String(taskItem.dataset.statusKind || "").trim() === "done";
                        if (shouldHideDone && isDoneTask) {
                            taskItem.hidden = true;
                            groupHiddenDoneCount += 1;
                            return;
                        }

                        taskItem.hidden = false;
                        groupVisibleTaskCount += 1;
                        visibleTaskCount += 1;
                    });

                    const hiddenDoneRow = dropzone.querySelector("[data-task-group-hidden-done-row]");
                    if (groupHiddenDoneCount > 0 && groupVisibleTaskCount === 0) {
                        const hiddenLabel =
                            groupHiddenDoneCount === 1
                                ? "1 tarefa concluída oculta."
                                : `${groupHiddenDoneCount} tarefas concluídas ocultas.`;
                        if (hiddenDoneRow instanceof HTMLElement) {
                            hiddenDoneRow.textContent = hiddenLabel;
                        } else {
                            const row = document.createElement("div");
                            row.className = "task-group-hidden-done-row";
                            row.dataset.taskGroupHiddenDoneRow = "";
                            row.textContent = hiddenLabel;
                            dropzone.append(row);
                        }
                    } else if (hiddenDoneRow instanceof HTMLElement) {
                        hiddenDoneRow.remove();
                    }
                });

                const boardVisibleCount = document.querySelector("[data-board-visible-count]");
                if (boardVisibleCount instanceof HTMLElement) {
                    boardVisibleCount.textContent = `${visibleTaskCount} visÃ­veis`;
                }
            })();
        </script>

        <section class="vault-wrap panel" id="vault" data-dashboard-view-panel="vault"<?= $serverSelectedDashboardView !== 'vault' ? ' hidden' : '' ?>>
            <div class="panel-header board-header vault-header">
                <div>
                    <h2>Gerenciador de acessos</h2>
                </div>
                <div class="board-summary vault-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-vault-group-modal
                        aria-label="Criar grupo no cofre"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-vault-entry-modal
                        aria-label="Adicionar dado de acesso"
                        <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <circle cx="8" cy="12" r="2.4"></circle>
                                <path d="M10.4 12h9.6"></path>
                                <path d="M16 12v2.5"></path>
                                <path d="M19 12v2"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo acesso</span>
                    </button>
                    <span data-vault-total-count><?= e(appItemCountLabel(count($vaultEntries))) ?></span>
                </div>
            </div>

            <div class="vault-groups-list">
                <?php if (empty($vaultEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item no cofre ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($vaultEntriesByGroup as $vaultGroupName => $groupVaultEntries): ?>
                        <?php
                        $vaultGroupPermission = $vaultGroupPermissions[$vaultGroupName] ?? ['can_view' => true, 'can_access' => true];
                        $vaultGroupCanAccess = !empty($vaultGroupPermission['can_access']);
                        $vaultGroupPermissionsModalKey = 'vault-group-perm-' . md5((string) $vaultGroupName);
                        ?>
                        <section class="task-group vault-group<?= $vaultGroupCanAccess ? '' : ' task-group-readonly' ?>" data-vault-group data-group-name="<?= e((string) $vaultGroupName) ?>">
                            <header class="task-group-head" data-vault-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_vault_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $vaultGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $vaultGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo do cofre"
                                                spellcheck="false"
                                                <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                        <button
                                            type="button"
                                            class="group-permissions-button"
                                            data-open-group-permissions-modal="<?= e($vaultGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($vaultGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-vault-entry-modal
                                            data-create-group="<?= e((string) $vaultGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $vaultGroupName) ?>">
                                            <button
                                                type="submit"
                                                class="task-group-delete"
                                                aria-label="Excluir grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$vaultGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupVaultEntries)) ?></span>
                                </div>
                            </header>

                            <div class="vault-group-rows" data-vault-group-rows>
                                <?php if (!$groupVaultEntries): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($vaultGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-vault-entry-modal
                                                data-create-group="<?= e((string) $vaultGroupName) ?>"
                                                aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupVaultEntries as $vaultEntry): ?>
                                    <?php
                                    $vaultEntryId = (int) ($vaultEntry['id'] ?? 0);
                                    $vaultLabel = (string) ($vaultEntry['label'] ?? '');
                                    $vaultLogin = (string) ($vaultEntry['login_value'] ?? '');
                                    $vaultPassword = (string) ($vaultEntry['password_value'] ?? '');
                                    $vaultPasswordUnavailable = !empty($vaultEntry['password_unavailable']);
                                    $vaultCanUsePassword = !$vaultPasswordUnavailable && $vaultPassword !== '';
                                    $vaultGroupValue = (string) ($vaultEntry['group_name'] ?? $vaultGroupName);
                                    ?>
                                    <article
                                        class="vault-entry-row"
                                        data-vault-entry
                                        data-entry-id="<?= e((string) $vaultEntryId) ?>"
                                        data-entry-label="<?= e($vaultLabel) ?>"
                                        data-entry-login="<?= e($vaultLogin) ?>"
                                        data-entry-password="<?= e($vaultPassword) ?>"
                                        data-entry-password-unavailable="<?= $vaultPasswordUnavailable ? '1' : '0' ?>"
                                        data-entry-group="<?= e($vaultGroupValue) ?>"
                                    >
                                        <form method="post" class="vault-entry-name-form" data-vault-entry-name-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="rename_vault_entry_label">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">

                                            <div class="vault-entry-line">
                                                <div class="vault-entry-name">
                                                    <input
                                                        type="text"
                                                        name="label"
                                                        maxlength="120"
                                                        value="<?= e($vaultLabel) ?>"
                                                        class="task-title-input vault-entry-title-input"
                                                        aria-label="Nome do dado de acesso"
                                                        data-vault-entry-label-input
                                                        <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
                                                        required
                                                    >
                                                </div>

                                                <div class="vault-entry-value">
                                                    <span class="vault-entry-value-text"><?= $vaultLogin !== '' ? e($vaultLogin) : '-' ?></span>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultLogin) ?>"
                                                        aria-label="Copiar login"
                                                        <?= $vaultLogin === '' ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div
                                                    class="vault-entry-value vault-entry-password"
                                                    data-vault-password-cell
                                                    data-password-value="<?= e($vaultPassword) ?>"
                                                    data-visible="false"
                                                >
                                                    <?php if ($vaultPasswordUnavailable): ?>
                                                        <span class="vault-entry-value-text" data-vault-password-text>Indisponivel</span>
                                                    <?php else: ?>
                                                    <span class="vault-entry-value-text" data-vault-password-text><?= $vaultPassword !== '' ? 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢' : '-' ?></span>
                                                    <?php endif; ?>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-toggle-password
                                                        aria-label="Mostrar senha"
                                                        <?= !$vaultCanUsePassword ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                                            <circle cx="12" cy="12" r="3"></circle>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultPassword) ?>"
                                                        aria-label="Copiar senha"
                                                        <?= !$vaultCanUsePassword ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="vault-entry-tools">
                                                    <?php if ($vaultGroupCanAccess): ?>
                                                        <button
                                                            type="button"
                                                            class="vault-icon-button"
                                                            data-open-vault-edit-modal
                                                            aria-label="Editar dado de acesso"
                                                        >
                                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                                <path d="m12 6 4 4"></path>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="vault-entry-delete-button"
                                                            data-vault-delete-entry
                                                            data-delete-form-id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>"
                                                            aria-label="Excluir dado de acesso"
                                                        >
                                                            <span aria-hidden="true">&#10005;</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>

                                        <form method="post" id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>


        <section class="inventory-wrap panel" id="inventory" data-dashboard-view-panel="inventory"<?= $serverSelectedDashboardView !== 'inventory' ? ' hidden' : '' ?>>
            <div class="panel-header board-header due-header">
                <div>
                    <h2>Estoque</h2>
                </div>
                <div class="board-summary inventory-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-inventory-group-modal
                        aria-label="Criar grupo de estoque"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-inventory-entry-modal
                        aria-label="Adicionar item ao estoque"
                        <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 5v14"></path>
                                <path d="M5 12h14"></path>
                                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo item</span>
                    </button>
                    <span data-inventory-total-count><?= e(appItemCountLabel(count($inventoryEntries))) ?></span>
                </div>
            </div>

            <div class="inventory-groups-list">
                <?php if (empty($inventoryEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item de estoque cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventoryEntriesByGroup as $inventoryGroupName => $groupInventoryEntries): ?>
                        <section
                            class="task-group inventory-group"
                            data-inventory-group
                            data-group-name="<?= e((string) $inventoryGroupName) ?>"
                        >
                            <header class="task-group-head" data-inventory-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_inventory_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $inventoryGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo de estoque"
                                                spellcheck="false"
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <button
                                        type="button"
                                        class="group-add-button"
                                        data-open-inventory-entry-modal
                                        data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                        aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                    >+</button>
                                    <form method="post" class="task-group-delete-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_inventory_group">
                                        <input type="hidden" name="group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <button
                                            type="submit"
                                            class="task-group-delete"
                                            aria-label="Excluir grupo de estoque <?= e((string) $inventoryGroupName) ?>"
                                        ><span aria-hidden="true">&#10005;</span></button>
                                    </form>
                                    <span class="task-group-count"><?= e((string) count($groupInventoryEntries)) ?></span>
                                </div>
                            </header>

                            <div class="inventory-group-rows" data-inventory-group-rows>
                                <?php if (!$groupInventoryEntries): ?>
                                    <div class="task-group-empty-row">
                                        <button
                                            type="button"
                                            class="task-group-empty-add"
                                            data-open-inventory-entry-modal
                                            data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                        >+</button>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupInventoryEntries as $inventoryEntry): ?>
                                    <?php
                                    $inventoryEntryId = (int) ($inventoryEntry['id'] ?? 0);
                                    $inventoryLabel = (string) ($inventoryEntry['label'] ?? '');
                                    $inventoryQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['quantity_value'] ?? null) ?? 0;
                                    $inventoryQuantityDisplay = (string) ($inventoryEntry['quantity_display'] ?? inventoryQuantityLabel($inventoryQuantityValue));
                                    $inventoryQuantityInput = (string) ($inventoryEntry['quantity_value_input'] ?? inventoryQuantityInputValue($inventoryQuantityValue));
                                    $inventoryMinQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['min_quantity_value'] ?? null);
                                    $inventoryMinQuantityInput = $inventoryMinQuantityValue !== null
                                        ? (string) ($inventoryEntry['min_quantity_value_input'] ?? inventoryQuantityInputValue($inventoryMinQuantityValue))
                                        : '';
                                    $inventoryUnitLabel = normalizeInventoryUnitLabel((string) ($inventoryEntry['unit_label'] ?? 'un'));
                                    $inventoryGroupValue = (string) ($inventoryEntry['group_name'] ?? $inventoryGroupName);
                                    $inventoryNotes = (string) ($inventoryEntry['notes'] ?? '');
                                    $inventoryLowStock = ((int) ($inventoryEntry['is_low_stock'] ?? 0)) === 1;
                                    ?>
                                    <article
                                        class="inventory-entry-row"
                                        data-inventory-entry
                                        data-entry-id="<?= e((string) $inventoryEntryId) ?>"
                                        data-entry-label="<?= e($inventoryLabel) ?>"
                                        data-entry-quantity-value="<?= e($inventoryQuantityInput) ?>"
                                        data-entry-min-quantity-value="<?= e($inventoryMinQuantityInput) ?>"
                                        data-entry-unit-label="<?= e($inventoryUnitLabel) ?>"
                                        data-entry-group="<?= e($inventoryGroupValue) ?>"
                                        data-entry-notes="<?= e($inventoryNotes) ?>"
                                    >
                                        <div class="inventory-entry-main">
                                            <div class="inventory-entry-line">
                                                <span class="inventory-entry-title"><?= e($inventoryLabel) ?></span>
                                                <form
                                                    method="post"
                                                    class="inventory-entry-qty inventory-entry-qty-form"
                                                    data-inventory-inline-quantity-form
                                                    title="Quantidade disponÃ­vel"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_inventory_entry_quantity">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                                    <span class="inventory-entry-inline-label">Qtd.</span>
                                                    <label class="inventory-entry-qty-editor">
                                                        <span class="inventory-entry-qty-control">
                                                            <input
                                                                type="number"
                                                                name="quantity_value"
                                                                min="0"
                                                                step="1"
                                                                value="<?= e($inventoryQuantityInput) ?>"
                                                                class="inventory-entry-qty-input"
                                                                data-inventory-inline-quantity-input
                                                                aria-label="Quantidade de <?= e($inventoryLabel) ?>"
                                                            >
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-right"
                                                                data-inventory-inline-quantity-step
                                                                data-step="1"
                                                                aria-label="Aumentar quantidade"
                                                            >&#9654;</button>
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-left"
                                                                data-inventory-inline-quantity-step
                                                                data-step="-1"
                                                                aria-label="Diminuir quantidade"
                                                            >&#9664;</button>
                                                        </span>
                                                        <span class="inventory-entry-qty-unit"><?= e($inventoryUnitLabel) ?></span>
                                                    </label>
                                                    <button type="submit" class="sr-only">Salvar quantidade</button>
                                                </form>
                                                <?php if ($inventoryLowStock): ?>
                                                    <span class="inventory-entry-alert" title="Quantidade atual abaixo do estoque mÃ­nimo">Baixo estoque</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="vault-entry-tools">
                                            <button
                                                type="button"
                                                class="vault-icon-button"
                                                data-open-inventory-edit-modal
                                                aria-label="Editar item de estoque"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                    <path d="m12 6 4 4"></path>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="vault-entry-delete-button"
                                                data-inventory-delete-entry
                                                data-delete-form-id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>"
                                                aria-label="Excluir item de estoque"
                                            >
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </div>

                                        <form method="post" id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_inventory_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="accounting-wrap panel" id="accounting" data-dashboard-view-panel="accounting"<?= $serverSelectedDashboardView !== 'accounting' ? ' hidden' : '' ?>>
            <div class="panel-header board-header accounting-header">
                <div>
                    <h2>Contabilidade</h2>
                    <p class="accounting-period-label">
                        <span><?= e($accountingPeriodLabel) ?></span>
                        <?php if (!empty($accountingPeriodRangeLabel)): ?>
                            <span class="accounting-period-range"><?= e((string) $accountingPeriodRangeLabel) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="board-summary accounting-board-summary">
                    <?php if (!empty($canManageWorkspace)): ?>
                        <details class="accounting-cycle-settings">
                            <summary class="accounting-cycle-settings-trigger">
                                <span><?= $accountingCycleCloseDay > 0 ? ('Fecha ' . str_pad((string) $accountingCycleCloseDay, 2, '0', STR_PAD_LEFT)) : 'Calend&aacute;rio' ?></span>
                            </summary>
                            <form method="post" class="accounting-cycle-settings-panel">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="workspace_update_accounting_cycle_close_day">
                                <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                <select
                                    name="accounting_cycle_close_day"
                                    class="accounting-installment-select accounting-cycle-settings-select"
                                    aria-label="Dia de fechamento financeiro"
                                >
                                    <option value="0" <?= (int) ($accountingCycleCloseDay ?? 0) === 0 ? 'selected' : '' ?>>Calend&aacute;rio</option>
                                    <?php for ($accountingCloseDayOption = 1; $accountingCloseDayOption <= 28; $accountingCloseDayOption++): ?>
                                        <option
                                            value="<?= e((string) $accountingCloseDayOption) ?>"
                                            <?= $accountingCloseDayOption === (int) ($accountingCycleCloseDay ?? 0) ? 'selected' : '' ?>
                                        >
                                            Fecha <?= e(str_pad((string) $accountingCloseDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" class="btn btn-mini">Salvar</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <form method="get" action="<?= e(appPath('#accounting')) ?>" class="accounting-period-form">
                        <?php if (!empty($accountingShowFutureCarryover)): ?>
                            <input type="hidden" name="accounting_forecast_carry" value="1">
                        <?php endif; ?>
                        <a
                            href="<?= e($accountingPreviousPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o mes anterior"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M9.5 3.5 5 8l4.5 4.5"></path>
                            </svg>
                        </a>
                        <label for="accounting-period-input" class="sr-only">PerÃ­odo de referÃªncia</label>
                        <input
                            type="month"
                            id="accounting-period-input"
                            name="accounting_period"
                            value="<?= e($accountingPeriod) ?>"
                            class="accounting-period-input"
                        >
                        <a
                            href="<?= e($accountingNextPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o prÃ³ximo mÃªs"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M6.5 3.5 11 8l-4.5 4.5"></path>
                            </svg>
                        </a>
                    </form>
                    <?php if (!empty($accountingCanPreviewFutureCarryover)): ?>
                        <a
                            href="<?= e($accountingFutureCarryoverTogglePath) ?>"
                            class="accounting-future-carry-toggle<?= !empty($accountingShowFutureCarryover) ? ' is-active' : '' ?>"
                            aria-pressed="<?= !empty($accountingShowFutureCarryover) ? 'true' : 'false' ?>"
                        ><?= !empty($accountingShowFutureCarryover) ? 'Pendências previstas' : 'Simular pendências' ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php include __DIR__ . '/accounting_sheet.php'; ?>
        </section>

        <?php if (!empty($showUsersDashboardTab)): ?>
            <?php $workspaceTaskStatusConfig = $statusConfig; ?>
            <?php include __DIR__ . '/users_panel.php'; ?>
        <?php endif; ?>
    </section>
</main>

<?php if (!empty($canCreateWorkspace)): ?>
    <div class="modal-backdrop" data-workspace-create-modal hidden>
        <div class="modal-scrim" data-close-workspace-create-modal></div>
        <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="workspace-create-title">
            <header class="modal-head">
                <h2 id="workspace-create-title">Novo workspace</h2>
                <button type="button" class="modal-close-button" data-close-workspace-create-modal aria-label="Fechar modal">
                    <span aria-hidden="true">&#10005;</span>
                </button>
            </header>

            <form method="post" class="form-stack modal-form" data-workspace-create-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="create_workspace">

                <label>
                    <span>Nome do workspace</span>
                    <input type="text" name="workspace_name" maxlength="80" required data-workspace-create-name-input>
                </label>

                <div class="modal-actions">
                    <button type="button" class="btn btn-mini btn-ghost" data-close-workspace-create-modal>Cancelar</button>
                    <button type="submit" class="btn btn-pill">Criar workspace</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>
<div class="modal-backdrop" data-create-modal hidden>
    <div class="modal-scrim" data-close-create-modal></div>
    <section class="modal-card create-task-modal task-create-modal-large" role="dialog" aria-modal="true" aria-labelledby="create-task-title">
        <header class="modal-head">
            <h2 id="create-task-title">Nova tarefa</h2>
            <button type="button" class="modal-close-button" data-close-create-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-create-task-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_task">
            <input type="hidden" name="redirect_task_scope" value="<?= e($taskPageMode === 'project' ? 'project' : 'all') ?>">
            <input type="hidden" name="redirect_task_layout" value="<?= e($taskLayout) ?>">
            <input type="hidden" name="redirect_calendar_month" value="<?= e($taskCalendarMonth) ?>">
            <input type="hidden" name="redirect_group" value="<?= e((string) ($groupFilter ?? '')) ?>">
            <input type="hidden" name="redirect_created_by" value="<?= e((string) ($creatorFilterId ?? '')) ?>">
            <input type="hidden" name="redirect_assignee" value="<?= e((string) ($assigneeFilterId ?? '')) ?>">

            <div class="task-create-modal-body">
                <div class="task-detail-title-group-row">
                <label>
                    <span>T&iacute;tulo</span>
                    <div class="create-task-title-composer" data-create-task-title-composer>
                        <div class="create-task-title-tag-picker" data-create-task-title-tag-picker>
                            <button
                                type="button"
                                class="create-task-title-tag-trigger is-empty"
                                data-create-task-title-tag-trigger
                                aria-haspopup="listbox"
                                aria-expanded="false"
                            >tag</button>
                            <input
                                type="text"
                                maxlength="40"
                                placeholder="Criar tag"
                                autocomplete="off"
                                data-create-task-title-tag-custom
                                hidden
                            >
                            <div class="create-task-title-tag-menu" data-create-task-title-tag-menu hidden></div>
                        </div>
                        <input type="text" name="title" maxlength="140" required data-create-task-title-input>
                    </div>
                    <input type="hidden" name="title_tag" value="" data-create-task-title-tag-input>
                    <input type="hidden" name="title_tag_color" value="<?= e(taskTitleTagDefaultColor()) ?>" data-create-task-title-tag-color-input>
                </label>

                <label>
                    <span>Grupo</span>
                    <div
                        class="task-detail-group-inline-wrap row-inline-picker-wrap"
                        data-inline-select-wrap
                        data-inline-picker-kind="group"
                    >
                        <details class="row-inline-picker group-inline-picker" data-inline-select-picker>
                            <summary aria-label="Grupo da tarefa">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?= $taskGroupsWithAccess ? e((string) $taskGroupsWithAccess[0]) : 'Sem grupo com acesso' ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar grupo">
                                <?php if (!$taskGroupsWithAccess): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option"
                                        data-inline-select-option
                                        data-value=""
                                        data-label="Sem grupo com acesso"
                                        role="option"
                                        aria-selected="true"
                                        disabled
                                    >Sem grupo com acesso</button>
                                <?php else: ?>
                                    <?php foreach ($taskGroupsWithAccess as $groupIndex => $groupNameOption): ?>
                                        <button
                                            type="button"
                                            class="row-inline-picker-option<?= $groupIndex === 0 ? ' is-active' : '' ?>"
                                            data-inline-select-option
                                            data-value="<?= e((string) $groupNameOption) ?>"
                                            data-label="<?= e((string) $groupNameOption) ?>"
                                            role="option"
                                            aria-selected="<?= $groupIndex === 0 ? 'true' : 'false' ?>"
                                        ><?= e((string) $groupNameOption) ?></button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                        <select
                            name="group_name"
                            class="group-tag-select row-inline-picker-native task-group-inline-select"
                            data-inline-select-source
                            data-inline-select-sync-options="group"
                            data-create-task-group-input
                            <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>
                            hidden
                        >
                            <?php if (!$taskGroupsWithAccess): ?>
                                <option value="">Sem grupo com acesso</option>
                            <?php else: ?>
                                <?php foreach ($taskGroupsWithAccess as $groupNameOption): ?>
                                    <option value="<?= e((string) $groupNameOption) ?>">
                                        <?= e((string) $groupNameOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </label>
            </div>

            <div class="task-detail-inline-controls">
                <div class="assignee-picker-wrap task-detail-inline-field task-detail-inline-assignees">
                    <span class="assignee-picker-label">Respons&aacute;veis</span>
                    <details class="assignee-picker task-detail-inline-assignee-picker">
                        <summary>Selecionar</summary>
                        <div class="assignee-picker-menu">
                            <?php if (!$users): ?>
                                <p class="assignee-picker-empty">Nenhum usu&aacute;rio cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <label class="assignee-option">
                                        <input
                                            type="checkbox"
                                            name="assigned_to[]"
                                            value="<?= e((string) $user['id']) ?>"
                                            data-assignee-name="<?= e((string) $user['name']) ?>"
                                            data-assignee-avatar="<?= e(userAvatarImageSrc($user)) ?>"
                                            data-assignee-initial="<?= e(userDisplayInitial((string) $user['name'])) ?>"
                                            <?= (int) $user['id'] === (int) $currentUser['id'] ? 'checked' : '' ?>
                                        >
                                        <?= renderUserAvatar($user, 'avatar small assignee-option-avatar', true, 'span') ?>
                                        <span class="assignee-option-text"><?= e((string) $user['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>

                <div class="task-detail-inline-field task-detail-inline-status">
                    <span>Status</span>
                    <div class="status-stepper task-detail-status-stepper" data-status-stepper>
                        <button
                            type="button"
                            class="status-stepper-btn"
                            data-status-step="-1"
                            aria-label="Status anterior"
                        >
                            <span aria-hidden="true">&#8249;</span>
                        </button>

                        <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                            <details
                                class="row-inline-picker status-inline-picker status-<?= e($defaultTaskStatusKind) ?>"
                                data-inline-select-picker
                                data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                style="<?= e($defaultTaskStatusCssVars) ?>"
                            >
                                <summary aria-label="Status da tarefa">
                                    <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($defaultTaskStatusLabel) ?></span>
                                </summary>
                                <div
                                    class="assignee-picker-menu row-inline-picker-menu"
                                    role="listbox"
                                    aria-label="Selecionar status"
                                >
                                    <?php foreach ($statusOptions as $key => $label): ?>
                                        <?php
                                        $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                        $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                        $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                        $optionColor = normalizeTaskStatusColor(
                                            (string) ($optionMeta['color'] ?? ''),
                                            $optionKind
                                        );
                                        $optionCssVars = (string) ($optionMeta['css_vars'] ?? taskStatusCssVars($optionColor));
                                        ?>
                                        <button
                                            type="button"
                                            class="row-inline-picker-option status-<?= e($optionKind) ?><?= $key === $defaultTaskStatusKey ? ' is-active' : '' ?>"
                                            data-inline-select-option
                                            data-value="<?= e($key) ?>"
                                            data-label="<?= e($label) ?>"
                                            data-status-kind="<?= e($optionKind) ?>"
                                            data-status-color="<?= e($optionColor) ?>"
                                            data-status-order="<?= e((string) $optionOrder) ?>"
                                            style="<?= e($optionCssVars) ?>"
                                            role="option"
                                            aria-selected="<?= $key === $defaultTaskStatusKey ? 'true' : 'false' ?>"
                                        ><?= e($label) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <select
                                name="status"
                                class="tag-select status-select status-<?= e($defaultTaskStatusKind) ?> row-inline-picker-native"
                                data-inline-select-source
                                data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                style="<?= e($defaultTaskStatusCssVars) ?>"
                                aria-label="Status"
                                hidden
                            >
                                <?php foreach ($statusOptions as $key => $label): ?>
                                    <?php
                                    $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                    $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                    $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                    $optionColor = normalizeTaskStatusColor(
                                        (string) ($optionMeta['color'] ?? ''),
                                        $optionKind
                                    );
                                    ?>
                                    <option
                                        value="<?= e($key) ?>"
                                        data-status-kind="<?= e($optionKind) ?>"
                                        data-status-color="<?= e($optionColor) ?>"
                                        data-status-order="<?= e((string) $optionOrder) ?>"
                                        <?= $key === $defaultTaskStatusKey ? ' selected' : '' ?>
                                    ><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="status-stepper-btn"
                            data-status-step="1"
                            aria-label="Pr&oacute;ximo status"
                        >
                            <span aria-hidden="true">&#8250;</span>
                        </button>
                    </div>
                </div>

                <div class="task-detail-inline-field task-detail-inline-priority">
                    <span>Prioridade</span>
                    <div
                        class="tag-field tag-field-priority row-inline-picker-wrap"
                        data-inline-select-wrap
                        data-inline-picker-kind="priority"
                    >
                        <details
                            class="row-inline-picker priority-inline-picker priority-medium"
                            data-inline-select-picker
                        >
                            <summary aria-label="Prioridade da tarefa">
                                <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                <span class="row-inline-picker-summary-text sr-only" data-inline-select-text>Media</span>
                            </summary>
                            <div
                                class="assignee-picker-menu row-inline-picker-menu"
                                role="listbox"
                                aria-label="Selecionar prioridade"
                            >
                                <?php foreach ($priorityOptions as $key => $label): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option priority-<?= e($key) ?><?= $key === 'medium' ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e($key) ?>"
                                        data-label="<?= e($label) ?>"
                                        role="option"
                                        aria-selected="<?= $key === 'medium' ? 'true' : 'false' ?>"
                                    >
                                        <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                        <span><?= e($label) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select
                            name="priority"
                            class="tag-select priority-select priority-medium row-inline-picker-native"
                            data-inline-select-source
                            aria-label="Prioridade"
                            hidden
                        >
                            <?php foreach ($priorityOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>>&#9873;</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="task-detail-inline-field task-detail-inline-due">
                    <span>Prazo</span>
                    <input type="date" name="due_date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">
                </label>
            </div>

            <div class="task-detail-edit-main-row">
                <div class="task-detail-edit-description-field">
                    <span>Descri&ccedil;&atilde;o</span>
                    <div class="task-detail-edit-description-wrap task-create-description-wrap" data-create-task-description-wrap>
                        <div class="task-detail-edit-description-toolbar task-create-description-toolbar" data-create-task-description-toolbar>
                            <button
                                type="button"
                                data-create-task-description-format="bold"
                                title="Negrito"
                                aria-label="Negrito"
                            ><strong aria-hidden="true">B</strong></button>
                            <button
                                type="button"
                                data-create-task-description-format="italic"
                                title="It&aacute;lico"
                                aria-label="It&aacute;lico"
                            ><em aria-hidden="true">I</em></button>
                            <button
                                type="button"
                                data-create-task-description-action="divider"
                                title="Inserir separador"
                                aria-label="Inserir separador"
                            >
                                <span class="task-description-toolbar-divider-icon" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div
                            class="task-detail-edit-description-editor task-create-description-editor"
                            data-create-task-description-editor
                            contenteditable="true"
                            data-description-maxlength="<?= e((string) taskDescriptionMaxLength()) ?>"
                            role="textbox"
                            aria-multiline="true"
                            aria-label="Descri&ccedil;&atilde;o da tarefa"
                        ></div>
                    </div>
                    <textarea name="description" rows="5" maxlength="<?= e((string) taskDescriptionMaxLength()) ?>" data-create-task-description hidden></textarea>
                </div>

                <div class="task-detail-edit-images-field">
                    <span>M&iacute;dias</span>
                    <div class="task-detail-edit-image-picker-actions">
                        <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-create-task-image-add>
                            <span class="task-image-add-button-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <rect x="2.5" y="4" width="15" height="12" rx="2.2"></rect>
                                    <circle cx="7.2" cy="8.1" r="1.4"></circle>
                                    <path d="M3.8 14.8 8.3 10.2l3 2.7 2.1-1.8 2.8 3.7"></path>
                                </svg>
                            </span>
                            <span class="task-image-add-button-label">Adicionar m&iacute;dia</span>
                        </button>
                        <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-create-task-drive-add>
                            <span class="task-image-add-button-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <path d="M7.2 3.4h5.6l4.1 7.2-2.8 4.9H5.9l-2.8-4.9 4.1-7.2Z"></path>
                                    <path d="M7.2 3.4 11.3 10.6"></path>
                                    <path d="M16.9 10.6H8.6"></path>
                                </svg>
                            </span>
                            <span class="task-image-add-button-label">Google Drive</span>
                        </button>
                    </div>
                    <div class="task-detail-edit-image-picker" data-create-task-image-picker tabindex="0" aria-label="Adicionar m&iacute;dias de refer&ecirc;ncia">
                        <input type="file" accept="image/*" multiple data-create-task-image-input hidden>
                        <div class="task-detail-edit-image-list" data-create-task-image-list></div>
                    </div>
                    <textarea name="reference_images_json" rows="1" data-create-task-images hidden></textarea>
                    <input type="hidden" value="{}" data-create-task-review-file data-task-review-file-json>
                </div>
            </div>

            <div class="task-detail-links-steps-row">
            <section class="task-inline-list-editor task-inline-list-editor--links">
                <div class="task-inline-list-head">
                    <span>Links de refer&ecirc;ncia</span>
                    <button type="button" class="btn btn-mini btn-ghost task-inline-list-add-trigger" data-create-task-link-toggle-add>+&nbsp;Link</button>
                </div>
                <div class="task-inline-add-row" data-create-task-link-add-form hidden>
                    <input
                        type="url"
                        maxlength="260"
                        placeholder="https://site.com"
                        data-create-task-link-input
                    >
                    <button type="button" class="task-inline-action task-inline-action-confirm" data-create-task-link-confirm aria-label="Confirmar link">&#10003;</button>
                    <button type="button" class="task-inline-action task-inline-action-cancel" data-create-task-link-cancel aria-label="Cancelar link">X</button>
                </div>
                <div class="task-reference-edit-list" data-create-task-links-list></div>
                <textarea
                    name="reference_links_json"
                    rows="1"
                    class="task-detail-reference-input"
                    data-create-task-links
                    hidden
                ></textarea>
            </section>

            <section class="task-inline-list-editor task-subtasks-editor">
                <div class="task-inline-list-head task-subtasks-editor-head">
                    <div class="task-subtasks-editor-title">
                        <span>Etapas</span>
                        <label class="task-subtasks-dependency-toggle" title="Ativar sequ&ecirc;ncia entre etapas">
                            <input
                                type="checkbox"
                                aria-label="Ativar sequ&ecirc;ncia entre etapas"
                                data-create-task-subtasks-dependency-toggle
                            >
                            <span class="task-subtasks-dependency-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <rect x="5.2" y="2.2" width="9.6" height="3.1" rx="1.1"></rect>
                                    <rect x="5.2" y="8.5" width="9.6" height="3.1" rx="1.1"></rect>
                                    <rect x="5.2" y="14.7" width="9.6" height="3.1" rx="1.1"></rect>
                                    <path d="M10 5.8v1.7"></path>
                                    <path d="M10 12.1v1.7"></path>
                                    <path d="M8.9 6.5 10 7.7l1.1-1.2"></path>
                                    <path d="M8.9 12.8 10 14l1.1-1.2"></path>
                                </svg>
                            </span>
                            <span class="sr-only">Ativar sequ&ecirc;ncia entre etapas</span>
                        </label>
                    </div>
                    <button type="button" class="btn btn-mini btn-ghost task-inline-list-add-trigger" data-create-task-subtask-toggle-add>+&nbsp;Etapa</button>
                </div>
                <div class="task-subtasks-edit-add task-inline-add-row" data-create-task-subtask-add-form hidden>
                    <input
                        type="text"
                        maxlength="120"
                        placeholder="Nova etapa"
                        data-create-task-subtask-input
                    >
                    <button type="button" class="task-inline-action task-inline-action-confirm" data-create-task-subtask-add aria-label="Confirmar etapa">&#10003;</button>
                    <button type="button" class="task-inline-action task-inline-action-cancel" data-create-task-subtask-cancel aria-label="Cancelar etapa">X</button>
                </div>
                <div class="task-subtasks-edit-list" data-create-task-subtasks-list></div>
                <textarea name="subtasks_json" rows="1" data-create-task-subtasks hidden></textarea>
                <input type="hidden" name="subtasks_dependency_enabled" value="0" data-create-task-subtasks-dependency>
            </section>

                </div>
            </div>

            <div class="modal-actions">
                <div class="modal-actions-left">
                    <button type="button" class="btn btn-mini btn-ghost" data-create-task-open-media>M&iacute;dias</button>
                    <button type="button" class="btn btn-mini btn-ghost" data-create-task-back-main hidden>Voltar</button>
                </div>
                <button type="submit" class="btn btn-pill" data-create-task-submit <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar tarefa</button>
            </div>
        </form>
    </section>
</div>
<?php include __DIR__ . '/pwa_install_modal.php'; ?>

<?php if (!empty($canManageWorkspace)): ?>
    <?php
    $createGroupPermissionRows = [];
    $createGroupEnabledCount = 0;
    $createGroupCurrentUserId = (int) ($currentUser['id'] ?? 0);
    foreach ($workspaceMembers as $workspaceMember) {
        $memberId = (int) ($workspaceMember['id'] ?? 0);
        if ($memberId <= 0) {
            continue;
        }
        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
        $memberEnabled = true;
        if ($memberEnabled) {
            $createGroupEnabledCount++;
        }
        $createGroupPermissionRows[] = [
            'id' => $memberId,
            'name' => (string) ($workspaceMember['name'] ?? 'Usuário'),
            'email' => (string) ($workspaceMember['email'] ?? ''),
            'enabled' => $memberEnabled,
            'required' => $memberId === $createGroupCurrentUserId,
            'role_label' => $memberRole === 'admin' ? 'Administrador' : 'Membro',
        ];
    }
    $createGroupTotalCount = count($createGroupPermissionRows);
    $createGroupAllEnabled = $createGroupTotalCount > 0 && $createGroupEnabledCount === $createGroupTotalCount;
    $createGroupCounterLabel = $createGroupEnabledCount . '/' . $createGroupTotalCount;
    ?>
    <div class="modal-backdrop" data-create-group-modal data-group-permissions-modal="task-group-create" hidden>
        <div class="modal-scrim" data-close-create-group-modal></div>
        <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="create-group-title">
            <header class="modal-head">
                <h2 id="create-group-title">Novo projeto</h2>
                <button type="button" class="modal-close-button" data-close-create-group-modal aria-label="Fechar modal">
                    <span aria-hidden="true">&#10005;</span>
                </button>
            </header>

            <form method="post" class="form-stack modal-form" data-create-group-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="create_group">
                <input type="hidden" name="redirect_task_scope" value="<?= e($taskPageMode === 'project' ? 'project' : 'all') ?>">
                <input type="hidden" name="redirect_task_layout" value="<?= e($taskLayout) ?>">
                <input type="hidden" name="redirect_calendar_month" value="<?= e($taskCalendarMonth) ?>">
                <input type="hidden" name="redirect_group" value="<?= e((string) ($groupFilter ?? '')) ?>">

                <label>
                    <span>Nome do projeto</span>
                    <input type="text" name="group_name" maxlength="60" required data-create-group-name-input>
                </label>

                <div class="task-project-visual-fields" data-group-visual-fields>
                    <label class="task-project-color-field">
                        <span>Cor</span>
                        <input type="color" name="group_color" value="#4e82ba" data-group-visual-color-input>
                    </label>
                    <div class="task-project-image-field">
                        <span>Imagem <small>opcional</small></span>
                        <input type="hidden" name="group_image_data_url" value="" data-group-visual-image-value>
                        <div class="task-project-image-field-actions">
                            <button type="button" class="btn btn-mini btn-ghost" data-group-visual-image-choose>Adicionar imagem</button>
                            <button type="button" class="btn btn-mini btn-ghost" data-group-visual-image-remove hidden>Remover</button>
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-group-visual-image-input hidden>
                        </div>
                        <span class="task-project-image-preview" data-group-visual-image-preview hidden></span>
                    </div>
                </div>

                <div class="group-permissions-scope" data-group-permissions-scope>
                    <label class="group-permissions-toggle group-permissions-toggle-master">
                        <input
                            type="checkbox"
                            data-permission-all-checkbox
                            <?= $createGroupAllEnabled ? 'checked' : '' ?>
                            <?= $createGroupTotalCount === 0 ? 'disabled' : '' ?>
                        >
                        <span>Aplicar a todos</span>
                    </label>
                    <span class="group-permissions-counter" data-permission-counter><?= e($createGroupCounterLabel) ?> permitidos</span>
                </div>

                <details class="group-permissions-members">
                    <summary>
                        <span>Acesso do projeto</span>
                        <span class="group-permissions-summary-count" data-permission-summary-count><?= e($createGroupCounterLabel) ?></span>
                    </summary>
                    <div class="group-permissions-list">
                        <?php if (!$createGroupPermissionRows): ?>
                            <p class="group-permissions-empty">Nenhum usu&aacute;rio dispon&iacute;vel para configurar.</p>
                        <?php else: ?>
                            <?php foreach ($createGroupPermissionRows as $createGroupPermissionRow): ?>
                                <div class="group-permissions-row">
                                    <input type="hidden" name="member_ids[]" value="<?= e((string) $createGroupPermissionRow['id']) ?>">
                                    <div class="group-permissions-user">
                                        <strong><?= e((string) $createGroupPermissionRow['name']) ?></strong>
                                        <span><?= e((string) $createGroupPermissionRow['email']) ?></span>
                                        <span><?= e((string) $createGroupPermissionRow['role_label']) ?></span>
                                    </div>
                                    <label class="group-permissions-toggle">
                                        <input
                                            type="checkbox"
                                            name="permissions[<?= e((string) $createGroupPermissionRow['id']) ?>][enabled]"
                                            value="1"
                                            <?= !empty($createGroupPermissionRow['enabled']) ? 'checked' : '' ?>
                                            <?= !empty($createGroupPermissionRow['required']) ? 'disabled' : '' ?>
                                            data-permission-enabled-checkbox
                                        >
                                        <span><?= !empty($createGroupPermissionRow['required']) ? 'Obrigatório' : 'Permitido' ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>

                <div class="modal-actions">
                    <button type="button" class="btn btn-mini btn-ghost" data-close-create-group-modal>Cancelar</button>
                    <button type="submit" class="btn btn-pill">Criar projeto</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php foreach (($taskGroups ?? []) as $taskGroupVisualOption): ?>
    <?php
    $taskGroupVisualName = normalizeTaskGroupName((string) $taskGroupVisualOption);
    $taskGroupVisualPermission = $taskGroupPermissions[$taskGroupVisualName] ?? ['can_access' => false];
    if (empty($taskGroupVisualPermission['can_access'])) {
        continue;
    }
    $taskGroupVisualData = $taskGroupVisuals[$taskGroupVisualName]
        ?? taskGroupVisual($taskGroupVisualName, $currentWorkspaceId ?? null);
    $taskGroupVisualModalKey = 'task-group-visual-' . md5($taskGroupVisualName);
    $taskGroupVisualImage = taskGroupImageSrc($taskGroupVisualData);
    ?>
    <div class="modal-backdrop" data-group-visual-modal="<?= e($taskGroupVisualModalKey) ?>" hidden>
        <div class="modal-scrim" data-close-group-visual-modal></div>
        <section class="modal-card create-group-modal task-group-visual-modal-card" role="dialog" aria-modal="true" aria-labelledby="task-group-visual-title-<?= e(md5($taskGroupVisualName)) ?>">
            <header class="modal-head">
                <h2 id="task-group-visual-title-<?= e(md5($taskGroupVisualName)) ?>">Identidade do projeto</h2>
                <button type="button" class="modal-close-button" data-close-group-visual-modal aria-label="Fechar modal"><span aria-hidden="true">&#10005;</span></button>
            </header>
            <form method="post" class="form-stack modal-form" data-group-visual-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="update_task_group_visual">
                <input type="hidden" name="group_name" value="<?= e($taskGroupVisualName) ?>">
                <input type="hidden" name="redirect_task_scope" value="<?= e($taskPageMode === 'project' ? 'project' : 'all') ?>">
                <input type="hidden" name="redirect_task_layout" value="<?= e($taskLayout) ?>">
                <input type="hidden" name="redirect_calendar_month" value="<?= e($taskCalendarMonth) ?>">
                <input type="hidden" name="redirect_group" value="<?= e((string) ($groupFilter ?? '')) ?>">

                <p class="task-project-visual-modal-project">
                    <?= renderTaskGroupVisual($taskGroupVisualData, 'task-project-visual task-project-visual-modal', 'span') ?>
                    <strong><?= e($taskGroupVisualName) ?></strong>
                </p>
                <div class="task-project-visual-fields" data-group-visual-fields>
                    <label class="task-project-color-field">
                        <span>Cor</span>
                        <input type="color" name="group_color" value="<?= e((string) ($taskGroupVisualData['color'] ?? '#4e82ba')) ?>" data-group-visual-color-input>
                    </label>
                    <div class="task-project-image-field">
                        <span>Imagem <small>opcional</small></span>
                        <input type="hidden" name="group_image_data_url" value="<?= e((string) ($taskGroupVisualData['image_data_url'] ?? '')) ?>" data-group-visual-image-value>
                        <div class="task-project-image-field-actions">
                            <button type="button" class="btn btn-mini btn-ghost" data-group-visual-image-choose>Trocar imagem</button>
                            <button type="button" class="btn btn-mini btn-ghost" data-group-visual-image-remove<?= $taskGroupVisualImage === '' ? ' hidden' : '' ?>>Remover</button>
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-group-visual-image-input hidden>
                        </div>
                        <span class="task-project-image-preview<?= $taskGroupVisualImage === '' ? '' : ' has-image' ?>" data-group-visual-image-preview<?= $taskGroupVisualImage === '' ? ' hidden' : '' ?>><?php if ($taskGroupVisualImage !== ''): ?><img src="<?= e($taskGroupVisualImage) ?>" alt="Imagem do projeto"><?php endif; ?></span>
                    </div>
                </div>
                <p class="form-helper-text">A imagem aparece em miniatura; sem imagem, a cor identifica o projeto.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-mini btn-ghost" data-close-group-visual-modal>Cancelar</button>
                    <button type="submit" class="btn btn-pill">Salvar identidade</button>
                </div>
            </form>
        </section>
    </div>
<?php endforeach; ?>

<div class="modal-backdrop" data-vault-group-modal hidden>
    <div class="modal-scrim" data-close-vault-group-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="vault-group-modal-title">
        <header class="modal-head">
            <h2 id="vault-group-modal-title">Novo grupo do cofre</h2>
            <button type="button" class="modal-close-button" data-close-vault-group-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-group-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_vault_group">

            <label>
                <span>Nome do grupo</span>
                <input type="text" name="group_name" maxlength="60" required data-vault-group-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>
<div class="modal-backdrop" data-vault-entry-modal hidden>
    <div class="modal-scrim" data-close-vault-entry-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="vault-entry-modal-title">
        <header class="modal-head">
            <h2 id="vault-entry-modal-title">Novo dado de acesso</h2>
            <button type="button" class="modal-close-button" data-close-vault-entry-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-entry-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_vault_entry">

            <label>
                <span>Grupo</span>
                <select name="group_name" data-vault-entry-group <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$vaultGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($vaultGroupsWithAccess as $vaultGroupOption): ?>
                            <option value="<?= e((string) $vaultGroupOption) ?>"><?= e((string) $vaultGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-vault-entry-label>
            </label>

            <label>
                <span>Login</span>
                <input type="text" name="login_value" maxlength="220" data-vault-entry-login>
            </label>

            <label>
                <span>Senha</span>
                <input type="text" name="password_value" maxlength="220" data-vault-entry-password>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-entry-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar</button>
            </div>
        </form>
    </section>
</div>
<div class="modal-backdrop" data-vault-entry-edit-modal hidden>
    <div class="modal-scrim" data-close-vault-entry-edit-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="vault-entry-edit-modal-title">
        <header class="modal-head">
            <h2 id="vault-entry-edit-modal-title">Editar dado de acesso</h2>
            <button type="button" class="modal-close-button" data-close-vault-entry-edit-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-entry-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_vault_entry">
            <input type="hidden" name="entry_id" value="" data-vault-entry-edit-id>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-vault-entry-edit-group <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$vaultGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($vaultGroupsWithAccess as $vaultGroupOption): ?>
                            <option value="<?= e((string) $vaultGroupOption) ?>"><?= e((string) $vaultGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-vault-entry-edit-label>
            </label>

            <label>
                <span>Login</span>
                <input type="text" name="login_value" maxlength="220" data-vault-entry-edit-login>
            </label>

            <label>
                <span>Senha</span>
                <input type="text" name="password_value" maxlength="220" data-vault-entry-edit-password>
            </label>
            <p class="vault-entry-edit-password-help">Deixe em branco para manter a senha atual.</p>
            <label class="vault-entry-edit-remove-password">
                <input type="checkbox" name="remove_password" value="1" data-vault-entry-edit-remove-password>
                <span>Remover senha salva</span>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-entry-edit-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>Salvar</button>
            </div>
        </form>
    </section>
</div>


<div class="modal-backdrop" data-inventory-group-modal hidden>
    <div class="modal-scrim" data-close-inventory-group-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-group-modal-title">
        <header class="modal-head">
            <h2 id="inventory-group-modal-title">Novo grupo de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-group-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-group-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_inventory_group">

            <label>
                <span>Nome do grupo</span>
                <input type="text" name="group_name" maxlength="60" required data-inventory-group-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-inventory-entry-modal hidden>
    <div class="modal-scrim" data-close-inventory-entry-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-entry-modal-title">
        <header class="modal-head">
            <h2 id="inventory-entry-modal-title">Novo item de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-entry-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-entry-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_inventory_entry">

            <label>
                <span>Grupo</span>
                <select name="group_name" data-inventory-entry-group <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$inventoryGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($inventoryGroupsWithAccess as $inventoryGroupOption): ?>
                            <option value="<?= e((string) $inventoryGroupOption) ?>"><?= e((string) $inventoryGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Item</span>
                <input type="text" name="label" maxlength="120" required data-inventory-entry-label>
            </label>

            <div class="form-row">
                <label>
                    <span>Quantidade</span>
                    <input type="number" name="quantity_value" min="0" step="1" required data-inventory-entry-quantity>
                </label>
                <label>
                    <span>Unidade</span>
                    <input type="text" name="unit_label" maxlength="30" value="un" required data-inventory-entry-unit>
                </label>
            </div>

            <label>
                <span>Estoque m&iacute;nimo</span>
                <input type="number" name="min_quantity_value" min="0" step="1" data-inventory-entry-min-quantity>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-entry-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-inventory-entry-edit-modal hidden>
    <div class="modal-scrim" data-close-inventory-entry-edit-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-entry-edit-modal-title">
        <header class="modal-head">
            <h2 id="inventory-entry-edit-modal-title">Editar item de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-entry-edit-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-entry-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_inventory_entry">
            <input type="hidden" name="entry_id" value="" data-inventory-entry-edit-id>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-inventory-entry-edit-group <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$inventoryGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($inventoryGroupsWithAccess as $inventoryGroupOption): ?>
                            <option value="<?= e((string) $inventoryGroupOption) ?>"><?= e((string) $inventoryGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Item</span>
                <input type="text" name="label" maxlength="120" required data-inventory-entry-edit-label>
            </label>

            <div class="form-row">
                <label>
                    <span>Quantidade</span>
                    <input type="number" name="quantity_value" min="0" step="1" required data-inventory-entry-edit-quantity>
                </label>
                <label>
                    <span>Unidade</span>
                    <input type="text" name="unit_label" maxlength="30" required data-inventory-entry-edit-unit>
                </label>
            </div>

            <label>
                <span>Estoque m&iacute;nimo</span>
                <input type="number" name="min_quantity_value" min="0" step="1" data-inventory-entry-edit-min-quantity>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-entry-edit-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>Salvar</button>
            </div>
        </form>
    </section>
</div>

<?php if (!empty($canManageWorkspace)): ?>
    <?php foreach ($taskGroups as $taskGroupPermissionsName): ?>
        <?php
        $taskGroupPermissionsModalKey = 'task-group-perm-' . md5((string) $taskGroupPermissionsName);
        $taskPermissionsByUser = $taskGroupPermissionsByUserMap[$taskGroupPermissionsName] ?? [];
        ?>
        <div class="modal-backdrop" data-group-permissions-modal="<?= e($taskGroupPermissionsModalKey) ?>" hidden>
            <div class="modal-scrim" data-close-group-permissions-modal></div>
            <section class="modal-card group-permissions-modal-card" role="dialog" aria-modal="true" aria-labelledby="task-group-perm-title-<?= e(md5((string) $taskGroupPermissionsName)) ?>">
                <header class="modal-head">
                    <h2 id="task-group-perm-title-<?= e(md5((string) $taskGroupPermissionsName)) ?>">Acesso do projeto: <?= e((string) $taskGroupPermissionsName) ?></h2>
                    <button type="button" class="modal-close-button" data-close-group-permissions-modal aria-label="Fechar modal">
                        <span aria-hidden="true">&#10005;</span>
                    </button>
                </header>

                <form method="post" class="form-stack modal-form group-permissions-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_task_group_permissions">
                    <input type="hidden" name="group_name" value="<?= e((string) $taskGroupPermissionsName) ?>">
                    <input type="hidden" name="redirect_task_scope" value="<?= e($taskPageMode === 'project' ? 'project' : 'all') ?>">
                    <input type="hidden" name="redirect_task_layout" value="<?= e($taskLayout) ?>">
                    <input type="hidden" name="redirect_calendar_month" value="<?= e($taskCalendarMonth) ?>">
                    <input type="hidden" name="redirect_group" value="<?= e((string) ($groupFilter ?? '')) ?>">

                    <?php
                    $taskPermissionRows = [];
                    $taskEnabledCount = 0;
                    foreach ($workspaceMembers as $workspaceMember) {
                        $memberId = (int) ($workspaceMember['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                        $memberPermission = $taskPermissionsByUser[$memberId] ?? [];
                        $memberEnabled = (bool) ($memberPermission['can_view'] ?? true)
                            && (bool) ($memberPermission['can_access'] ?? true);
                        $isRequiredMember = $memberId === (int) ($currentUser['id'] ?? 0);
                        if ($isRequiredMember) {
                            $memberEnabled = true;
                        }
                        if ($memberEnabled) {
                            $taskEnabledCount++;
                        }
                        $taskPermissionRows[] = [
                            'id' => $memberId,
                            'name' => (string) ($workspaceMember['name'] ?? 'Usuário'),
                            'email' => (string) ($workspaceMember['email'] ?? ''),
                            'enabled' => $memberEnabled,
                            'required' => $isRequiredMember,
                            'role_label' => $memberRole === 'admin' ? 'Administrador' : 'Membro',
                        ];
                    }
                    $taskTotalCount = count($taskPermissionRows);
                    $taskAllEnabled = $taskTotalCount > 0 && $taskEnabledCount === $taskTotalCount;
                    $taskCounterLabel = $taskEnabledCount . '/' . $taskTotalCount;
                    ?>

                    <div class="group-permissions-scope" data-group-permissions-scope>
                        <label class="group-permissions-toggle group-permissions-toggle-master">
                            <input
                                type="checkbox"
                                data-permission-all-checkbox
                                <?= $taskAllEnabled ? 'checked' : '' ?>
                                <?= $taskTotalCount === 0 ? 'disabled' : '' ?>
                            >
                            <span>Aplicar a todos</span>
                        </label>
                        <span class="group-permissions-counter" data-permission-counter><?= e($taskCounterLabel) ?> permitidos</span>
                    </div>

                    <details class="group-permissions-members" open>
                        <summary>
                            <span>Usu&aacute;rios do workspace</span>
                            <span class="group-permissions-summary-count" data-permission-summary-count><?= e($taskCounterLabel) ?></span>
                        </summary>
                        <div class="group-permissions-list">
                            <?php if (!$taskPermissionRows): ?>
                                <p class="group-permissions-empty">Nenhum usu&aacute;rio dispon&iacute;vel para configurar.</p>
                            <?php else: ?>
                                <?php foreach ($taskPermissionRows as $taskPermissionRow): ?>
                                    <div class="group-permissions-row">
                                        <input type="hidden" name="member_ids[]" value="<?= e((string) $taskPermissionRow['id']) ?>">
                                        <div class="group-permissions-user">
                                            <strong><?= e((string) $taskPermissionRow['name']) ?></strong>
                                            <span><?= e((string) $taskPermissionRow['email']) ?></span>
                                            <span><?= e((string) $taskPermissionRow['role_label']) ?></span>
                                        </div>
                                        <label class="group-permissions-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= e((string) $taskPermissionRow['id']) ?>][enabled]"
                                                value="1"
                                                <?= !empty($taskPermissionRow['enabled']) ? 'checked' : '' ?>
                                                <?= !empty($taskPermissionRow['required']) ? 'disabled' : '' ?>
                                                data-permission-enabled-checkbox
                                            >
                                            <span><?= !empty($taskPermissionRow['required']) ? 'Obrigatório' : 'Permitido' ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-close-group-permissions-modal>Cancelar</button>
                        <button type="submit" class="btn btn-pill">Salvar permiss&otilde;es</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endforeach; ?>

    <?php foreach ($vaultGroups as $vaultGroupPermissionsName): ?>
        <?php
        $vaultGroupPermissionsModalKey = 'vault-group-perm-' . md5((string) $vaultGroupPermissionsName);
        $vaultPermissionsByUser = $vaultGroupPermissionsByUserMap[$vaultGroupPermissionsName] ?? [];
        ?>
        <div class="modal-backdrop" data-group-permissions-modal="<?= e($vaultGroupPermissionsModalKey) ?>" hidden>
            <div class="modal-scrim" data-close-group-permissions-modal></div>
            <section class="modal-card group-permissions-modal-card" role="dialog" aria-modal="true" aria-labelledby="vault-group-perm-title-<?= e(md5((string) $vaultGroupPermissionsName)) ?>">
                <header class="modal-head">
                    <h2 id="vault-group-perm-title-<?= e(md5((string) $vaultGroupPermissionsName)) ?>">Acesso do grupo do cofre: <?= e((string) $vaultGroupPermissionsName) ?></h2>
                    <button type="button" class="modal-close-button" data-close-group-permissions-modal aria-label="Fechar modal">
                        <span aria-hidden="true">&#10005;</span>
                    </button>
                </header>

                <form method="post" class="form-stack modal-form group-permissions-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_vault_group_permissions">
                    <input type="hidden" name="group_name" value="<?= e((string) $vaultGroupPermissionsName) ?>">

                    <?php
                    $vaultPermissionRows = [];
                    $vaultEnabledCount = 0;
                    foreach ($workspaceMembers as $workspaceMember) {
                        $memberId = (int) ($workspaceMember['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                        if ($memberRole === 'admin') {
                            continue;
                        }
                        $memberPermission = $vaultPermissionsByUser[$memberId] ?? [];
                        $memberEnabled = (bool) ($memberPermission['can_view'] ?? true)
                            && (bool) ($memberPermission['can_access'] ?? true);
                        if ($memberEnabled) {
                            $vaultEnabledCount++;
                        }
                        $vaultPermissionRows[] = [
                            'id' => $memberId,
                            'name' => (string) ($workspaceMember['name'] ?? 'Usuário'),
                            'email' => (string) ($workspaceMember['email'] ?? ''),
                            'enabled' => $memberEnabled,
                        ];
                    }
                    $vaultTotalCount = count($vaultPermissionRows);
                    $vaultAllEnabled = $vaultTotalCount > 0 && $vaultEnabledCount === $vaultTotalCount;
                    $vaultCounterLabel = $vaultEnabledCount . '/' . $vaultTotalCount;
                    ?>

                    <div class="group-permissions-scope" data-group-permissions-scope>
                        <label class="group-permissions-toggle group-permissions-toggle-master">
                            <input
                                type="checkbox"
                                data-permission-all-checkbox
                                <?= $vaultAllEnabled ? 'checked' : '' ?>
                                <?= $vaultTotalCount === 0 ? 'disabled' : '' ?>
                            >
                            <span>Aplicar a todos</span>
                        </label>
                        <span class="group-permissions-counter" data-permission-counter><?= e($vaultCounterLabel) ?> permitidos</span>
                    </div>

                    <details class="group-permissions-members" open>
                        <summary>
                            <span>Usu&aacute;rios do workspace</span>
                            <span class="group-permissions-summary-count" data-permission-summary-count><?= e($vaultCounterLabel) ?></span>
                        </summary>
                        <div class="group-permissions-list">
                            <?php if (!$vaultPermissionRows): ?>
                                <p class="group-permissions-empty">Nenhum usu&aacute;rio dispon&iacute;vel para configurar.</p>
                            <?php else: ?>
                                <?php foreach ($vaultPermissionRows as $vaultPermissionRow): ?>
                                    <div class="group-permissions-row">
                                        <input type="hidden" name="member_ids[]" value="<?= e((string) $vaultPermissionRow['id']) ?>">
                                        <div class="group-permissions-user">
                                            <strong><?= e((string) $vaultPermissionRow['name']) ?></strong>
                                            <span><?= e((string) $vaultPermissionRow['email']) ?></span>
                                        </div>
                                        <label class="group-permissions-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= e((string) $vaultPermissionRow['id']) ?>][enabled]"
                                                value="1"
                                                <?= !empty($vaultPermissionRow['enabled']) ? 'checked' : '' ?>
                                                data-permission-enabled-checkbox
                                            >
                                            <span>Permitido</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-close-group-permissions-modal>Cancelar</button>
                        <button type="submit" class="btn btn-pill">Salvar permiss&otilde;es</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<div class="modal-backdrop" data-task-detail-modal hidden>
    <div class="modal-scrim" data-close-task-detail-modal></div>
    <section class="modal-card task-detail-modal" role="dialog" aria-modal="true" aria-labelledby="task-detail-modal-title">
        <header class="modal-head task-detail-modal-head">
            <div class="task-detail-modal-head-copy">
                <h2 id="task-detail-modal-title" data-task-detail-title>Tarefa</h2>
            </div>
            <div class="task-detail-modal-head-actions">
                <button type="button" class="btn btn-mini btn-danger" data-task-detail-delete>Remover</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-edit>Editar</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-request-revision hidden>Solicitar ajuste</button>
                <button type="button" class="btn btn-mini" data-task-detail-save hidden>Salvar</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-cancel-edit hidden>Cancelar</button>
                <button type="button" class="modal-close-button" data-close-task-detail-modal aria-label="Fechar modal">
                    <span aria-hidden="true">&#10005;</span>
                </button>
            </div>
        </header>

        <div class="task-detail-modal-body">
            <section class="task-detail-view" data-task-detail-view>
                <div class="task-detail-view-layout">
                    <div class="task-detail-view-main">
                        <div class="task-detail-view-block">
                            <div class="task-detail-view-tags">
                                <span class="task-detail-view-tag task-title-tag-badge task-detail-title-tag" data-task-detail-view-title-tag hidden></span>
                                <span class="task-detail-view-tag" data-task-detail-view-status></span>
                                <span class="task-detail-view-tag" data-task-detail-view-priority></span>
                                <span class="task-detail-view-tag" data-task-detail-view-group></span>
                                <span class="task-detail-view-tag task-detail-view-tag-due" data-task-detail-view-due></span>
                            </div>
                            <div class="task-detail-view-assignees" data-task-detail-view-assignees></div>
                        </div>

                        <div class="task-detail-view-block">
                            <div class="task-detail-view-label-row">
                                <div class="task-detail-view-label">Descri&ccedil;&atilde;o</div>
                                <button
                                    type="button"
                                    class="task-detail-description-remove"
                                    data-task-detail-remove-revision
                                    hidden
                                    aria-label="Remover &uacute;ltima solicita&ccedil;&atilde;o de ajuste"
                                    title="Remover &uacute;ltima solicita&ccedil;&atilde;o de ajuste"
                                >
                                    <span aria-hidden="true">&#10005;</span>
                                </button>
                            </div>
                            <div class="task-detail-view-description" data-task-detail-view-description></div>
                            <div class="task-detail-description-versions" data-task-detail-view-description-versions hidden></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-subtasks-wrap hidden>
                            <div class="task-detail-view-label">Etapas</div>
                            <div class="task-detail-subtasks-list" data-task-detail-view-subtasks></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-review-file-wrap hidden>
                            <div class="task-detail-view-label-row">
                                <div class="task-detail-view-label">Arquivo para revisao</div>
                                <div class="task-detail-view-inline-actions" data-task-detail-view-review-file-actions hidden>
                                    <button
                                        type="button"
                                        class="btn btn-mini btn-ghost"
                                        data-task-detail-view-review-file-add
                                    >Adicionar arquivo</button>
                                    <button
                                        type="button"
                                        class="btn btn-mini btn-ghost"
                                        data-task-detail-view-review-file-remove
                                        hidden
                                    >Remover</button>
                                </div>
                            </div>
                            <div class="task-detail-review-file" data-task-detail-view-review-file></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-references hidden>
                            <div class="task-detail-view-label">Refer&ecirc;ncias</div>

                            <div class="task-detail-ref-section" data-task-detail-view-images-wrap hidden>
                                <div class="task-detail-ref-head">
                                    <div class="task-detail-ref-title">M&iacute;dias</div>
                                    <button
                                        type="button"
                                        class="btn btn-mini btn-ghost task-detail-ref-bulk-download"
                                        data-task-detail-download-all
                                        hidden
                                    >Baixar todas</button>
                                </div>
                                <div class="task-detail-ref-images" data-task-detail-view-images></div>
                            </div>

                            <div class="task-detail-ref-section" data-task-detail-view-links-wrap hidden>
                                <div class="task-detail-ref-title">Links</div>
                                <div class="task-detail-ref-links" data-task-detail-view-links></div>
                            </div>
                        </div>

                        <div class="task-detail-view-meta">
                            <span data-task-detail-view-created-by></span>
                            <span data-task-detail-view-updated-at></span>
                        </div>
                    </div>

                    <details class="task-detail-history-column" data-task-detail-history-column>
                        <summary class="task-detail-history-summary" aria-label="Hist&oacute;rico da tarefa" title="Hist&oacute;rico">
                            <span class="task-detail-history-summary-glyph" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <path d="M3.5 5.5V2.9"></path>
                                    <path d="M3.5 2.9h2.6"></path>
                                    <path d="M4 8.1a6.4 6.4 0 1 1 2 6.9"></path>
                                    <path d="M10 6.4v3.9l2.7 1.7"></path>
                                </svg>
                            </span>
                            <span class="task-detail-history-summary-title" aria-hidden="true">Hist&oacute;rico</span>
                        </summary>
                        <div class="task-detail-history-list" data-task-detail-view-history></div>
                    </details>
                </div>
            </section>

            <section class="task-detail-edit" data-task-detail-edit-panel hidden>
                <div class="form-stack modal-form">
                    <div class="task-detail-title-group-row">
                        <label>
                            <span>T&iacute;tulo</span>
                            <div class="create-task-title-composer" data-task-detail-edit-title-composer>
                                <div class="create-task-title-tag-picker" data-task-detail-edit-title-tag-picker>
                                    <button
                                        type="button"
                                        class="create-task-title-tag-trigger is-empty"
                                        data-task-detail-edit-title-tag-trigger
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                    >tag</button>
                                    <input
                                        type="text"
                                        maxlength="40"
                                        placeholder="Criar tag"
                                        autocomplete="off"
                                        data-task-detail-edit-title-tag-custom
                                        hidden
                                    >
                                    <div class="create-task-title-tag-menu" data-task-detail-edit-title-tag-menu hidden></div>
                                </div>
                                <input type="text" maxlength="140" required data-task-detail-edit-title>
                            </div>
                            <input type="hidden" name="title_tag" value="" data-task-detail-edit-title-tag-input>
                            <input type="hidden" name="title_tag_color" value="<?= e(taskTitleTagDefaultColor()) ?>" data-task-detail-edit-title-tag-color-input>
                        </label>

                        <label>
                            <span>Grupo</span>
                            <div
                                class="task-detail-group-inline-wrap row-inline-picker-wrap"
                                data-inline-select-wrap
                                data-inline-picker-kind="group"
                            >
                                <details class="row-inline-picker group-inline-picker" data-inline-select-picker>
                                    <summary aria-label="Grupo da tarefa">
                                        <span class="row-inline-picker-summary-text" data-inline-select-text>
                                            <?= $taskGroupsWithAccess ? e((string) $taskGroupsWithAccess[0]) : 'Sem grupo com acesso' ?>
                                        </span>
                                    </summary>
                                    <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar grupo">
                                        <?php if (!$taskGroupsWithAccess): ?>
                                            <button
                                                type="button"
                                                class="row-inline-picker-option"
                                                data-inline-select-option
                                                data-value=""
                                                data-label="Sem grupo com acesso"
                                                role="option"
                                                aria-selected="true"
                                                disabled
                                            >Sem grupo com acesso</button>
                                        <?php else: ?>
                                            <?php foreach ($taskGroupsWithAccess as $groupIndex => $groupNameOption): ?>
                                                <button
                                                    type="button"
                                                    class="row-inline-picker-option<?= $groupIndex === 0 ? ' is-active' : '' ?>"
                                                    data-inline-select-option
                                                    data-value="<?= e((string) $groupNameOption) ?>"
                                                    data-label="<?= e((string) $groupNameOption) ?>"
                                                    role="option"
                                                    aria-selected="<?= $groupIndex === 0 ? 'true' : 'false' ?>"
                                                ><?= e((string) $groupNameOption) ?></button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </details>
                                <select
                                    class="group-tag-select row-inline-picker-native task-group-inline-select"
                                    data-inline-select-source
                                    data-inline-select-sync-options="group"
                                    data-task-detail-edit-group
                                    hidden
                                >
                                    <?php if (!$taskGroupsWithAccess): ?>
                                        <option value="">Sem grupo com acesso</option>
                                    <?php else: ?>
                                        <?php foreach ($taskGroupsWithAccess as $groupNameOption): ?>
                                            <option value="<?= e((string) $groupNameOption) ?>">
                                                <?= e((string) $groupNameOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </label>
                    </div>

                    <div class="task-detail-inline-controls">
                        <div class="assignee-picker-wrap task-detail-inline-field task-detail-inline-assignees">
                            <span class="assignee-picker-label">Respons&aacute;veis</span>
                            <details class="assignee-picker task-detail-inline-assignee-picker" data-task-detail-edit-assignees>
                                <summary>Selecionar</summary>
                                <div class="assignee-picker-menu" data-task-detail-edit-assignees-menu></div>
                            </details>
                        </div>

                        <div class="task-detail-inline-field task-detail-inline-status">
                            <span>Status</span>
                            <div class="status-stepper task-detail-status-stepper" data-status-stepper>
                                <button
                                    type="button"
                                    class="status-stepper-btn"
                                    data-status-step="-1"
                                    aria-label="Status anterior"
                                >
                                    <span aria-hidden="true">&#8249;</span>
                                </button>

                                <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                                    <details
                                        class="row-inline-picker status-inline-picker status-<?= e($defaultTaskStatusKind) ?>"
                                        data-inline-select-picker
                                        data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                        style="<?= e($defaultTaskStatusCssVars) ?>"
                                    >
                                        <summary aria-label="Status da tarefa">
                                            <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($defaultTaskStatusLabel) ?></span>
                                        </summary>
                                        <div
                                            class="assignee-picker-menu row-inline-picker-menu"
                                            role="listbox"
                                            aria-label="Selecionar status"
                                        >
                                            <?php foreach ($statusOptions as $key => $label): ?>
                                                <?php
                                                $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                                $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                                $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                                $optionColor = normalizeTaskStatusColor(
                                                    (string) ($optionMeta['color'] ?? ''),
                                                    $optionKind
                                                );
                                                $optionCssVars = (string) ($optionMeta['css_vars'] ?? taskStatusCssVars($optionColor));
                                                ?>
                                                <button
                                                    type="button"
                                                    class="row-inline-picker-option status-<?= e($optionKind) ?><?= $key === $defaultTaskStatusKey ? ' is-active' : '' ?>"
                                                    data-inline-select-option
                                                    data-value="<?= e($key) ?>"
                                                    data-label="<?= e($label) ?>"
                                                    data-status-kind="<?= e($optionKind) ?>"
                                                    data-status-color="<?= e($optionColor) ?>"
                                                    data-status-order="<?= e((string) $optionOrder) ?>"
                                                    style="<?= e($optionCssVars) ?>"
                                                    role="option"
                                                    aria-selected="<?= $key === $defaultTaskStatusKey ? 'true' : 'false' ?>"
                                                ><?= e($label) ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <select
                                        class="tag-select status-select status-<?= e($defaultTaskStatusKind) ?> row-inline-picker-native"
                                        data-inline-select-source
                                        data-task-detail-edit-status
                                        data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                        style="<?= e($defaultTaskStatusCssVars) ?>"
                                        aria-label="Status"
                                        hidden
                                    >
                                        <?php foreach ($statusOptions as $key => $label): ?>
                                            <?php
                                            $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                            $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                            $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                            $optionColor = normalizeTaskStatusColor(
                                                (string) ($optionMeta['color'] ?? ''),
                                                $optionKind
                                            );
                                            ?>
                                            <option
                                                value="<?= e($key) ?>"
                                                data-status-kind="<?= e($optionKind) ?>"
                                                data-status-color="<?= e($optionColor) ?>"
                                                data-status-order="<?= e((string) $optionOrder) ?>"
                                                <?= $key === $defaultTaskStatusKey ? ' selected' : '' ?>
                                            ><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    class="status-stepper-btn"
                                    data-status-step="1"
                                    aria-label="Pr&oacute;ximo status"
                                >
                                    <span aria-hidden="true">&#8250;</span>
                                </button>
                            </div>
                        </div>

                        <div class="task-detail-inline-field task-detail-inline-priority">
                            <span>Prioridade</span>
                            <div
                                class="tag-field tag-field-priority row-inline-picker-wrap"
                                data-inline-select-wrap
                                data-inline-picker-kind="priority"
                            >
                                <details
                                    class="row-inline-picker priority-inline-picker priority-medium"
                                    data-inline-select-picker
                                >
                                    <summary aria-label="Prioridade da tarefa">
                                        <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                        <span class="row-inline-picker-summary-text sr-only" data-inline-select-text>Media</span>
                                    </summary>
                                    <div
                                        class="assignee-picker-menu row-inline-picker-menu"
                                        role="listbox"
                                        aria-label="Selecionar prioridade"
                                    >
                                        <?php foreach ($priorityOptions as $key => $label): ?>
                                            <button
                                                type="button"
                                                class="row-inline-picker-option priority-<?= e($key) ?><?= $key === 'medium' ? ' is-active' : '' ?>"
                                                data-inline-select-option
                                                data-value="<?= e($key) ?>"
                                                data-label="<?= e($label) ?>"
                                                role="option"
                                                aria-selected="<?= $key === 'medium' ? 'true' : 'false' ?>"
                                            >
                                                <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                                <span><?= e($label) ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <select
                                    class="tag-select priority-select priority-medium row-inline-picker-native"
                                    data-inline-select-source
                                    data-task-detail-edit-priority
                                    aria-label="Prioridade"
                                    hidden
                                >
                                    <?php foreach ($priorityOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>>&#9873;</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <label class="task-detail-inline-field task-detail-inline-due">
                            <span>Prazo</span>
                            <input type="date" data-task-detail-edit-due-date>
                        </label>
                    </div>

                    <div class="task-detail-edit-main-row">
                        <div class="task-detail-edit-description-field">
                            <span>Descri&ccedil;&atilde;o</span>
                            <div class="task-detail-edit-description-wrap" data-task-detail-edit-description-wrap>
                                <div class="task-detail-edit-description-toolbar" data-task-detail-edit-description-toolbar>
                                    <button
                                        type="button"
                                        data-task-detail-description-format="bold"
                                        title="Negrito"
                                        aria-label="Negrito"
                                    ><strong aria-hidden="true">B</strong></button>
                                    <button
                                        type="button"
                                        data-task-detail-description-format="italic"
                                        title="It&aacute;lico"
                                        aria-label="It&aacute;lico"
                                    ><em aria-hidden="true">I</em></button>
                                    <button
                                        type="button"
                                        data-task-detail-description-action="divider"
                                        title="Inserir separador"
                                        aria-label="Inserir separador"
                                    >
                                        <span class="task-description-toolbar-divider-icon" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div
                                    class="task-detail-edit-description-editor"
                                    data-task-detail-edit-description-editor
                                    contenteditable="true"
                                    data-description-maxlength="<?= e((string) taskDescriptionMaxLength()) ?>"
                                    role="textbox"
                                    aria-multiline="true"
                                    aria-label="Descri&ccedil;&atilde;o da tarefa"
                                ></div>
                            </div>
                            <textarea rows="5" maxlength="<?= e((string) taskDescriptionMaxLength()) ?>" data-task-detail-edit-description hidden></textarea>
                        </div>

                        <div class="task-detail-edit-images-field">
                            <span>M&iacute;dias</span>
                            <div class="task-detail-edit-image-picker-actions">
                                <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-task-detail-image-add>
                                    <span class="task-image-add-button-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false">
                                            <rect x="2.5" y="4" width="15" height="12" rx="2.2"></rect>
                                            <circle cx="7.2" cy="8.1" r="1.4"></circle>
                                            <path d="M3.8 14.8 8.3 10.2l3 2.7 2.1-1.8 2.8 3.7"></path>
                                        </svg>
                                    </span>
                                    <span class="task-image-add-button-label">Adicionar m&iacute;dia</span>
                                </button>
                                <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-task-detail-drive-add>
                                    <span class="task-image-add-button-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false">
                                            <path d="M7.2 3.4h5.6l4.1 7.2-2.8 4.9H5.9l-2.8-4.9 4.1-7.2Z"></path>
                                            <path d="M7.2 3.4 11.3 10.6"></path>
                                            <path d="M16.9 10.6H8.6"></path>
                                        </svg>
                                    </span>
                                    <span class="task-image-add-button-label">Google Drive</span>
                                </button>
                            </div>
                            <div class="task-detail-edit-image-picker" data-task-detail-image-picker tabindex="0" aria-label="Adicionar m&iacute;dias de refer&ecirc;ncia">
                                <input type="file" accept="image/*" multiple data-task-detail-image-input hidden>
                                <div class="task-detail-edit-image-list" data-task-detail-image-list></div>
                            </div>
                            <textarea rows="1" data-task-detail-edit-images hidden></textarea>
                        </div>

                        <div class="task-detail-edit-review-file-field" data-task-detail-edit-review-file-wrap>
                            <div class="task-detail-edit-review-file-head">
                                <span>Arquivo para revis&atilde;o</span>
                                <div class="task-detail-edit-review-file-actions" data-task-detail-edit-review-file-actions>
                                    <button
                                        type="button"
                                        class="btn btn-mini btn-ghost task-image-add-button"
                                        data-task-detail-review-file-add
                                    >
                                        <span class="task-image-add-button-label">Adicionar arquivo</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-mini btn-ghost"
                                        data-task-detail-review-file-remove
                                        hidden
                                    >Remover</button>
                                </div>
                            </div>
                            <p class="task-detail-edit-review-file-note" data-task-detail-edit-review-file-note hidden></p>
                            <div class="task-detail-review-file task-detail-edit-review-file" data-task-detail-edit-review-file></div>
                        </div>
                    </div>

                    <div class="task-detail-links-steps-row">
                    <section class="task-inline-list-editor task-subtasks-editor">
                        <div class="task-inline-list-head task-subtasks-editor-head">
                            <div class="task-subtasks-editor-title">
                                <span>Etapas</span>
                                <label class="task-subtasks-dependency-toggle" title="Ativar sequ&ecirc;ncia entre etapas">
                                    <input
                                        type="checkbox"
                                        aria-label="Ativar sequ&ecirc;ncia entre etapas"
                                        data-task-detail-edit-subtasks-dependency-toggle
                                    >
                                    <span class="task-subtasks-dependency-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false">
                                            <rect x="5.2" y="2.2" width="9.6" height="3.1" rx="1.1"></rect>
                                            <rect x="5.2" y="8.5" width="9.6" height="3.1" rx="1.1"></rect>
                                            <rect x="5.2" y="14.7" width="9.6" height="3.1" rx="1.1"></rect>
                                            <path d="M10 5.8v1.7"></path>
                                            <path d="M10 12.1v1.7"></path>
                                            <path d="M8.9 6.5 10 7.7l1.1-1.2"></path>
                                            <path d="M8.9 12.8 10 14l1.1-1.2"></path>
                                        </svg>
                                    </span>
                                    <span class="sr-only">Ativar sequ&ecirc;ncia entre etapas</span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-mini btn-ghost task-inline-list-add-trigger" data-task-detail-edit-subtask-toggle-add>+&nbsp;Etapa</button>
                        </div>
                        <div class="task-subtasks-edit-add task-inline-add-row" data-task-detail-edit-subtask-add-form hidden>
                            <input
                                type="text"
                                maxlength="120"
                                placeholder="Nova etapa"
                                data-task-detail-edit-subtask-input
                            >
                            <button type="button" class="task-inline-action task-inline-action-confirm" data-task-detail-edit-subtask-add aria-label="Confirmar etapa">&#10003;</button>
                            <button type="button" class="task-inline-action task-inline-action-cancel" data-task-detail-edit-subtask-cancel aria-label="Cancelar etapa">X</button>
                        </div>
                        <div class="task-subtasks-edit-list" data-task-detail-edit-subtasks-list></div>
                        <textarea rows="1" data-task-detail-edit-subtasks hidden></textarea>
                        <input type="hidden" value="0" data-task-detail-edit-subtasks-dependency>
                    </section>

                    <section class="task-inline-list-editor task-inline-list-editor--links">
                        <div class="task-inline-list-head">
                            <span>Links de refer&ecirc;ncia</span>
                            <button type="button" class="btn btn-mini btn-ghost task-inline-list-add-trigger" data-task-detail-edit-link-toggle-add>+&nbsp;Link</button>
                        </div>
                        <div class="task-inline-add-row" data-task-detail-edit-link-add-form hidden>
                            <input
                                type="url"
                                maxlength="260"
                                placeholder="https://site.com"
                                data-task-detail-edit-link-input
                            >
                            <button type="button" class="task-inline-action task-inline-action-confirm" data-task-detail-edit-link-confirm aria-label="Confirmar link">&#10003;</button>
                            <button type="button" class="task-inline-action task-inline-action-cancel" data-task-detail-edit-link-cancel aria-label="Cancelar link">X</button>
                        </div>
                        <div class="task-reference-edit-list" data-task-detail-edit-links-list></div>
                        <textarea
                            rows="1"
                            class="task-detail-reference-input"
                            data-task-detail-edit-links
                            hidden
                        ></textarea>
                    </section>
                    </div>
                    <div class="task-detail-edit-media-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-task-detail-open-media>M&iacute;dias</button>
                        <button type="button" class="btn btn-mini btn-ghost" data-task-detail-back-main hidden>Voltar</button>
                    </div>
                </div>
            </section>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-task-review-modal hidden>
    <div class="modal-scrim" data-close-task-review-modal></div>
    <section class="modal-card review-task-modal" role="dialog" aria-modal="true" aria-labelledby="task-review-modal-title">
        <header class="modal-head">
            <h2 id="task-review-modal-title">Solicitar ajuste</h2>
            <button type="button" class="modal-close-button" data-close-task-review-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-task-review-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="request_task_revision">
            <input type="hidden" name="task_id" value="" data-task-review-task-id>

            <label>
                <span>Nova descri&ccedil;&atilde;o para ajustes</span>
                <textarea name="revision_description" rows="6" maxlength="8000" required data-task-review-description></textarea>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-task-review-modal>Cancelar</button>
                <button type="submit" class="btn btn-mini" data-task-review-submit>Salvar ajuste</button>
            </div>
        </form>
    </section>
</div>

<form method="post" data-task-remove-revision-form hidden>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="remove_task_revision">
    <input type="hidden" name="task_id" value="" data-task-remove-revision-task-id>
</form>

<div class="modal-backdrop task-image-preview-modal" data-task-image-preview-modal hidden>
    <div class="modal-scrim" data-close-task-image-preview></div>
    <section class="modal-card task-image-preview-card" role="dialog" aria-modal="true" aria-label="M&iacute;dia de refer&ecirc;ncia">
        <header class="modal-head task-image-preview-head">
            <div class="task-image-preview-title" data-task-image-preview-title></div>
            <a
                class="task-image-preview-download"
                data-task-image-preview-download
                href="#"
                download
                target="_blank"
                rel="noreferrer noopener"
                aria-label="Baixar midia"
                hidden
            >
                <span aria-hidden="true">&#8681;</span>
                <span>Baixar</span>
            </a>
            <button type="button" class="modal-close-button" data-close-task-image-preview aria-label="Fechar visualiza&ccedil;&atilde;o da m&iacute;dia">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>
        <div class="task-image-preview-body">
            <div class="task-image-preview-nav-slot">
                <button
                    type="button"
                    class="task-image-preview-nav task-image-preview-nav-prev"
                    data-task-image-preview-prev
                    aria-label="M&iacute;dia anterior"
                    hidden
                >
                    <span aria-hidden="true">&#8249;</span>
                </button>
            </div>
            <div class="task-image-preview-viewport">
                <img src="" alt="Imagem de refer&ecirc;ncia ampliada" data-task-image-preview-img>
                <video controls playsinline preload="metadata" hidden data-task-image-preview-video></video>
            </div>
            <div class="task-image-preview-nav-slot">
                <button
                    type="button"
                    class="task-image-preview-nav task-image-preview-nav-next"
                    data-task-image-preview-next
                    aria-label="Pr&oacute;xima m&iacute;dia"
                    hidden
                >
                    <span aria-hidden="true">&#8250;</span>
                </button>
            </div>
        </div>
    </section>
</div>

<div class="modal-backdrop google-drive-browser-modal-backdrop" data-google-drive-browser-modal hidden>
    <div class="modal-scrim" data-close-google-drive-browser></div>
    <section class="modal-card google-drive-browser-modal" role="dialog" aria-modal="true" aria-labelledby="google-drive-browser-title">
        <header class="modal-head google-drive-browser-modal-head">
            <div class="google-drive-browser-head-copy">
                <h2 id="google-drive-browser-title">Google Drive</h2>
                <p>Escolha m&iacute;dias por pasta, com navega&ccedil;&atilde;o hier&aacute;rquica dentro do Bexon.</p>
            </div>
            <button type="button" class="modal-close-button" data-close-google-drive-browser aria-label="Fechar navegador do Google Drive">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <div class="google-drive-browser-modal-body">
            <section class="google-drive-browser-roots" data-google-drive-browser-roots>
                <button type="button" class="google-drive-browser-root-card" data-google-drive-browser-root="my_drive">
                    <span class="google-drive-browser-root-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M10.2 4.4h8.1l3.2 5.3-2.6 4.4H7.2L4.5 9.7l3.2-5.3Z"></path>
                            <path d="M10.2 4.4 13.5 9.7"></path>
                            <path d="M21.5 9.7h-8"></path>
                        </svg>
                    </span>
                    <span class="google-drive-browser-root-copy">
                        <strong>Meu Drive</strong>
                        <span>Arquivos e pastas do pr&oacute;prio usu&aacute;rio.</span>
                    </span>
                </button>

                <button type="button" class="google-drive-browser-root-card" data-google-drive-browser-root="shared_with_me">
                    <span class="google-drive-browser-root-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M8.2 11.3a3.3 3.3 0 1 1 0-6.6 3.3 3.3 0 0 1 0 6.6Z"></path>
                            <path d="M16.9 12.8a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4Z"></path>
                            <path d="M4.1 18.7c.5-2.9 2.5-4.3 6-4.3s5.5 1.4 6 4.3"></path>
                            <path d="M14.6 18.7c.4-1.9 1.8-2.9 4.1-2.9 1 0 1.8.2 2.6.6"></path>
                        </svg>
                    </span>
                    <span class="google-drive-browser-root-copy">
                        <strong>Compartilhados comigo</strong>
                        <span>Itens recebidos de outras pessoas, separados da raiz pessoal.</span>
                    </span>
                </button>
            </section>

            <section class="google-drive-browser-explorer" data-google-drive-browser-explorer hidden>
                <div class="google-drive-browser-toolbar">
                    <button type="button" class="btn btn-mini btn-ghost" data-google-drive-browser-back-root>Raizes</button>
                    <div class="google-drive-browser-breadcrumbs" data-google-drive-browser-breadcrumbs></div>
                </div>

                <div class="google-drive-browser-state" data-google-drive-browser-state hidden></div>

                <div class="google-drive-browser-columns google-drive-browser-columns-head" aria-hidden="true">
                    <span>Nome</span>
                    <span>Proprietario</span>
                    <span>Ultima modificacao</span>
                </div>

                <div class="google-drive-browser-list" data-google-drive-browser-list></div>

                <div class="google-drive-browser-more" data-google-drive-browser-more-wrap hidden>
                    <button type="button" class="btn btn-mini btn-ghost" data-google-drive-browser-more>Carregar mais</button>
                </div>
            </section>
        </div>

        <div class="modal-actions google-drive-browser-actions">
            <span class="google-drive-browser-selection-count" data-google-drive-browser-selection-count>Nenhuma midia selecionada</span>
            <div class="google-drive-browser-actions-buttons">
                <button type="button" class="btn btn-mini btn-ghost" data-close-google-drive-browser>Cancelar</button>
                <button type="button" class="btn btn-mini" data-google-drive-browser-attach disabled>Adicionar selecionadas</button>
            </div>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-confirm-modal hidden>
    <div class="modal-scrim" data-close-confirm-modal></div>
    <section class="modal-card confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <header class="modal-head">
            <h2 id="confirm-modal-title">Confirmar</h2>
            <button type="button" class="modal-close-button" data-close-confirm-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <div class="confirm-modal-body">
            <p data-confirm-modal-message>Tem certeza?</p>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-mini btn-ghost" data-close-confirm-modal data-confirm-modal-cancel>Cancelar</button>
            <button type="button" class="btn btn-mini btn-danger" data-confirm-modal-submit>Confirmar</button>
        </div>
    </section>
</div>
