            <?php
            $renderAccountingMoney = static function (string $amountLabel, string $extraClass = ''): string {
                $normalized = trim($amountLabel);
                if ($normalized === '') {
                    $normalized = 'R$ 0,00';
                }

                $className = trim('accounting-money ' . $extraClass);
                if (preg_match('/^(-?\s*R\$)?\s*([\d\.]+)(,\d{2})$/u', $normalized, $matches)) {
                    $prefix = trim((string) ($matches[1] ?? ''));
                    $major = (string) ($matches[2] ?? '0');
                    $minor = (string) ($matches[3] ?? ',00');

                    return sprintf(
                        '<span class="%s">%s<span class="accounting-money-major">%s</span><span class="accounting-money-minor">%s</span></span>',
                        e($className),
                        $prefix !== ''
                            ? '<span class="accounting-money-prefix">' . e($prefix) . '</span>'
                            : '',
                        e($major),
                        e($minor)
                    );
                }

                return '<span class="' . e($className) . '">' . e($normalized) . '</span>';
            };
            $renderAccountingAttributes = static function (array $attributes = []): string {
                $chunks = [];
                foreach ($attributes as $attributeName => $attributeValue) {
                    if ($attributeValue === null || $attributeValue === false) {
                        continue;
                    }
                    if ($attributeValue === true) {
                        $chunks[] = e((string) $attributeName);
                        continue;
                    }

                    $chunks[] = e((string) $attributeName) . '="' . e((string) $attributeValue) . '"';
                }

                return $chunks ? ' ' . implode(' ', $chunks) : '';
            };
            $renderAccountingHelpTooltip = static function (
                string $message,
                string $buttonLabel = 'Mostrar ajuda',
                string $extraClass = '',
                array $containerAttributes = [],
                array $bubbleAttributes = []
            ) use ($renderAccountingAttributes): string {
                static $tooltipCounter = 0;

                $normalizedMessage = trim($message);
                if ($normalizedMessage === '') {
                    return '';
                }

                $tooltipCounter += 1;
                $tooltipId = 'accounting-help-tooltip-' . $tooltipCounter;
                $className = trim('accounting-help-tooltip ' . $extraClass);

                ob_start();
                ?>
                <span class="<?= e($className) ?>"<?= $renderAccountingAttributes($containerAttributes) ?>>
                    <button
                        type="button"
                        class="accounting-help-tooltip-trigger"
                        aria-label="<?= e($buttonLabel . ': ' . $normalizedMessage) ?>"
                        aria-describedby="<?= e($tooltipId) ?>"
                    >
                        <span aria-hidden="true">?</span>
                    </button>
                    <span
                        class="accounting-help-tooltip-bubble"
                        id="<?= e($tooltipId) ?>"
                        role="tooltip"
                        <?= $renderAccountingAttributes($bubbleAttributes) ?>
                    ><?= e($normalizedMessage) ?></span>
                </span>
                <?php

                return (string) ob_get_clean();
            };
            $accountingTaskLinkOptions = is_array($accountingTaskLinkOptions ?? null)
                ? $accountingTaskLinkOptions
                : ['workspaces' => [], 'groups_by_workspace' => [], 'users_by_workspace' => []];
            $accountingTaskLinkWorkspaces = is_array($accountingTaskLinkOptions['workspaces'] ?? null)
                ? array_values($accountingTaskLinkOptions['workspaces'])
                : [];
            $accountingTaskLinkGroupsByWorkspace = is_array($accountingTaskLinkOptions['groups_by_workspace'] ?? null)
                ? $accountingTaskLinkOptions['groups_by_workspace']
                : [];
            $accountingTaskLinkUsersByWorkspace = is_array($accountingTaskLinkOptions['users_by_workspace'] ?? null)
                ? $accountingTaskLinkOptions['users_by_workspace']
                : [];
            $accountingTaskLinkContextWorkspaceId = isset($currentWorkspaceId)
                ? (int) $currentWorkspaceId
                : (isset($workspaceId) ? (int) $workspaceId : 0);
            $resolveAccountingTaskLinkWorkspaceId = static function (?int $preferredWorkspaceId = null) use ($accountingTaskLinkWorkspaces, $accountingTaskLinkContextWorkspaceId): ?int {
                $candidateWorkspaceId = $preferredWorkspaceId !== null && $preferredWorkspaceId > 0
                    ? $preferredWorkspaceId
                    : ($accountingTaskLinkContextWorkspaceId > 0 ? $accountingTaskLinkContextWorkspaceId : null);
                foreach ($accountingTaskLinkWorkspaces as $workspaceOption) {
                    $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
                    if ($workspaceOptionId <= 0) {
                        continue;
                    }
                    if ($candidateWorkspaceId !== null && $workspaceOptionId === $candidateWorkspaceId) {
                        return $workspaceOptionId;
                    }
                }

                foreach ($accountingTaskLinkWorkspaces as $workspaceOption) {
                    $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
                    if ($workspaceOptionId > 0) {
                        return $workspaceOptionId;
                    }
                }

                return null;
            };
            $accountingTaskLinkDefaultWorkspaceId = $resolveAccountingTaskLinkWorkspaceId(null);
            $accountingTaskLinkGroupsForWorkspace = static function (?int $workspaceId = null) use ($accountingTaskLinkGroupsByWorkspace, $resolveAccountingTaskLinkWorkspaceId): array {
                $resolvedWorkspaceId = $resolveAccountingTaskLinkWorkspaceId($workspaceId);
                if ($resolvedWorkspaceId === null) {
                    return [];
                }

                $groups = $accountingTaskLinkGroupsByWorkspace[(string) $resolvedWorkspaceId] ?? [];
                if (!is_array($groups)) {
                    return [];
                }

                return array_values(array_unique(array_map(
                    static fn ($groupName): string => normalizeTaskGroupName((string) $groupName),
                    $groups
                )));
            };
            $accountingTaskLinkUsersForWorkspace = static function (?int $workspaceId = null) use ($accountingTaskLinkUsersByWorkspace, $resolveAccountingTaskLinkWorkspaceId): array {
                $resolvedWorkspaceId = $resolveAccountingTaskLinkWorkspaceId($workspaceId);
                if ($resolvedWorkspaceId === null) {
                    return [];
                }

                $users = $accountingTaskLinkUsersByWorkspace[(string) $resolvedWorkspaceId] ?? [];
                return is_array($users) ? array_values($users) : [];
            };
            $renderAccountingTaskLinkWorkspaceOptions = static function (?int $selectedWorkspaceId = null) use ($accountingTaskLinkWorkspaces, $resolveAccountingTaskLinkWorkspaceId): string {
                $resolvedWorkspaceId = $resolveAccountingTaskLinkWorkspaceId($selectedWorkspaceId);
                ob_start();
                if (!$accountingTaskLinkWorkspaces) {
                    echo '<option value="">Nenhum workspace disponível</option>';
                } else {
                    foreach ($accountingTaskLinkWorkspaces as $workspaceOption) {
                        $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
                        if ($workspaceOptionId <= 0) {
                            continue;
                        }
                        $workspaceOptionName = normalizeWorkspaceName((string) ($workspaceOption['name'] ?? 'Workspace'));
                        echo '<option value="' . e((string) $workspaceOptionId) . '"'
                            . ($resolvedWorkspaceId === $workspaceOptionId ? ' selected' : '')
                            . '>'
                            . e($workspaceOptionName)
                            . '</option>';
                    }
                }

                return (string) ob_get_clean();
            };
            $renderAccountingTaskLinkGroupOptions = static function (?int $workspaceId = null, ?string $selectedGroupName = null) use ($accountingTaskLinkGroupsForWorkspace): string {
                $groupNames = $accountingTaskLinkGroupsForWorkspace($workspaceId);
                $resolvedGroupName = $selectedGroupName !== null && trim($selectedGroupName) !== ''
                    ? normalizeTaskGroupName($selectedGroupName)
                    : ($groupNames[0] ?? '');
                ob_start();
                if (!$groupNames) {
                    echo '<option value="">Nenhum projeto disponível</option>';
                } else {
                    foreach ($groupNames as $groupName) {
                        echo '<option value="' . e($groupName) . '"'
                            . ($resolvedGroupName === $groupName ? ' selected' : '')
                            . '>'
                            . e($groupName)
                            . '</option>';
                    }
                }

                return (string) ob_get_clean();
            };
            $accountingTaskLinkAssigneeSummary = static function (?int $workspaceId = null, array $selectedAssigneeIds = []) use ($accountingTaskLinkUsersForWorkspace): string {
                $selectedLookup = array_fill_keys(normalizeAssigneeIds($selectedAssigneeIds), true);
                if (!$selectedLookup) {
                    return 'Todos os responsáveis';
                }

                $selectedNames = [];
                foreach ($accountingTaskLinkUsersForWorkspace($workspaceId) as $workspaceUser) {
                    $workspaceUserId = (int) ($workspaceUser['id'] ?? 0);
                    if ($workspaceUserId <= 0 || !isset($selectedLookup[$workspaceUserId])) {
                        continue;
                    }
                    $selectedNames[] = normalizeUserDisplayName((string) ($workspaceUser['name'] ?? 'Usuário'));
                }

                return $selectedNames ? implode(', ', $selectedNames) : 'Todos os responsáveis';
            };
            $renderAccountingTaskLinkAssigneePicker = static function (?int $workspaceId = null, array $selectedAssigneeIds = [], bool $disabled = false) use ($accountingTaskLinkUsersForWorkspace, $accountingTaskLinkAssigneeSummary): string {
                $workspaceUsers = $accountingTaskLinkUsersForWorkspace($workspaceId);
                $selectedLookup = array_fill_keys(normalizeAssigneeIds($selectedAssigneeIds), true);
                $summaryLabel = $accountingTaskLinkAssigneeSummary($workspaceId, $selectedAssigneeIds);
                ob_start();
                ?>
                <div class="assignee-picker-wrap task-detail-inline-field task-detail-inline-assignees accounting-task-link-picker-wrap">
                    <span class="assignee-picker-label">Respons&aacute;veis</span>
                    <details class="assignee-picker row-assignee-picker" data-accounting-task-link-assignees>
                        <summary><?= e($summaryLabel) ?></summary>
                        <div class="assignee-picker-menu" aria-label="Selecionar responsáveis" data-sheet-title="Responsáveis" data-accounting-task-link-assignee-menu>
                            <?php if (!$workspaceUsers): ?>
                                <p class="assignee-picker-empty">Nenhum usu&aacute;rio dispon&iacute;vel.</p>
                            <?php else: ?>
                                <?php foreach ($workspaceUsers as $workspaceUser): ?>
                                    <?php $workspaceUserId = (int) ($workspaceUser['id'] ?? 0); ?>
                                    <?php if ($workspaceUserId <= 0) { continue; } ?>
                                    <label class="assignee-option">
                                        <input
                                            type="checkbox"
                                            name="task_link_assignee_ids[]"
                                            value="<?= e((string) $workspaceUserId) ?>"
                                            data-assignee-name="<?= e((string) ($workspaceUser['name'] ?? 'Usuário')) ?>"
                                            data-assignee-avatar="<?= e((string) ($workspaceUser['avatar'] ?? '')) ?>"
                                            data-assignee-initial="<?= e((string) ($workspaceUser['initial'] ?? 'U')) ?>"
                                            <?= isset($selectedLookup[$workspaceUserId]) ? 'checked' : '' ?>
                                            <?= $disabled ? 'disabled' : '' ?>
                                        >
                                        <?= renderUserAvatar($workspaceUser, 'avatar small assignee-option-avatar', true, 'span') ?>
                                        <span class="assignee-option-text"><?= e((string) ($workspaceUser['name'] ?? 'Usuário')) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <?php
                return (string) ob_get_clean();
            };
            $renderAccountingTaskLinkFields = static function (
                ?int $workspaceId = null,
                ?string $groupName = null,
                array $selectedAssigneeIds = [],
                bool $hidden = true,
                bool $disabled = true
            ) use (
                $renderAccountingTaskLinkWorkspaceOptions,
                $renderAccountingTaskLinkGroupOptions,
                $renderAccountingTaskLinkAssigneePicker,
                $renderAccountingHelpTooltip
            ): string {
                ob_start();
                ?>
                <div class="accounting-task-link-fields" data-accounting-task-link-fields<?= $hidden ? ' hidden' : '' ?>>
                    <label class="accounting-entry-edit-control">
                        <span>Workspace</span>
                        <select
                            name="task_link_workspace_id"
                            class="accounting-installment-select"
                            aria-label="Workspace das tarefas concluídas"
                            data-accounting-task-link-workspace
                            <?= $disabled ? 'disabled' : '' ?>
                        >
                            <?= $renderAccountingTaskLinkWorkspaceOptions($workspaceId) ?>
                        </select>
                    </label>
                    <label class="accounting-entry-edit-control">
                        <span>Projeto</span>
                        <select
                            name="task_link_group_name"
                            class="accounting-installment-select"
                            aria-label="Projeto das tarefas concluídas"
                            data-accounting-task-link-group
                            <?= $disabled ? 'disabled' : '' ?>
                        >
                            <?= $renderAccountingTaskLinkGroupOptions($workspaceId, $groupName) ?>
                        </select>
                    </label>
                    <?= $renderAccountingTaskLinkAssigneePicker($workspaceId, $selectedAssigneeIds, $disabled) ?>
                    <div class="accounting-help-row">
                        <?= $renderAccountingHelpTooltip(
                            'O valor informado acima passa a ser o ganho por tarefa. O total desta entrada se atualiza sozinho conforme as concluídas do período.',
                            'Entender entrada por tarefa',
                            'is-left'
                        ) ?>
                    </div>
                </div>
                <?php
                return (string) ob_get_clean();
            };
            $renderAccountingTaskLinkHiddenAssigneeInputs = static function (array $selectedAssigneeIds = []): string {
                $selectedAssigneeIds = normalizeAssigneeIds($selectedAssigneeIds);
                ob_start();
                foreach ($selectedAssigneeIds as $selectedAssigneeId) {
                    echo '<input type="hidden" name="task_link_assignee_ids[]" value="' . e((string) $selectedAssigneeId) . '">';
                }

                return (string) ob_get_clean();
            };
            $accountingTaskLinkOptionsJson = json_encode(
                [
                    'workspaces' => $accountingTaskLinkWorkspaces,
                    'groups_by_workspace' => $accountingTaskLinkGroupsByWorkspace,
                    'users_by_workspace' => $accountingTaskLinkUsersByWorkspace,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) ?: '{}';
            ?>
            <div class="accounting-sheet">
                <script type="application/json" data-accounting-task-link-options><?= $accountingTaskLinkOptionsJson ?></script>
                <div class="accounting-columns">
                    <section class="accounting-card is-expense-card<?= empty($accountingExpenseEntries) ? ' is-empty' : '' ?>">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Contas</h3>
                                <p>Despesas do mês</p>
                            </div>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingExpenseEntries)): ?>
                                <div class="accounting-empty">Nenhuma conta cadastrada neste mês.</div>
                            <?php else: ?>
                                <?php foreach ($accountingExpenseEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsCarried = ((int) ($accountingEntry['is_carried'] ?? 0)) === 1;
                                    $accountingEntrySourceDueId = (int) ($accountingEntry['source_due_entry_id'] ?? 0);
                                    $accountingEntryIsMonthlyDue = $accountingEntrySourceDueId > 0;
                                    $accountingEntryIsMonthlyGoal = ((int) ($accountingEntry['is_monthly_goal'] ?? 0)) === 1;
                                    $accountingEntryMonthlyDay = normalizeDueMonthlyDay($accountingEntry['source_due_monthly_day'] ?? null);
                                    $accountingEntryDueDateDisplay = (string) ($accountingEntry['due_date_display'] ?? '');
                                    $accountingEntryGoalPaymentInput = (string) ($accountingEntry['goal_payment_input'] ?? '0,00');
                                    $accountingEntryGoalPaymentDisplay = (string) ($accountingEntry['goal_payment_display'] ?? 'R$ 0,00');
                                    $accountingEntryGoalTotalDisplay = (string) ($accountingEntry['total_amount_display'] ?? $accountingEntryAmountInput);
                                    $accountingEntryGoalPaymentHistory = is_array($accountingEntry['goal_payment_history'] ?? null)
                                        ? $accountingEntry['goal_payment_history']
                                        : [];
                                    $accountingEntryGoalPaidCents = max(0, (int) ($accountingEntry['paid_amount_cents'] ?? 0));
                                    $accountingEntryGoalTotalCents = max(0, (int) ($accountingEntry['total_amount_cents'] ?? 0));
                                    $accountingEntryGoalRemainingCents = max(0, $accountingEntryGoalTotalCents - $accountingEntryGoalPaidCents);
                                    $accountingEntryGoalPaymentCompactDisplay = dueAmountCompactLabelFromCents($accountingEntryGoalPaidCents, true);
                                    $accountingEntryGoalTotalCompactDisplay = dueAmountCompactLabelFromCents($accountingEntryGoalTotalCents, true);
                                    $accountingEntryGoalRemainingDisplay = dueAmountLabelFromCents($accountingEntryGoalRemainingCents);
                                    $accountingEntryGoalIsComplete = $accountingEntryGoalTotalCents > 0
                                        && $accountingEntryGoalPaidCents >= $accountingEntryGoalTotalCents;
                                    $accountingEntryGoalProgressPercent = $accountingEntryGoalTotalCents > 0
                                        ? min(100, max(0, ($accountingEntryGoalPaidCents / $accountingEntryGoalTotalCents) * 100))
                                        : 0;
                                    $accountingEntryGoalProgressWidth = number_format($accountingEntryGoalProgressPercent, 2, '.', '');
                                    $accountingEntryMonthlyBadge = $accountingEntryIsMonthlyGoal
                                        ? 'Saldo a quitar'
                                        : ($accountingEntryIsMonthlyDue && $accountingEntryMonthlyDay !== null
                                            ? ('Mensal - ' . str_pad((string) $accountingEntryMonthlyDay, 2, '0', STR_PAD_LEFT))
                                            : '');
                                    $accountingEntryIsOverdue = ((int) ($accountingEntry['is_overdue'] ?? 0)) === 1;
                                    $accountingEntryDueDateBadge = $accountingEntryDueDateDisplay !== ''
                                        && ($accountingEntryIsCarried || $accountingEntryIsOverdue)
                                        ? ('Venc. ' . $accountingEntryDueDateDisplay)
                                        : '';
                                    $accountingEntryOverdueDays = max(0, (int) ($accountingEntry['overdue_days'] ?? 0));
                                    $accountingEntryShowPendingBadge = $accountingEntryIsCarried
                                        && !$accountingEntryIsSettled
                                        && !$accountingEntryIsInstallment
                                        && !$accountingEntryIsMonthlyGoal;
                                    $accountingEntrySubitems = is_array($accountingEntry['subitems'] ?? null)
                                        ? $accountingEntry['subitems']
                                        : [];
                                    $accountingEntryHasSubitems = !empty($accountingEntrySubitems);
                                    $accountingEntrySupportsSubitems = ((int) ($accountingEntry['supports_subitems'] ?? 0)) === 1;
                                    $accountingEntrySubitemCount = count($accountingEntrySubitems);
                                    $accountingEntrySubitemBadge = $accountingEntrySubitemCount > 0
                                        ? ($accountingEntrySubitemCount . ' sub' . ($accountingEntrySubitemCount === 1 ? '' : 's'))
                                        : '';
                                    ?>
                                    <div class="accounting-entry-row<?= $accountingEntryIsMonthlyGoal ? ' is-goal-entry' : '' ?>">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                    <?php if ($accountingEntryIsMonthlyGoal): ?>
                                                        <span
                                                            class="accounting-entry-goal-progress<?= $accountingEntryGoalIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Pago <?= e($accountingEntryGoalPaymentDisplay) ?> de <?= e($accountingEntryGoalTotalDisplay) ?>"
                                                        >
                                                            <?php if ($accountingEntryGoalIsComplete): ?>
                                                                <span class="accounting-entry-goal-progress-status" aria-hidden="true">
                                                                    <svg viewBox="0 0 16 16" focusable="false">
                                                                        <path d="M3.5 8.4 6.5 11.2 12.5 4.8"></path>
                                                                    </svg>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="accounting-entry-goal-progress-bar">
                                                                <span class="accounting-entry-goal-progress-fill" style="width: <?= e($accountingEntryGoalProgressWidth) ?>%"></span>
                                                                <span class="accounting-entry-goal-progress-values">
                                                                    <span class="accounting-entry-goal-progress-paid"><?= e($accountingEntryGoalPaymentCompactDisplay) ?></span>
                                                                    <span class="accounting-entry-goal-progress-separator">/</span>
                                                                    <strong class="accounting-entry-goal-progress-total"><?= e($accountingEntryGoalTotalCompactDisplay) ?></strong>
                                                                </span>
                                                            </span>
                                                        </span>
                                                    <?php elseif ($accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge || $accountingEntryIsOverdue || $accountingEntrySubitemBadge !== ''): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryIsInstallment): ?>
                                                                <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntrySubitemBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-subitems"><?= e($accountingEntrySubitemBadge) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryShowPendingBadge): ?>
                                                                <span class="accounting-entry-badge is-pending">Pendente</span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryDueDateBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryDueDateBadge) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryIsOverdue): ?>
                                                                <span
                                                                    class="accounting-entry-badge is-overdue"
                                                                    title="Conta em atraso h&aacute; <?= e((string) $accountingEntryOverdueDays) ?> dia(s)."
                                                                >Atrasado</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                            <?php if ($accountingEntryIsMonthlyGoal): ?>
                                                <span
                                                    class="accounting-entry-summary-amount accounting-entry-summary-amount-goal"
                                                    aria-label="Pago at&eacute; agora <?= e($accountingEntryGoalPaymentDisplay) ?>"
                                                >
                                                    <?= $renderAccountingMoney($accountingEntryGoalPaymentDisplay) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="accounting-entry-summary-amount"><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <?php if ($accountingEntryIsMonthlyGoal): ?>
                                            <div class="accounting-entry-goal-payment-panel">
                                                <button
                                                    type="button"
                                                    class="accounting-entry-goal-payment-trigger"
                                                    data-accounting-goal-payment-toggle
                                                    aria-label="Abrir lançamentos"
                                                    title="Abrir lançamentos"
                                                >
                                                    <span class="accounting-entry-goal-payment-trigger-icon" aria-hidden="true">+</span>
                                                </button>
                                            </div>
                                            <div class="accounting-entry-goal-payment-drawer" data-accounting-goal-payment-drawer hidden>
                                                <div class="accounting-entry-goal-payment-drawer-head">
                                                    <div class="accounting-entry-goal-payment-drawer-copy">
                                                        <div class="accounting-heading-with-help">
                                                            <strong>Lançamentos</strong>
                                                            <?= $renderAccountingHelpTooltip(
                                                                'Os lançamentos abaixo compõem o valor já pago e são os únicos que mexem no caixa.',
                                                                'Entender lançamentos do saldo a quitar',
                                                                'is-left'
                                                            ) ?>
                                                        </div>
                                                    </div>
                                                    <div class="accounting-entry-goal-payment-drawer-tools">
                                                        <div
                                                            class="accounting-entry-goal-payment-remaining<?= $accountingEntryGoalIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Falta <?= e($accountingEntryGoalRemainingDisplay) ?> para quitar o saldo"
                                                        >
                                                            <span>Falta</span>
                                                            <strong><?= $renderAccountingMoney($accountingEntryGoalRemainingDisplay) ?></strong>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            class="accounting-entry-goal-payment-close"
                                                            data-accounting-goal-payment-close
                                                            aria-label="Fechar lançamentos"
                                                        >
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <form method="post" class="accounting-entry-goal-payment-add-form" data-accounting-goal-payment-add-form autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_goal_payment">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input
                                                        type="text"
                                                        name="payment_amount_value"
                                                        value=""
                                                        class="accounting-input accounting-input-amount"
                                                        inputmode="numeric"
                                                        placeholder="0,00"
                                                        autocomplete="off"
                                                        required
                                                    >
                                                    <div class="accounting-entry-goal-payment-actions">
                                                        <button type="submit" class="btn btn-mini">Adicionar valor</button>
                                                    </div>
                                                </form>
                                                <div class="accounting-entry-goal-payment-history">
                                                    <?php if ($accountingEntryGoalPaymentHistory): ?>
                                                        <?php foreach ($accountingEntryGoalPaymentHistory as $goalPaymentHistoryItem): ?>
                                                            <?php
                                                            $goalPaymentHistoryId = (int) ($goalPaymentHistoryItem['id'] ?? 0);
                                                            $goalPaymentHistoryAmountDisplay = (string) ($goalPaymentHistoryItem['amount_display'] ?? 'R$ 0,00');
                                                            $goalPaymentHistoryCreatedAt = (string) ($goalPaymentHistoryItem['created_at'] ?? '');
                                                            $goalPaymentHistoryCreatedAtDisplay = (string) ($goalPaymentHistoryItem['created_at_display'] ?? '');
                                                            ?>
                                                            <div class="accounting-entry-goal-payment-item">
                                                                <div class="accounting-entry-goal-payment-item-main">
                                                                    <strong><?= $renderAccountingMoney($goalPaymentHistoryAmountDisplay) ?></strong>
                                                                    <time datetime="<?= e($goalPaymentHistoryCreatedAt) ?>"><?= e($goalPaymentHistoryCreatedAtDisplay) ?></time>
                                                                </div>
                                                                <form method="post" class="accounting-entry-goal-payment-delete-form" data-accounting-goal-payment-delete-form>
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="delete_accounting_goal_payment">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="payment_id" value="<?= e((string) $goalPaymentHistoryId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <button
                                                                        type="submit"
                                                                        class="accounting-entry-goal-payment-delete"
                                                                        aria-label="Remover lançamento de <?= e($goalPaymentHistoryAmountDisplay) ?>"
                                                                    >
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p class="accounting-entry-goal-payment-empty">Nenhum lançamento registrado.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <form method="post" class="accounting-entry-quick-status-form" data-accounting-form>
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="update_accounting_entry">
                                                <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                <input type="hidden" name="label" value="<?= e($accountingEntryLabel) ?>">
                                                <input type="hidden" name="amount_value" value="<?= e($accountingEntryAmountInput) ?>">
                                                <input type="hidden" name="is_installment" value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>">
                                                <input type="hidden" name="installment_progress" value="<?= e($accountingEntryInstallmentProgress) ?>">
                                                <input type="hidden" name="total_amount_value" value="<?= e($accountingEntryTotalAmountInput) ?>">
                                                <input type="hidden" name="monthly_mode" value="uniform">
                                                <input type="hidden" name="monthly_day" value="<?= $accountingEntryMonthlyDay !== null ? e((string) $accountingEntryMonthlyDay) : '' ?>">
                                                <label class="accounting-check">
                                                    <input
                                                        type="checkbox"
                                                        <?= $accountingEntryHasSubitems ? '' : 'name="is_settled" value="1"' ?>
                                                        <?= $accountingEntryIsSettled ? 'checked' : '' ?>
                                                        <?= $accountingEntryHasSubitems ? 'disabled aria-disabled="true"' : '' ?>
                                                    >
                                                    <span>Pago</span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir conta">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                        <form method="post" class="accounting-entry-form accounting-entry-editor-form" data-accounting-form hidden autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da conta"
                                                autocomplete="off"
                                                required
                                            >
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryIsMonthlyGoal ? $accountingEntryTotalAmountInput : $accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                inputmode="numeric"
                                                placeholder="0,00"
                                                autocomplete="off"
                                                required
                                                data-accounting-primary-amount
                                                <?= ($accountingEntryIsInstallment || $accountingEntryHasSubitems) ? 'readonly' : '' ?>
                                            >
                                            <?php if ($accountingEntryIsMonthlyGoal || $accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge || $accountingEntrySubitemBadge !== ''): ?>
                                                <div class="accounting-entry-meta">
                                                    <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <label class="accounting-entry-edit-control is-monthly">
                                                            <span>Mensal -</span>
                                                            <select name="monthly_day" class="accounting-installment-select" aria-label="Dia do vencimento mensal">
                                                                <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                    <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === $accountingEntryMonthlyDay ? 'selected' : '' ?>>
                                                                        <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </label>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryShowPendingBadge): ?>
                                                        <span class="accounting-entry-badge is-pending">Pendente</span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntrySubitemBadge !== ''): ?>
                                                        <span class="accounting-entry-badge is-subitems"><?= e($accountingEntrySubitemBadge) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="accounting-entry-status">
                                                <?php if (!$accountingEntryIsMonthlyGoal): ?>
                                                    <label class="accounting-check">
                                                        <input
                                                            type="checkbox"
                                                            <?= $accountingEntryHasSubitems ? '' : 'name="is_settled" value="1"' ?>
                                                            <?= $accountingEntryIsSettled ? 'checked' : '' ?>
                                                            <?= $accountingEntryHasSubitems ? 'disabled aria-disabled="true"' : '' ?>
                                                        >
                                                        <span>Pago</span>
                                                    </label>
                                                <?php endif; ?>
                                            </div>
                                            <div class="accounting-entry-flow-note">
                                                <?= $renderAccountingHelpTooltip(
                                                    $accountingEntryIsMonthlyGoal
                                                        ? 'Saldo a quitar: os pagamentos parciais são lançados no botão + e só eles entram no caixa e na projeção.'
                                                        : 'Despesa prevista: este valor continua na projeção até você marcar como pago.',
                                                    $accountingEntryIsMonthlyGoal
                                                        ? 'Entender saldo a quitar'
                                                        : 'Entender projeção desta conta',
                                                    'is-left'
                                                ) ?>
                                            </div>
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                            <input type="hidden" name="is_monthly_due" value="<?= ($accountingEntryIsMonthlyDue || $accountingEntryIsMonthlyGoal) ? '1' : '0' ?>">
                                            <input type="hidden" name="monthly_mode" value="<?= $accountingEntryIsMonthlyGoal ? 'goal' : 'uniform' ?>">
                                            <?php if (!$accountingEntryIsMonthlyDue): ?>
                                                <input type="hidden" name="monthly_day" value="">
                                            <?php endif; ?>
                                        </form>
                                        <?php if ($accountingEntrySupportsSubitems): ?>
                                            <div class="accounting-entry-subitems-panel">
                                                <div class="accounting-entry-subitems-head">
                                                    <strong>Subitens</strong>
                                                    <span><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                                </div>
                                                <?php if ($accountingEntrySubitems): ?>
                                                    <div class="accounting-entry-subitems-list">
                                                        <?php foreach ($accountingEntrySubitems as $accountingSubitem): ?>
                                                            <?php
                                                            $accountingSubitemId = (int) ($accountingSubitem['id'] ?? 0);
                                                            $accountingSubitemLabel = (string) ($accountingSubitem['label'] ?? '');
                                                            $accountingSubitemAmountInput = (string) ($accountingSubitem['amount_input'] ?? 'R$ 0,00');
                                                            $accountingSubitemIsSettled = ((int) ($accountingSubitem['is_settled'] ?? 0)) === 1;
                                                            ?>
                                                            <div class="accounting-entry-subitem-row<?= $accountingSubitemIsSettled ? ' is-settled' : '' ?>" data-accounting-subitem-row>
                                                                <button
                                                                    type="button"
                                                                    class="accounting-entry-subitem-summary"
                                                                    data-accounting-subitem-edit
                                                                    aria-expanded="false"
                                                                    aria-label="Editar subitem <?= e($accountingSubitemLabel) ?>"
                                                                >
                                                                    <span class="accounting-entry-subitem-summary-label"><?= e($accountingSubitemLabel) ?></span>
                                                                    <span class="accounting-entry-subitem-summary-amount"><?= $renderAccountingMoney($accountingSubitemAmountInput) ?></span>
                                                                </button>
                                                                <form method="post" class="accounting-entry-subitem-status-form" data-accounting-subitem-status-form>
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="update_accounting_subitem">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="subitem_id" value="<?= e((string) $accountingSubitemId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <input type="hidden" name="subitem_label" value="<?= e($accountingSubitemLabel) ?>">
                                                                    <input type="hidden" name="subitem_amount_value" value="<?= e($accountingSubitemAmountInput) ?>">
                                                                    <label class="accounting-check accounting-entry-subitem-paid-check">
                                                                        <input type="checkbox" name="is_settled" value="1" <?= $accountingSubitemIsSettled ? 'checked' : '' ?>>
                                                                        <span>Pago</span>
                                                                    </label>
                                                                </form>
                                                                <form method="post" class="accounting-entry-subitem-form" data-accounting-subitem-form autocomplete="off" hidden>
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="update_accounting_subitem">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="subitem_id" value="<?= e((string) $accountingSubitemId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <input
                                                                        type="text"
                                                                        name="subitem_label"
                                                                        value="<?= e($accountingSubitemLabel) ?>"
                                                                        maxlength="120"
                                                                        class="accounting-input accounting-input-label"
                                                                        autocomplete="off"
                                                                        required
                                                                    >
                                                                    <input
                                                                        type="text"
                                                                        name="subitem_amount_value"
                                                                        value="<?= e($accountingSubitemAmountInput) ?>"
                                                                        class="accounting-input accounting-input-amount"
                                                                        inputmode="numeric"
                                                                        autocomplete="off"
                                                                        required
                                                                    >
                                                                    <label class="accounting-check accounting-entry-subitem-editor-paid-check">
                                                                        <input type="checkbox" name="is_settled" value="1" <?= $accountingSubitemIsSettled ? 'checked' : '' ?>>
                                                                        <span>Pago</span>
                                                                    </label>
                                                                    <div class="accounting-entry-subitem-editor-actions">
                                                                        <button type="submit" class="btn btn-mini">Confirmar</button>
                                                                        <button type="button" class="btn btn-mini btn-ghost" data-accounting-subitem-cancel>Cancelar</button>
                                                                    </div>
                                                                </form>
                                                                <form method="post" class="accounting-entry-subitem-delete-form" data-accounting-subitem-delete-form>
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="delete_accounting_subitem">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="subitem_id" value="<?= e((string) $accountingSubitemId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <button type="submit" class="accounting-entry-subitem-delete" aria-label="Remover subitem">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <form method="post" class="accounting-entry-subitem-add-form" data-accounting-subitem-form autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="create_accounting_subitem">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input
                                                        type="text"
                                                        name="subitem_label"
                                                        maxlength="120"
                                                        class="accounting-input accounting-input-label"
                                                        placeholder="Subitem"
                                                        autocomplete="off"
                                                        required
                                                    >
                                                    <input
                                                        type="text"
                                                        name="subitem_amount_value"
                                                        class="accounting-input accounting-input-amount"
                                                        inputmode="numeric"
                                                        placeholder="0,00"
                                                        autocomplete="off"
                                                        required
                                                    >
                                                    <button type="submit" class="btn btn-mini">+</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="expense">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova conta"
                                        autocomplete="off"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        inputmode="numeric"
                                        placeholder="0,00"
                                        autocomplete="off"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <input type="hidden" name="create_subitems_json" value="[]" data-accounting-create-subitems-json>
                                    <div class="accounting-create-subitems" data-accounting-create-subitems>
                                        <div class="accounting-create-subitems-head">
                                            <strong>Subitens</strong>
                                            <span data-accounting-create-subitems-total>R$ 0,00</span>
                                        </div>
                                        <div class="accounting-create-subitems-list" data-accounting-create-subitems-list></div>
                                        <button type="button" class="btn btn-mini btn-ghost accounting-create-subitem-add" data-accounting-create-subitem-add>+ Subitem</button>
                                    </div>
                                    <div class="accounting-create-footer">
                                        <div class="accounting-create-meta">
                                            <div class="accounting-entry-options">
                                                <select
                                                    name="accounting_type_choice"
                                                    class="accounting-installment-select accounting-entry-type-select"
                                                    aria-label="Tipo de conta"
                                                    data-accounting-type-select
                                                >
                                                    <option value="single">&Uacute;nica</option>
                                                    <option value="installment">Parcelada</option>
                                                    <option value="monthly">Mensal</option>
                                                    <option value="goal">Saldo a quitar</option>
                                                </select>
                                                <input
                                                    type="checkbox"
                                                    name="is_installment"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-installment-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <input
                                                    type="checkbox"
                                                    name="is_monthly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-monthly-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <div class="accounting-installment-fields" data-accounting-installment-fields hidden>
                                                    <div class="accounting-installment-progress-picker">
                                                        <select
                                                            name="installment_number"
                                                            class="accounting-installment-select"
                                                            aria-label="Parcela atual"
                                                            data-accounting-installment-number
                                                            disabled
                                                        >
                                                            <?php for ($installmentNumberOption = 1; $installmentNumberOption <= 60; $installmentNumberOption++): ?>
                                                                <option value="<?= e((string) $installmentNumberOption) ?>"><?= e((string) $installmentNumberOption) ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                        <span class="accounting-installment-separator">/</span>
                                                        <select
                                                            name="installment_total"
                                                            class="accounting-installment-select"
                                                            aria-label="Total de parcelas"
                                                            data-accounting-installment-total-count
                                                            disabled
                                                        >
                                                            <?php for ($installmentTotalOption = 2; $installmentTotalOption <= 60; $installmentTotalOption++): ?>
                                                                <option value="<?= e((string) $installmentTotalOption) ?>" <?= $installmentTotalOption === 2 ? 'selected' : '' ?>>
                                                                    <?= e((string) $installmentTotalOption) ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="installment_progress" value="" data-accounting-installment-progress>
                                                    <input
                                                        type="text"
                                                        name="total_amount_value"
                                                        class="accounting-input accounting-input-amount accounting-input-installment-total"
                                                        inputmode="numeric"
                                                        placeholder="Valor total"
                                                        aria-label="Valor total"
                                                        data-accounting-installment-total-amount
                                                        disabled
                                                    >
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="monthly_mode"
                                                    value="uniform"
                                                    data-accounting-monthly-mode
                                                >
                                                <div class="accounting-monthly-fields" data-accounting-monthly-fields hidden>
                                                    <div class="accounting-monthly-day-field" data-accounting-monthly-day-field>
                                                        <span class="accounting-entry-inline-label">Vencimento</span>
                                                        <select
                                                            name="monthly_day"
                                                            class="accounting-installment-select accounting-monthly-day-select"
                                                            aria-label="Dia do vencimento mensal"
                                                            data-accounting-monthly-day
                                                            disabled
                                                        >
                                                            <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === 1 ? 'selected' : '' ?>>
                                                                    <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <?= $renderAccountingHelpTooltip(
                                                    'Contas únicas, mensais e parceladas entram na projeção até serem pagas.',
                                                    'Entender como esta conta entra no caixa e na projeção',
                                                    'is-left',
                                                    [
                                                        'data-accounting-cashflow-note' => '1',
                                                        'data-accounting-default-note' => 'Contas únicas, mensais e parceladas entram na projeção até serem pagas.',
                                                        'data-accounting-goal-note' => 'Saldo a quitar: use para compras pagas aos poucos. Só os pagamentos lançados no botão + mexem no caixa e na projeção.',
                                                        'data-accounting-help-label' => 'Entender como esta conta entra no caixa e na projeção',
                                                    ],
                                                    [
                                                        'data-accounting-tooltip-text' => '1',
                                                    ]
                                                ) ?>
                                            </div>
                                        </div>
                                        <div class="accounting-create-actions">
                                            <button type="submit" class="btn btn-mini">Adicionar</button>
                                            <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            </details>
                            <?php
                            $accountingExpenseTotalCents = max(0, (int) ($accountingSummary['expense_total_cents'] ?? 0));
                            $accountingExpensePaidCents = max(0, (int) ($accountingSummary['expense_paid_cents'] ?? 0));
                            $accountingExpenseRemainingCents = max(0, $accountingExpenseTotalCents - $accountingExpensePaidCents);
                            $accountingExpenseRemainingDisplay = dueAmountLabelFromCents($accountingExpenseRemainingCents);
                            $accountingHideExpensePaidTotal = $accountingExpenseTotalCents > 0
                                && $accountingExpensePaidCents >= $accountingExpenseTotalCents;
                            $accountingShowExpenseRemaining = !$accountingHideExpensePaidTotal
                                && $accountingExpenseRemainingCents > 0;
                            $accountingExpenseTotalAriaLabel = $accountingHideExpensePaidTotal
                                ? ('Total pago ' . (string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00'))
                                : ('Pago ' . (string) ($accountingSummary['expense_paid_display'] ?? 'R$ 0,00') . ' de ' . (string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00'));
                            if ($accountingShowExpenseRemaining) {
                                $accountingExpenseTotalAriaLabel .= '. Faltam ' . $accountingExpenseRemainingDisplay . ' para pagar.';
                            }
                            ?>
                            <dl class="accounting-totals is-single">
                                <div class="is-expense-total<?= $accountingShowExpenseRemaining ? ' has-helper' : '' ?>">
                                    <dt>Total</dt>
                                    <dd
                                        class="accounting-total-pair"
                                        aria-label="<?= e($accountingExpenseTotalAriaLabel) ?>"
                                    >
                                        <?php if (!$accountingHideExpensePaidTotal): ?>
                                            <span class="accounting-total-secondary"><?= $renderAccountingMoney((string) ($accountingSummary['expense_paid_display'] ?? 'R$ 0,00')) ?></span>
                                            <span class="accounting-total-separator">/</span>
                                        <?php endif; ?>
                                        <strong class="accounting-total-main"><?= $renderAccountingMoney((string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00')) ?></strong>
                                    </dd>
                                    <?php if ($accountingShowExpenseRemaining): ?>
                                        <span class="accounting-total-helper">
                                            <span>Faltam</span>
                                            <strong><?= $renderAccountingMoney($accountingExpenseRemainingDisplay) ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="accounting-card is-income-card<?= empty($accountingIncomeEntries) ? ' is-empty' : '' ?>">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Entradas</h3>
                                <p>Receitas do mês</p>
                            </div>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingIncomeEntries)): ?>
                                <div class="accounting-empty">Nenhuma entrada cadastrada neste mês.</div>
                            <?php else: ?>
                                <?php foreach ($accountingIncomeEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryAutomationType = normalizeAccountingAutomationType((string) ($accountingEntry['automation_type'] ?? 'manual'));
                                    $accountingEntryIsTaskLinked = ((int) ($accountingEntry['is_task_linked'] ?? 0)) === 1;
                                    $accountingEntryTaskLinkWorkspaceId = isset($accountingEntry['task_link_workspace_id'])
                                        ? (int) ($accountingEntry['task_link_workspace_id'] ?? 0)
                                        : 0;
                                    $accountingEntryTaskLinkWorkspaceId = $accountingEntryTaskLinkWorkspaceId > 0
                                        ? $accountingEntryTaskLinkWorkspaceId
                                        : $accountingTaskLinkDefaultWorkspaceId;
                                    $accountingEntryTaskLinkGroupName = (string) ($accountingEntry['task_link_group_name'] ?? '');
                                    $accountingEntryTaskLinkAssigneeIds = normalizeAssigneeIds(
                                        is_array($accountingEntry['task_link_assignee_ids'] ?? null)
                                            ? $accountingEntry['task_link_assignee_ids']
                                            : []
                                    );
                                    $accountingEntryTaskLinkRateInput = (string) ($accountingEntry['task_link_rate_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryTaskLinkSummaryLabel = (string) ($accountingEntry['task_link_summary_label'] ?? '');
                                    $accountingEntryTaskLinkScopeLabel = (string) ($accountingEntry['task_link_scope_label'] ?? '');
                                    $accountingEntryTaskLinkAssigneeSummary = (string) ($accountingEntry['task_link_assignee_summary'] ?? 'Todos os responsáveis');
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsMonthly = ((int) ($accountingEntry['is_monthly'] ?? 0)) === 1;
                                    $accountingEntryMonthlyDay = normalizeDueMonthlyDay($accountingEntry['monthly_day'] ?? null);
                                    $accountingEntryMonthlyBadge = $accountingEntryIsMonthly && $accountingEntryMonthlyDay !== null
                                        ? ('Mensal - ' . str_pad((string) $accountingEntryMonthlyDay, 2, '0', STR_PAD_LEFT))
                                        : '';
                                    ?>
                                    <div class="accounting-entry-row">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                    <?php if ($accountingEntryIsTaskLinked || $accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryIsTaskLinked): ?>
                                                                <span class="accounting-entry-badge is-monthly">Por tarefa</span>
                                                            <?php elseif ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryIsInstallment): ?>
                                                                <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                            <span class="accounting-entry-summary-amount"><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                        </button>
                                        <form method="post" class="accounting-entry-quick-status-form" data-accounting-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input type="hidden" name="entry_type" value="income">
                                            <input type="hidden" name="label" value="<?= e($accountingEntryLabel) ?>">
                                            <input type="hidden" name="amount_value" value="<?= e($accountingEntryIsTaskLinked ? $accountingEntryTaskLinkRateInput : $accountingEntryAmountInput) ?>">
                                            <input type="hidden" name="is_installment" value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>">
                                            <input type="hidden" name="installment_progress" value="<?= e($accountingEntryInstallmentProgress) ?>">
                                            <input type="hidden" name="total_amount_value" value="<?= e($accountingEntryTotalAmountInput) ?>">
                                            <input type="hidden" name="automation_type" value="<?= e($accountingEntryAutomationType) ?>" data-accounting-automation-type>
                                            <?php if ($accountingEntryIsTaskLinked): ?>
                                                <input type="hidden" name="task_link_workspace_id" value="<?= e((string) ($accountingEntryTaskLinkWorkspaceId ?? 0)) ?>">
                                                <input type="hidden" name="task_link_group_name" value="<?= e($accountingEntryTaskLinkGroupName) ?>">
                                                <?= $renderAccountingTaskLinkHiddenAssigneeInputs($accountingEntryTaskLinkAssigneeIds) ?>
                                            <?php endif; ?>
                                            <input type="hidden" name="is_monthly_due" value="<?= (!$accountingEntryIsTaskLinked && $accountingEntryIsMonthly) ? '1' : '0' ?>">
                                            <input type="hidden" name="monthly_day" value="<?= (!$accountingEntryIsTaskLinked && $accountingEntryMonthlyDay !== null) ? e((string) $accountingEntryMonthlyDay) : '' ?>">
                                            <label class="accounting-check">
                                                <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                <span>Recebido</span>
                                            </label>
                                        </form>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir entrada">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                        <form method="post" class="accounting-entry-form accounting-entry-editor-form" data-accounting-form hidden autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input type="hidden" name="entry_type" value="income">
                                            <input type="hidden" name="automation_type" value="<?= e($accountingEntryAutomationType) ?>" data-accounting-automation-type>
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da entrada"
                                                autocomplete="off"
                                                required
                                            >
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryIsTaskLinked ? $accountingEntryTaskLinkRateInput : $accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                inputmode="numeric"
                                                placeholder="0,00"
                                                autocomplete="off"
                                                required
                                                data-accounting-primary-amount
                                                <?= $accountingEntryIsInstallment ? 'readonly' : '' ?>
                                            >
                                            <?php if ($accountingEntryIsTaskLinked || $accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment): ?>
                                                <div class="accounting-entry-meta">
                                                    <?php if ($accountingEntryIsTaskLinked): ?>
                                                        <span class="accounting-entry-badge is-monthly">Por tarefa</span>
                                                    <?php elseif ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <label class="accounting-entry-edit-control is-monthly">
                                                            <span>Mensal -</span>
                                                            <select name="monthly_day" class="accounting-installment-select" aria-label="Dia do recebimento mensal">
                                                                <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                    <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === $accountingEntryMonthlyDay ? 'selected' : '' ?>>
                                                                        <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </label>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($accountingEntryIsTaskLinked): ?>
                                                <?= $renderAccountingTaskLinkFields(
                                                    $accountingEntryTaskLinkWorkspaceId,
                                                    $accountingEntryTaskLinkGroupName,
                                                    $accountingEntryTaskLinkAssigneeIds,
                                                    false,
                                                    false
                                                ) ?>
                                            <?php endif; ?>
                                            <div class="accounting-entry-status">
                                                <label class="accounting-check">
                                                    <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                    <span>Recebido</span>
                                                </label>
                                            </div>
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                            <input type="hidden" name="is_monthly_due" value="<?= (!$accountingEntryIsTaskLinked && $accountingEntryIsMonthly) ? '1' : '0' ?>">
                                            <?php if ($accountingEntryIsTaskLinked || !$accountingEntryIsMonthly): ?>
                                                <input type="hidden" name="monthly_day" value="">
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="income">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova entrada"
                                        autocomplete="off"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        inputmode="numeric"
                                        placeholder="0,00"
                                        autocomplete="off"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <div class="accounting-create-footer">
                                        <div class="accounting-create-meta">
                                            <div class="accounting-entry-options">
                                                <select
                                                    name="accounting_type_choice"
                                                    class="accounting-installment-select accounting-entry-type-select"
                                                    aria-label="Tipo de entrada"
                                                    data-accounting-type-select
                                                >
                                                    <option value="single">&Uacute;nica</option>
                                                    <option value="monthly">Mensal</option>
                                                    <option value="completed_tasks">Por tarefa</option>
                                                </select>
                                                <input
                                                    type="checkbox"
                                                    name="is_installment"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-installment-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <input
                                                    type="checkbox"
                                                    name="is_monthly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-monthly-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="automation_type"
                                                    value="manual"
                                                    data-accounting-automation-type
                                                >
                                                <div class="accounting-installment-fields" data-accounting-installment-fields hidden>
                                                    <select
                                                        name="installment_number"
                                                        class="accounting-installment-select"
                                                        aria-label="Parcela atual"
                                                        data-accounting-installment-number
                                                        disabled
                                                    >
                                                        <option value="1">1</option>
                                                    </select>
                                                    <select
                                                        name="installment_total"
                                                        class="accounting-installment-select"
                                                        aria-label="Total de parcelas"
                                                        data-accounting-installment-total-count
                                                        disabled
                                                    >
                                                        <option value="2">2</option>
                                                    </select>
                                                    <input type="hidden" name="installment_progress" value="" data-accounting-installment-progress disabled>
                                                    <input type="hidden" name="total_amount_value" value="" data-accounting-installment-total-amount disabled>
                                                </div>
                                                <div class="accounting-monthly-fields" data-accounting-monthly-fields hidden>
                                                    <span class="accounting-entry-inline-label">Vencimento</span>
                                                    <select
                                                        name="monthly_day"
                                                        class="accounting-installment-select accounting-monthly-day-select"
                                                        aria-label="Dia do recebimento mensal"
                                                        data-accounting-monthly-day
                                                        disabled
                                                    >
                                                        <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                            <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === 1 ? 'selected' : '' ?>>
                                                                <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <?= $renderAccountingTaskLinkFields(
                                                    $accountingTaskLinkDefaultWorkspaceId,
                                                    $accountingTaskLinkDefaultWorkspaceId !== null
                                                        ? ($accountingTaskLinkGroupsForWorkspace($accountingTaskLinkDefaultWorkspaceId)[0] ?? '')
                                                        : '',
                                                    [],
                                                    true,
                                                    true
                                                ) ?>
                                            </div>
                                        </div>
                                        <div class="accounting-create-actions">
                                            <button type="submit" class="btn btn-mini">Adicionar</button>
                                            <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            </details>

                            <?php
                            $accountingIncomeTotalCents = max(0, (int) ($accountingSummary['income_total_cents'] ?? 0));
                            $accountingIncomeReceivedCents = max(0, (int) ($accountingSummary['income_received_cents'] ?? 0));
                            $accountingHideIncomeReceivedTotal = $accountingIncomeTotalCents > 0
                                && $accountingIncomeReceivedCents >= $accountingIncomeTotalCents;
                            ?>
                            <dl class="accounting-totals is-single">
                                <div class="is-income-total">
                                    <dt>Total</dt>
                                    <dd
                                        class="accounting-total-pair"
                                        aria-label="<?= $accountingHideIncomeReceivedTotal
                                            ? ('Total recebido ' . e((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00')))
                                            : ('Recebido ' . e((string) ($accountingSummary['income_received_display'] ?? 'R$ 0,00')) . ' de ' . e((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00'))) ?>"
                                    >
                                        <?php if (!$accountingHideIncomeReceivedTotal): ?>
                                            <span class="accounting-total-secondary"><?= $renderAccountingMoney((string) ($accountingSummary['income_received_display'] ?? 'R$ 0,00')) ?></span>
                                            <span class="accounting-total-separator">/</span>
                                        <?php endif; ?>
                                        <strong class="accounting-total-main"><?= $renderAccountingMoney((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00')) ?></strong>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </div>

                <section class="accounting-balance-card">
                    <?php
                    $accountingCurrentPeriodResolvedKey = isset($accountingCurrentPeriodKey)
                        ? normalizeAccountingPeriodKey((string) $accountingCurrentPeriodKey)
                        : accountingCycleCurrentPeriodKey((int) ($accountingCycleCloseDay ?? 0));
                    $accountingIsCurrentPeriodView = normalizeAccountingPeriodKey($accountingPeriod) === $accountingCurrentPeriodResolvedKey;
                    $accountingCurrentBalanceLabel = $accountingIsCurrentPeriodView
                        ? 'Saldo atual'
                        : 'Saldo do per&iacute;odo';
                    $accountingCurrentBalanceCents = (int) ($accountingSummary['current_balance_cents'] ?? 0);
                    $accountingCurrentBalanceClass = $accountingCurrentBalanceCents < 0
                        ? ' is-negative'
                        : ($accountingCurrentBalanceCents > 0 ? ' is-positive' : '');
                    $accountingFinalBalanceCents = (int) ($accountingSummary['final_balance_cents'] ?? 0);
                    $accountingFinalBalanceClass = $accountingFinalBalanceCents < 0
                        ? ' is-negative'
                        : ($accountingFinalBalanceCents > 0 ? ' is-positive' : '');
                    $accountingCashProjectionAvailable = !empty($accountingNextIncomeProjection['available']);
                    $accountingCashProjectionBalanceCents = $accountingCashProjectionAvailable
                        ? (int) ($accountingNextIncomeProjection['balance_after_next_income_cents'] ?? 0)
                        : 0;
                    $accountingCashProjectionClass = $accountingCashProjectionBalanceCents < 0
                        ? ' is-negative'
                        : ($accountingCashProjectionBalanceCents > 0 ? ' is-positive' : '');
                    $accountingCashProjectionShortfallCents = $accountingCashProjectionAvailable
                        ? max(0, (int) ($accountingNextIncomeProjection['shortfall_cents'] ?? 0))
                        : 0;
                    ?>
                    <dl class="accounting-balance-values">
                        <div class="is-current<?= e($accountingCurrentBalanceClass) ?>">
                            <dt><?= $accountingCurrentBalanceLabel ?></dt>
                            <dd><?= $renderAccountingMoney((string) ($accountingSummary['current_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                        <div class="is-final is-projected<?= e($accountingFinalBalanceClass) ?>">
                            <dt>Saldo projetado</dt>
                            <dd><?= $renderAccountingMoney((string) ($accountingSummary['final_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                    </dl>
                    <?php if ($accountingCashProjectionAvailable): ?>
                        <div class="accounting-balance-cashflow<?= e($accountingCashProjectionClass) ?>">
                            <div class="accounting-balance-cashflow-copy">
                                <span class="accounting-balance-cashflow-kicker">Saldo ap&oacute;s pr&oacute;xima entrada</span>
                                <strong><?= e((string) ($accountingNextIncomeProjection['next_income_date_display'] ?? '')) ?></strong>
                            </div>
                            <div class="accounting-balance-cashflow-value">
                                <?= $renderAccountingMoney((string) ($accountingNextIncomeProjection['balance_after_next_income_display'] ?? 'R$ 0,00')) ?>
                            </div>
                            <?php if ($accountingCashProjectionShortfallCents > 0): ?>
                                <p class="accounting-balance-cashflow-warning">
                                    Antes dela, pode faltar <?= e((string) ($accountingNextIncomeProjection['shortfall_display'] ?? 'R$ 0,00')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
