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
            $renderAccountingTaskLinkGroupPicker = static function (?int $workspaceId = null, array $selectedGroupNames = [], bool $disabled = false) use ($accountingTaskLinkGroupsForWorkspace): string {
                $groupNames = $accountingTaskLinkGroupsForWorkspace($workspaceId);
                $selectedGroupNames = normalizeAccountingTaskLinkGroupNames($selectedGroupNames);
                if (!$selectedGroupNames) {
                    $selectedGroupNames = $groupNames;
                }
                $selectedLookup = array_fill_keys($selectedGroupNames, true);
                $summaryLabel = count($selectedGroupNames) === count($groupNames)
                    ? 'Todos'
                    : (count($selectedGroupNames) === 1
                        ? (string) $selectedGroupNames[0]
                        : ((string) ($selectedGroupNames[0] ?? 'Projetos') . ' +' . max(0, count($selectedGroupNames) - 1)));
                ob_start();
                ?>
                <div class="assignee-picker-wrap task-detail-inline-field accounting-task-link-picker-wrap accounting-task-link-project-picker-wrap">
                    <span class="assignee-picker-label">Projetos</span>
                    <details class="assignee-picker row-assignee-picker accounting-task-link-project-picker" data-accounting-task-link-groups>
                        <summary><?= e($summaryLabel) ?></summary>
                        <div class="assignee-picker-menu" aria-label="Selecionar projetos" data-sheet-title="Projetos" data-accounting-task-link-group-menu>
                            <?php if (!$groupNames): ?>
                                <p class="assignee-picker-empty">Nenhum projeto dispon&iacute;vel.</p>
                            <?php else: ?>
                                <?php foreach ($groupNames as $groupName): ?>
                                    <label class="assignee-option">
                                        <input
                                            type="checkbox"
                                            name="task_link_group_names[]"
                                            value="<?= e($groupName) ?>"
                                            data-project-name="<?= e($groupName) ?>"
                                            <?= isset($selectedLookup[$groupName]) ? 'checked' : '' ?>
                                            <?= $disabled ? 'disabled' : '' ?>
                                        >
                                        <span class="assignee-option-text"><?= e($groupName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <?php
                return (string) ob_get_clean();
            };
            $accountingTaskLinkAssigneeSummary = static function (?int $workspaceId = null, array $selectedAssigneeIds = []) use ($accountingTaskLinkUsersForWorkspace): string {
                $selectedLookup = array_fill_keys(normalizeAssigneeIds($selectedAssigneeIds), true);
                if (!$selectedLookup) {
                    return 'Todos';
                }

                $selectedNames = [];
                foreach ($accountingTaskLinkUsersForWorkspace($workspaceId) as $workspaceUser) {
                    $workspaceUserId = (int) ($workspaceUser['id'] ?? 0);
                    if ($workspaceUserId <= 0 || !isset($selectedLookup[$workspaceUserId])) {
                        continue;
                    }
                    $selectedNames[] = normalizeUserDisplayName((string) ($workspaceUser['name'] ?? 'Usuário'));
                }

                return $selectedNames ? implode(', ', $selectedNames) : 'Todos';
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
                array $selectedGroupNames = [],
                array $selectedAssigneeIds = [],
                bool $hidden = true,
                bool $disabled = true
            ) use (
                $renderAccountingTaskLinkWorkspaceOptions,
                $renderAccountingTaskLinkGroupOptions,
                $renderAccountingTaskLinkGroupPicker,
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
                    <?= $renderAccountingTaskLinkGroupPicker($workspaceId, $selectedGroupNames, $disabled) ?>
                    <label class="accounting-entry-edit-control" hidden>
                        <span>Projeto</span>
                        <select
                            name="task_link_group_name"
                            class="accounting-installment-select"
                            aria-label="Projeto das tarefas concluídas"
                            data-accounting-task-link-group-legacy
                            <?= $disabled ? 'disabled' : '' ?>
                        >
                            <?= $renderAccountingTaskLinkGroupOptions($workspaceId, $selectedGroupNames[0] ?? null) ?>
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
            $renderAccountingTaskLinkHiddenGroupInputs = static function (array $selectedGroupNames = []): string {
                ob_start();
                foreach (normalizeAccountingTaskLinkGroupNames($selectedGroupNames) as $selectedGroupName) {
                    echo '<input type="hidden" name="task_link_group_names[]" value="' . e($selectedGroupName) . '">';
                }

                return (string) ob_get_clean();
            };
            $renderAccountingEntryTypeControls = static function (
                string $entryType,
                string $selectedType,
                ?int $monthlyDay = null,
                ?int $weeklyDay = null,
                int $installmentNumber = 1,
                int $installmentTotal = 2,
                string $totalAmountInput = '',
                string $startPeriodKey = '',
                string $weeklyStartDate = ''
            ): string {
                $entryType = normalizeAccountingEntryType($entryType);
                $selectedType = normalizeAccountingEntryTypeChoice($entryType, $selectedType);
                $monthlyDay ??= (int) (new DateTimeImmutable('today'))->format('j');
                $weeklyDay ??= (int) (new DateTimeImmutable('today'))->format('N');
                $installmentNumber = max(1, $installmentNumber);
                $installmentTotal = max(2, $installmentTotal);
                $startPeriodKey = preg_match('/^\d{4}-\d{2}$/', $startPeriodKey) ? $startPeriodKey : '';
                $weeklyStartDate = dueDateForStorage($weeklyStartDate) ?? '';
                ob_start();
                ?>
                <div class="accounting-entry-type-controls" data-accounting-entry-type-controls>
                    <select
                        name="accounting_type_choice"
                        class="accounting-installment-select accounting-entry-type-select"
                        aria-label="Tipo do registro"
                        data-accounting-type-select
                    >
                        <option value="single" <?= $selectedType === 'single' ? 'selected' : '' ?>>&Uacute;nica</option>
                        <option value="monthly" <?= $selectedType === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                        <option value="weekly" <?= $selectedType === 'weekly' ? 'selected' : '' ?>>Semanal</option>
                        <?php if ($entryType === 'expense'): ?>
                            <option value="installment" <?= $selectedType === 'installment' ? 'selected' : '' ?>>Parcelada</option>
                            <option value="goal" <?= $selectedType === 'goal' ? 'selected' : '' ?>>Saldo a quitar</option>
                        <?php else: ?>
                            <option value="completed_tasks" <?= $selectedType === 'completed_tasks' ? 'selected' : '' ?>>Por tarefa</option>
                        <?php endif; ?>
                    </select>
                    <input type="checkbox" name="is_installment" value="1" class="accounting-hidden-toggle" data-accounting-installment-toggle tabindex="-1" aria-hidden="true" <?= $selectedType === 'installment' ? 'checked' : '' ?>>
                    <input type="checkbox" name="is_monthly_due" value="1" class="accounting-hidden-toggle" data-accounting-monthly-toggle tabindex="-1" aria-hidden="true" <?= in_array($selectedType, ['monthly', 'goal'], true) ? 'checked' : '' ?>>
                    <input type="checkbox" name="is_weekly_due" value="1" class="accounting-hidden-toggle" data-accounting-weekly-toggle tabindex="-1" aria-hidden="true" <?= $selectedType === 'weekly' ? 'checked' : '' ?>>
                    <input type="hidden" name="monthly_mode" value="<?= $selectedType === 'goal' ? 'goal' : 'uniform' ?>" data-accounting-monthly-mode>
                    <div class="accounting-installment-fields" data-accounting-installment-fields<?= $selectedType === 'installment' ? '' : ' hidden' ?>>
                        <select name="installment_number" class="accounting-installment-select" aria-label="Parcela atual" data-accounting-installment-number>
                            <option value="<?= e((string) $installmentNumber) ?>"><?= e((string) $installmentNumber) ?></option>
                        </select>
                        <span>de</span>
                        <select name="installment_total" class="accounting-installment-select" aria-label="Total de parcelas" data-accounting-installment-total-count>
                            <option value="<?= e((string) $installmentTotal) ?>"><?= e((string) $installmentTotal) ?></option>
                        </select>
                        <input type="hidden" name="installment_progress" value="<?= e($installmentNumber . '/' . $installmentTotal) ?>" data-accounting-installment-progress>
                        <input type="text" name="total_amount_value" value="<?= e($totalAmountInput) ?>" class="accounting-input accounting-input-amount accounting-input-installment-total" inputmode="numeric" placeholder="Total" autocomplete="off" data-accounting-installment-total-amount>
                    </div>
                    <div class="accounting-monthly-fields" data-accounting-monthly-fields<?= $selectedType === 'monthly' ? '' : ' hidden' ?>>
                        <label class="accounting-entry-edit-control" data-accounting-monthly-day-field>
                            <span>Dia</span>
                            <select name="monthly_day" class="accounting-installment-select accounting-monthly-day-select" aria-label="Dia da recorrência mensal" data-accounting-monthly-day>
                                <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                    <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === $monthlyDay ? 'selected' : '' ?>><?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="accounting-entry-edit-control">
                            <span>Inicia em</span>
                            <input
                                type="month"
                                name="recurrence_start_period"
                                value="<?= e($startPeriodKey) ?>"
                                class="accounting-installment-select"
                                aria-label="Mês de início da recorrência mensal"
                                data-accounting-start-period
                            >
                        </label>
                    </div>
                    <div class="accounting-monthly-fields" data-accounting-weekly-fields<?= $selectedType === 'weekly' ? '' : ' hidden' ?>>
                        <label class="accounting-entry-edit-control">
                            <span>Toda</span>
                            <select name="weekly_day" class="accounting-installment-select accounting-monthly-day-select" aria-label="Dia da recorrência semanal" data-accounting-weekly-day>
                                <?php foreach ([1 => 'Segunda', 2 => 'Ter&ccedil;a', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'S&aacute;bado', 7 => 'Domingo'] as $weeklyDayOption => $weeklyDayLabel): ?>
                                    <option value="<?= e((string) $weeklyDayOption) ?>" <?= $weeklyDayOption === $weeklyDay ? 'selected' : '' ?>><?= $weeklyDayLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="accounting-entry-edit-control">
                            <span>Começa em</span>
                            <input
                                type="date"
                                name="weekly_start_date"
                                value="<?= e($weeklyStartDate) ?>"
                                class="accounting-installment-select"
                                aria-label="Data de início da recorrência semanal"
                                data-accounting-weekly-start-date
                            >
                        </label>
                    </div>
                </div>
                <?php
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
            $renderAccountingGroupedEntry = static function (array $group, string $periodKey, string $entryType) use ($renderAccountingMoney): string {
                $displayGroup = (string) ($group['display_group'] ?? '');
                if (!in_array($displayGroup, ['weekly', 'carried'], true)) {
                    return '';
                }

                $isWeekly = $displayGroup === 'weekly';
                $entries = $isWeekly
                    ? (is_array($group['weekly_occurrences'] ?? null) ? $group['weekly_occurrences'] : [])
                    : (is_array($group['carried_entries'] ?? null) ? $group['carried_entries'] : []);
                if (!$entries) {
                    return '';
                }

                $firstEntry = $entries[0];
                $label = $isWeekly
                    ? (string) ($firstEntry['label'] ?? '')
                    : ($entryType === 'income' ? 'Entradas anteriores' : 'Pendências anteriores');
                $count = $isWeekly
                    ? max(1, (int) ($group['weekly_occurrence_count'] ?? count($entries)))
                    : max(1, (int) ($group['carried_count'] ?? count($entries)));
                $totalDisplay = $isWeekly
                    ? (string) ($group['weekly_total_display'] ?? 'R$ 0,00')
                    : (string) ($group['carried_total_display'] ?? 'R$ 0,00');
                $settledCount = max(0, (int) ($group['weekly_settled_count'] ?? 0));
                $typeLabel = $entryType === 'income' ? 'Recebido' : 'Pago';
                $weeklyAmountInput = (string) ($firstEntry['amount_input'] ?? '0,00');
                $weeklyDay = (int) (new DateTimeImmutable((string) ($firstEntry['due_date'] ?? 'today')))->format('N');
                $weeklyStartDate = dueDateForStorage((string) ($firstEntry['weekly_anchor_date'] ?? ''))
                    ?? dueDateForStorage((string) ($firstEntry['due_date'] ?? ''))
                    ?? '';
                ob_start();
                ?>
                <details class="accounting-occurrence-group<?= $isWeekly ? ' is-weekly' : ' is-carried' ?>">
                    <summary>
                        <span class="accounting-occurrence-group-title">
                            <strong><?= e($label) ?></strong>
                            <span><?= $isWeekly ? e($count . ' semanas') : e($count . ' itens') ?></span>
                            <?php if ($isWeekly): ?>
                                <span><?= e($settledCount . '/' . $count . ' ' . strtolower($typeLabel)) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="accounting-occurrence-group-total"><?= $renderAccountingMoney($totalDisplay) ?></span>
                    </summary>
                    <div class="accounting-occurrence-group-body">
                        <?php if ($isWeekly): ?>
                            <form method="post" class="accounting-occurrence-group-edit" data-accounting-form autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="update_accounting_entry">
                                <input type="hidden" name="entry_id" value="<?= e((string) ((int) ($firstEntry['id'] ?? 0))) ?>">
                                <input type="hidden" name="period_key" value="<?= e($periodKey) ?>">
                                <input type="hidden" name="entry_type" value="<?= e($entryType) ?>">
                                <input type="hidden" name="accounting_type_choice" value="weekly">
                                <input type="hidden" name="weekly_day" value="<?= e((string) $weeklyDay) ?>">
                                <input type="hidden" name="preserve_settlement" value="1">
                                <input type="text" name="label" value="<?= e((string) ($firstEntry['label'] ?? '')) ?>" class="accounting-input accounting-input-label" maxlength="120" required>
                                <label class="accounting-occurrence-group-value">
                                    <span>Por semana</span>
                                    <input type="text" name="amount_value" value="<?= e($weeklyAmountInput) ?>" class="accounting-input accounting-input-amount" inputmode="numeric" required>
                                </label>
                                <label class="accounting-occurrence-group-value">
                                    <span>Começa em</span>
                                    <input type="date" name="weekly_start_date" value="<?= e($weeklyStartDate) ?>" class="accounting-input" required>
                                </label>
                                <button type="submit" class="btn btn-mini btn-ghost">Salvar recorrência</button>
                            </form>
                        <?php endif; ?>
                        <div class="accounting-occurrence-list">
                            <?php foreach ($entries as $occurrence): ?>
                                <?php
                                $occurrenceId = (int) ($occurrence['id'] ?? 0);
                                $occurrenceLabel = (string) ($occurrence['label'] ?? '');
                                $occurrenceAmount = (string) ($occurrence['amount_input'] ?? '0,00');
                                $occurrenceDueDate = (string) ($occurrence['due_date_display'] ?? '');
                                $occurrenceSettled = ((int) ($occurrence['is_settled'] ?? 0)) === 1;
                                $occurrenceIsMonthly = ((int) ($occurrence['is_monthly'] ?? 0)) === 1;
                                $occurrenceMonthlyMode = (string) ($occurrence['monthly_mode'] ?? 'uniform');
                                $occurrenceIsGoal = ((int) ($occurrence['is_monthly_goal'] ?? 0)) === 1;
                                $occurrenceMonthlyDay = normalizeDueMonthlyDay($occurrence['monthly_day'] ?? null);
                                $occurrenceWeeklyDay = (int) (new DateTimeImmutable((string) ($occurrence['due_date'] ?? 'today')))->format('N');
                                ?>
                                <form method="post" class="accounting-occurrence-row accounting-entry-quick-status-form" data-accounting-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="update_accounting_entry">
                                    <input type="hidden" name="entry_id" value="<?= e((string) $occurrenceId) ?>">
                                    <input type="hidden" name="period_key" value="<?= e($periodKey) ?>">
                                    <input type="hidden" name="entry_type" value="<?= e($entryType) ?>">
                                    <input type="hidden" name="label" value="<?= e($occurrenceLabel) ?>">
                                    <input type="hidden" name="amount_value" value="<?= e($occurrenceAmount) ?>">
                                    <input type="hidden" name="entry_date" value="<?= e((string) ($occurrence['due_date'] ?? '')) ?>">
                                    <?php if (!$isWeekly && $occurrenceIsMonthly): ?>
                                        <input type="hidden" name="is_monthly_due" value="1">
                                        <input type="hidden" name="monthly_mode" value="<?= e($occurrenceMonthlyMode) ?>">
                                        <input type="hidden" name="monthly_day" value="<?= e((string) ($occurrenceMonthlyDay ?? '')) ?>">
                                    <?php endif; ?>
                                    <?php if ($isWeekly): ?>
                                        <input type="hidden" name="accounting_type_choice" value="weekly">
                                        <input type="hidden" name="weekly_day" value="<?= e((string) $occurrenceWeeklyDay) ?>">
                                    <?php endif; ?>
                                    <span class="accounting-occurrence-row-label"><?= e($isWeekly ? ($occurrenceDueDate !== '' ? $occurrenceDueDate : 'Semana') : $occurrenceLabel) ?></span>
                                    <span class="accounting-occurrence-row-value"><?= $renderAccountingMoney($occurrenceAmount) ?></span>
                                    <?php if ($occurrenceIsGoal): ?>
                                        <span class="accounting-entry-badge is-progress">Saldo a quitar</span>
                                    <?php else: ?>
                                        <label class="accounting-check">
                                            <input type="checkbox" name="is_settled" value="1" <?= $occurrenceSettled ? 'checked' : '' ?>>
                                            <span><?= e($typeLabel) ?></span>
                                        </label>
                                    <?php endif; ?>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
                <?php
                return (string) ob_get_clean();
            };
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
                                    <?php $accountingDisplayGroup = (string) ($accountingEntry['display_group'] ?? ''); ?>
                                    <?php if ($accountingDisplayGroup === 'weekly'): ?>
                                        <?= $renderAccountingGroupedEntry($accountingEntry, $accountingPeriod, 'expense') ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php if ($accountingDisplayGroup === 'carried_header'): ?>
                                        <details class="accounting-occurrence-group is-carried">
                                            <summary>
                                                <span class="accounting-occurrence-group-title">
                                                    <strong>Pend&ecirc;ncias anteriores</strong>
                                                    <span><?= e((string) ((int) ($accountingEntry['carried_count'] ?? 0))) ?> itens</span>
                                                </span>
                                                <span class="accounting-occurrence-group-total"><?= $renderAccountingMoney((string) ($accountingEntry['carried_total_display'] ?? 'R$ 0,00')) ?></span>
                                            </summary>
                                            <div class="accounting-occurrence-group-body">
                                                <div class="accounting-occurrence-list accounting-carried-entry-list">
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php if ($accountingDisplayGroup === 'carried_footer'): ?>
                                                </div>
                                            </div>
                                        </details>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsBalanceAdjustment = ((int) ($accountingEntry['is_balance_adjustment'] ?? 0)) === 1;
                                    $accountingEntryIsForecastCarry = ((int) ($accountingEntry['is_forecast_carry'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsCarried = ((int) ($accountingEntry['is_carried'] ?? 0)) === 1;
                                    $accountingEntrySourceDueId = (int) ($accountingEntry['source_due_entry_id'] ?? 0);
                                    $accountingEntryIsMonthlyDue = $accountingEntrySourceDueId > 0;
                                    $accountingEntryIsMonthlyGoal = ((int) ($accountingEntry['is_monthly_goal'] ?? 0)) === 1;
                                    $accountingEntryIsWeekly = ((int) ($accountingEntry['is_weekly'] ?? 0)) === 1;
                                    $accountingEntryTypeChoice = workspaceAccountingEntryTypeChoice($accountingEntry);
                                    $accountingEntryWeeklyDay = $accountingEntryIsWeekly
                                        ? (int) (new DateTimeImmutable((string) ($accountingEntry['due_date'] ?? 'today')))->format('N')
                                        : (int) (new DateTimeImmutable('today'))->format('N');
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
                                    $accountingEntryWeeklyBadge = $accountingEntryIsWeekly
                                        ? ('Semanal - ' . accountingWeeklyDayLabel(
                                            (new DateTimeImmutable((string) ($accountingEntry['due_date'] ?? 'today')))->format('N'),
                                            true
                                        ))
                                        : '';
                                    $accountingEntryIsOverdue = ((int) ($accountingEntry['is_overdue'] ?? 0)) === 1;
                                    $accountingEntryDueDateBadge = $accountingEntryDueDateDisplay !== ''
                                        && $accountingEntryIsCarried
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
                                    $accountingEntryDiscounts = is_array($accountingEntry['discounts'] ?? null)
                                        ? $accountingEntry['discounts']
                                        : [];
                                    $accountingEntrySupportsDiscounts = ((int) ($accountingEntry['supports_discounts'] ?? 0)) === 1;
                                    $accountingEntryTotalCents = max(0, (int) ($accountingEntry['amount_cents'] ?? 0));
                                    $accountingEntryDiscountTotalCents = max(0, (int) ($accountingEntry['discount_total_cents'] ?? 0));
                                    $accountingEntryShowDiscountProgress = $accountingEntryDiscountTotalCents > 0;
                                    $accountingEntryDiscountTotalDisplay = (string) ($accountingEntry['discount_total_display'] ?? 'R$ 0,00');
                                    $accountingEntryDiscountTotalCompact = dueAmountCompactLabelFromCents($accountingEntryDiscountTotalCents, true);
                                    $accountingEntryTotalCompact = dueAmountCompactLabelFromCents($accountingEntryTotalCents, true);
                                    $accountingEntryDiscountProgressPercent = $accountingEntryTotalCents > 0
                                        ? min(100, max(0, ($accountingEntryDiscountTotalCents / $accountingEntryTotalCents) * 100))
                                        : 0;
                                    $accountingEntryDiscountProgressWidth = number_format($accountingEntryDiscountProgressPercent, 2, '.', '');
                                    $accountingEntryDiscountIsComplete = $accountingEntryTotalCents > 0
                                        && $accountingEntryDiscountTotalCents >= $accountingEntryTotalCents;
                                    $accountingEntryDiscountRemainingCents = max(0, (int) ($accountingEntry['discount_remaining_cents'] ?? 0));
                                    $accountingEntryDiscountRemainingDisplay = (string) ($accountingEntry['discount_remaining_display'] ?? $accountingEntryAmountInput);
                                    $accountingEntryShowDiscountSummaryProgress = $accountingEntryShowDiscountProgress
                                        && !$accountingEntryIsSettled;
                                    if ($accountingEntryIsForecastCarry):
                                    ?>
                                        <div class="accounting-entry-row is-forecast-carry">
                                            <div class="accounting-entry-summary" aria-label="Pendência prevista <?= e($accountingEntryLabel) ?>, <?= e($accountingEntryAmountInput) ?>">
                                                <span class="accounting-entry-summary-main">
                                                    <span class="accounting-entry-summary-head">
                                                        <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                        <span class="accounting-entry-summary-meta">
                                                            <span class="accounting-entry-badge is-forecast-carry">Previsto</span>
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="accounting-entry-summary-amount"><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                            </div>
                                        </div>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <div class="accounting-entry-row<?= $accountingEntryIsMonthlyGoal ? ' is-goal-entry' : '' ?><?= $accountingEntryIsBalanceAdjustment ? ' is-balance-adjustment' : '' ?>">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                    <?php if ($accountingEntryShowDiscountSummaryProgress): ?>
                                                        <span
                                                            class="accounting-entry-discount-payment-progress is-summary"
                                                            aria-label="Pago via abatimentos <?= e($accountingEntryDiscountTotalDisplay) ?> de <?= e($accountingEntryAmountInput) ?>"
                                                        >
                                                            <span class="accounting-entry-discount-payment-progress-fill" style="width: <?= e($accountingEntryDiscountProgressWidth) ?>%"></span>
                                                            <span class="accounting-entry-discount-payment-progress-values">
                                                                <span><?= e($accountingEntryDiscountTotalCompact) ?></span>
                                                                <span aria-hidden="true">/</span>
                                                                <strong><?= e($accountingEntryTotalCompact) ?></strong>
                                                            </span>
                                                        </span>
                                                    <?php endif; ?>
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
                                                    <?php elseif ($accountingEntryIsBalanceAdjustment || $accountingEntryMonthlyBadge !== '' || $accountingEntryWeeklyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge || $accountingEntryDueDateBadge !== '' || $accountingEntryIsOverdue): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryIsBalanceAdjustment): ?>
                                                                <span class="accounting-entry-badge is-balance-adjustment">Ajuste</span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryWeeklyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-weekly"><?= e($accountingEntryWeeklyBadge) ?></span>
                                                            <?php elseif ($accountingEntryIsInstallment): ?>
                                                                <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryShowPendingBadge): ?>
                                                                <span class="accounting-entry-badge is-pending">Pendente</span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryDueDateBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-due-date"><?= e($accountingEntryDueDateBadge) ?></span>
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
                                                <input type="hidden" name="entry_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>">
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
                                            <?php if (!$accountingEntryDiscounts && !$accountingEntryIsInstallment && !$accountingEntryIsMonthlyGoal && $accountingEntryMonthlyBadge === '' && $accountingEntryWeeklyBadge === ''): ?>
                                                <label class="accounting-entry-date-field" title="Organize esta conta na semana em que ela ocorreu.">
                                                    <span>Data</span>
                                                    <input type="date" name="entry_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>" class="accounting-installment-select" aria-label="Data da conta">
                                                </label>
                                            <?php endif; ?>
                                            <?= $renderAccountingEntryTypeControls(
                                                'expense',
                                                $accountingEntryTypeChoice,
                                                $accountingEntryMonthlyDay,
                                                $accountingEntryWeeklyDay,
                                                max(1, (int) ($accountingEntry['installment_number'] ?? 1)),
                                                max(2, (int) ($accountingEntry['installment_total'] ?? 2)),
                                                $accountingEntryTotalAmountInput,
                                                (string) ($accountingEntry['period_key'] ?? $accountingPeriod),
                                                (string) ($accountingEntry['weekly_anchor_date'] ?? $accountingEntry['due_date'] ?? '')
                                            ) ?>
                                            <?php if ($accountingEntryIsMonthlyGoal || $accountingEntryMonthlyBadge !== '' || $accountingEntryWeeklyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge || $accountingEntryShowDiscountProgress): ?>
                                                <div class="accounting-entry-meta<?= $accountingEntryShowDiscountProgress ? ' has-discount-progress' : '' ?>">
                                                    <?php if ($accountingEntryShowDiscountProgress): ?>
                                                        <span
                                                            class="accounting-entry-discount-payment-progress<?= $accountingEntryDiscountIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Pago via abatimentos <?= e($accountingEntryDiscountTotalDisplay) ?> de <?= e($accountingEntryAmountInput) ?>"
                                                        >
                                                            <span class="accounting-entry-discount-payment-progress-fill" style="width: <?= e($accountingEntryDiscountProgressWidth) ?>%"></span>
                                                            <span class="accounting-entry-discount-payment-progress-values">
                                                                <span><?= e($accountingEntryDiscountTotalCompact) ?></span>
                                                                <span aria-hidden="true">/</span>
                                                                <strong><?= e($accountingEntryTotalCompact) ?></strong>
                                                            </span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <span class="accounting-entry-badge is-monthly">Mensal</span>
                                                    <?php elseif ($accountingEntryWeeklyBadge !== ''): ?>
                                                        <span class="accounting-entry-badge is-weekly"><?= e($accountingEntryWeeklyBadge) ?></span>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryShowPendingBadge): ?>
                                                        <span class="accounting-entry-badge is-pending">Pendente</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="accounting-entry-status" data-accounting-settled-check>
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
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                        </form>
                                        <?php if ($accountingEntrySupportsSubitems || $accountingEntrySupportsDiscounts): ?>
                                            <div class="accounting-entry-detail-actions">
                                                <?php if ($accountingEntrySupportsSubitems): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-mini btn-ghost"
                                                        data-accounting-entry-panel-toggle="subitems"
                                                        aria-expanded="false"
                                                    >Subitens</button>
                                                <?php endif; ?>
                                                <?php if ($accountingEntrySupportsDiscounts): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-mini btn-ghost"
                                                        data-accounting-entry-panel-toggle="discounts"
                                                        aria-expanded="false"
                                                    >Abater</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($accountingEntrySupportsSubitems): ?>
                                            <div class="accounting-entry-subitems-panel" data-accounting-entry-panel="subitems">
                                                <div class="accounting-entry-subitems-head">
                                                    <strong>Subitens</strong>
                                                    <span><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                                </div>
                                                <div class="accounting-entry-subitems-list" data-accounting-subitems-list>
                                                    <?php if ($accountingEntrySubitems): ?>
                                                        <?php foreach ($accountingEntrySubitems as $accountingSubitem): ?>
                                                            <?php
                                                            $accountingSubitemId = (int) ($accountingSubitem['id'] ?? 0);
                                                            $accountingSubitemLabel = (string) ($accountingSubitem['label'] ?? '');
                                                            $accountingSubitemAmountInput = (string) ($accountingSubitem['amount_input'] ?? 'R$ 0,00');
                                                            $accountingSubitemDate = (string) ($accountingSubitem['due_date'] ?? $accountingEntry['due_date'] ?? '');
                                                            ?>
                                                            <div class="accounting-entry-subitem-row" data-accounting-subitem-row>
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
                                                                    <input type="date" name="subitem_date" value="<?= e($accountingSubitemDate) ?>" class="accounting-installment-select" aria-label="Data do subitem">
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
                                                    <?php endif; ?>
                                                </div>
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
                                                    <input type="date" name="subitem_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>" class="accounting-installment-select" aria-label="Data do subitem">
                                                    <button type="submit" class="btn btn-mini">+</button>
                                                </form>
                                                <form method="post" class="accounting-entry-subitem-statuses-form" data-accounting-subitem-statuses-form>
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_accounting_subitem_statuses">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="hidden" name="subitem_statuses_json" value="[]" data-accounting-subitem-statuses-json>
                                                    <input type="hidden" name="create_subitems_json" value="[]" data-accounting-pending-subitems-json>
                                                    <span class="accounting-entry-subitem-statuses-note" data-accounting-subitem-statuses-note hidden>Altera&ccedil;&otilde;es n&atilde;o salvas</span>
                                                    <button type="submit" class="btn btn-mini" data-accounting-subitem-statuses-confirm disabled>Confirmar subitens</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($accountingEntrySupportsDiscounts): ?>
                                            <div
                                                class="accounting-entry-discounts-panel"
                                                data-accounting-entry-panel="discounts"
                                                data-accounting-discount-remaining-cents="<?= e((string) $accountingEntryDiscountRemainingCents) ?>"
                                            >
                                                <div class="accounting-entry-discounts-head">
                                                    <strong>Abatimentos</strong>
                                                    <span>Falta <?= $renderAccountingMoney($accountingEntryDiscountRemainingDisplay) ?></span>
                                                </div>
                                                <div class="accounting-entry-discounts-list" data-accounting-discounts-list>
                                                    <?php if ($accountingEntryDiscounts): ?>
                                                        <?php foreach ($accountingEntryDiscounts as $accountingDiscount): ?>
                                                            <?php
                                                            $accountingDiscountId = (int) ($accountingDiscount['id'] ?? 0);
                                                            $accountingDiscountAmountDisplay = (string) ($accountingDiscount['amount_display'] ?? 'R$ 0,00');
                                                            ?>
                                                            <div class="accounting-entry-discount-row">
                                                            <span>- <?= $renderAccountingMoney($accountingDiscountAmountDisplay) ?><?= !empty($accountingDiscount['due_date']) ? ' · ' . e(accountingDateCompactLabel((string) $accountingDiscount['due_date'])) : '' ?></span>
                                                                <form method="post" class="accounting-entry-discount-delete-form">
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="delete_accounting_discount">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="discount_id" value="<?= e((string) $accountingDiscountId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <button type="submit" class="accounting-entry-subitem-delete" aria-label="Remover abatimento">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="post" class="accounting-entry-discount-add-form" autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_discount">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input
                                                        type="text"
                                                        name="discount_amount_value"
                                                        class="accounting-input accounting-input-amount"
                                                        inputmode="numeric"
                                                        placeholder="<?= $accountingEntryDiscountRemainingCents > 0 ? '0,00' : 'Quitado' ?>"
                                                        autocomplete="off"
                                                        aria-label="Valor do abatimento"
                                                        <?= $accountingEntryDiscountRemainingCents > 0 ? '' : 'disabled' ?>
                                                        required
                                                    >
                                                    <input type="date" name="discount_date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" class="accounting-installment-select" aria-label="Data do abatimento">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-mini"
                                                        data-accounting-discount-add-button
                                                        <?= $accountingEntryDiscountRemainingCents > 0 ? '' : 'disabled' ?>
                                                    >+</button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-mini btn-ghost accounting-entry-discount-settle-button"
                                                        data-accounting-discount-settle-remaining
                                                        title="Adicionar o valor restante"
                                                        <?= $accountingEntryDiscountRemainingCents > 0 ? '' : 'disabled' ?>
                                                    >Quitar</button>
                                                </form>
                                                <form method="post" class="accounting-entry-discount-confirm-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_discount">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="hidden" name="discounts_json" value="[]" data-accounting-pending-discounts-json>
                                                    <span class="accounting-entry-discount-confirm-note" data-accounting-discount-confirm-note hidden>Altera&ccedil;&otilde;es n&atilde;o salvas</span>
                                                    <button type="submit" class="btn btn-mini" data-accounting-discount-confirm disabled>Confirmar abatimentos</button>
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
                                                    <option value="weekly">Semanal</option>
                                                    <option value="goal">Saldo a quitar</option>
                                                </select>
                                                <label class="accounting-entry-date-field" title="Opcional: use quando esta conta se refere a outra data.">
                                                    <span>Data</span>
                                                    <input type="date" name="entry_date" class="accounting-installment-select" aria-label="Data da conta (opcional)">
                                                </label>
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
                                                    type="checkbox"
                                                    name="is_weekly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-weekly-toggle
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
                                                <div class="accounting-monthly-fields" data-accounting-weekly-fields hidden>
                                                    <div class="accounting-monthly-day-field">
                                                        <span class="accounting-entry-inline-label">Toda</span>
                                                        <select
                                                            name="weekly_day"
                                                            class="accounting-installment-select accounting-monthly-day-select"
                                                            aria-label="Dia da recorr&ecirc;ncia semanal"
                                                            data-accounting-weekly-day
                                                            disabled
                                                        >
                                                            <?php foreach ([1 => 'Segunda', 2 => 'Ter&ccedil;a', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'S&aacute;bado', 7 => 'Domingo'] as $weeklyDayOption => $weeklyDayLabel): ?>
                                                                <option value="<?= e((string) $weeklyDayOption) ?>" <?= $weeklyDayOption === (int) (new DateTimeImmutable('today'))->format('N') ? 'selected' : '' ?>><?= $weeklyDayLabel ?></option>
                                                            <?php endforeach; ?>
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
                                    <?php if (in_array((string) ($accountingEntry['display_group'] ?? ''), ['weekly', 'carried'], true)): ?>
                                        <?= $renderAccountingGroupedEntry($accountingEntry, $accountingPeriod, 'income') ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryIsBalanceAdjustment = ((int) ($accountingEntry['is_balance_adjustment'] ?? 0)) === 1;
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
                                    $accountingEntryTaskLinkGroupNames = normalizeAccountingTaskLinkGroupNames(
                                        $accountingEntry['task_link_group_names'] ?? null,
                                        $accountingEntryTaskLinkGroupName
                                    );
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
                                    $accountingEntryIsWeekly = ((int) ($accountingEntry['is_weekly'] ?? 0)) === 1;
                                    $accountingEntryTypeChoice = workspaceAccountingEntryTypeChoice($accountingEntry);
                                    $accountingEntryWeeklyDay = $accountingEntryIsWeekly
                                        ? (int) (new DateTimeImmutable((string) ($accountingEntry['due_date'] ?? 'today')))->format('N')
                                        : (int) (new DateTimeImmutable('today'))->format('N');
                                    $accountingEntryMonthlyDay = normalizeDueMonthlyDay($accountingEntry['monthly_day'] ?? null);
                                    $accountingEntryMonthlyBadge = $accountingEntryIsMonthly && $accountingEntryMonthlyDay !== null
                                        ? ('Mensal - ' . str_pad((string) $accountingEntryMonthlyDay, 2, '0', STR_PAD_LEFT))
                                        : '';
                                    $accountingEntryWeeklyBadge = $accountingEntryIsWeekly
                                        ? ('Semanal - ' . accountingWeeklyDayLabel(
                                            (new DateTimeImmutable((string) ($accountingEntry['due_date'] ?? 'today')))->format('N'),
                                            true
                                        ))
                                        : '';
                                    $accountingEntrySubitems = is_array($accountingEntry['subitems'] ?? null)
                                        ? $accountingEntry['subitems']
                                        : [];
                                    $accountingEntryHasSubitems = !empty($accountingEntrySubitems);
                                    $accountingEntrySupportsSubitems = ((int) ($accountingEntry['supports_subitems'] ?? 0)) === 1;
                                    $accountingEntryReceipts = is_array($accountingEntry['discounts'] ?? null)
                                        ? $accountingEntry['discounts']
                                        : [];
                                    $accountingEntrySupportsReceipts = ((int) ($accountingEntry['supports_discounts'] ?? 0)) === 1;
                                    $accountingEntryTotalCents = max(0, (int) ($accountingEntry['amount_cents'] ?? 0));
                                    $accountingEntryReceivedCents = max(0, (int) ($accountingEntry['discount_total_cents'] ?? 0));
                                    $accountingEntryShowReceiptProgress = $accountingEntryReceivedCents > 0;
                                    $accountingEntryReceivedDisplay = (string) ($accountingEntry['discount_total_display'] ?? 'R$ 0,00');
                                    $accountingEntryReceivedCompact = dueAmountCompactLabelFromCents($accountingEntryReceivedCents, true);
                                    $accountingEntryTotalCompact = dueAmountCompactLabelFromCents($accountingEntryTotalCents, true);
                                    $accountingEntryReceiptProgressPercent = $accountingEntryTotalCents > 0
                                        ? min(100, max(0, ($accountingEntryReceivedCents / $accountingEntryTotalCents) * 100))
                                        : 0;
                                    $accountingEntryReceiptProgressWidth = number_format($accountingEntryReceiptProgressPercent, 2, '.', '');
                                    $accountingEntryReceiptIsComplete = $accountingEntryTotalCents > 0
                                        && $accountingEntryReceivedCents >= $accountingEntryTotalCents;
                                    $accountingEntryReceiptRemainingCents = max(0, (int) ($accountingEntry['discount_remaining_cents'] ?? 0));
                                    $accountingEntryReceiptRemainingDisplay = (string) ($accountingEntry['discount_remaining_display'] ?? $accountingEntryAmountInput);
                                    $accountingEntryShowReceiptSummaryProgress = $accountingEntryShowReceiptProgress
                                        && !$accountingEntryIsSettled;
                                    ?>
                                    <div class="accounting-entry-row<?= $accountingEntryIsBalanceAdjustment ? ' is-balance-adjustment' : '' ?>">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                    <?php if ($accountingEntryShowReceiptSummaryProgress): ?>
                                                        <span
                                                            class="accounting-entry-discount-payment-progress is-summary is-income-progress"
                                                            aria-label="Recebido <?= e($accountingEntryReceivedDisplay) ?> de <?= e($accountingEntryAmountInput) ?>"
                                                        >
                                                            <span class="accounting-entry-discount-payment-progress-fill" style="width: <?= e($accountingEntryReceiptProgressWidth) ?>%"></span>
                                                            <span class="accounting-entry-discount-payment-progress-values">
                                                                <span><?= e($accountingEntryReceivedCompact) ?></span>
                                                                <span aria-hidden="true">/</span>
                                                                <strong><?= e($accountingEntryTotalCompact) ?></strong>
                                                            </span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryIsBalanceAdjustment || $accountingEntryIsTaskLinked || $accountingEntryMonthlyBadge !== '' || $accountingEntryWeeklyBadge !== '' || $accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryIsBalanceAdjustment): ?>
                                                                <span class="accounting-entry-badge is-balance-adjustment">Ajuste</span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryIsTaskLinked): ?>
                                                                <span class="accounting-entry-badge is-monthly">Por tarefa</span>
                                                            <?php elseif ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryWeeklyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-weekly"><?= e($accountingEntryWeeklyBadge) ?></span>
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
                                            <input type="hidden" name="entry_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>">
                                            <input type="hidden" name="is_installment" value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>">
                                            <input type="hidden" name="installment_progress" value="<?= e($accountingEntryInstallmentProgress) ?>">
                                            <input type="hidden" name="total_amount_value" value="<?= e($accountingEntryTotalAmountInput) ?>">
                                            <input type="hidden" name="automation_type" value="<?= e($accountingEntryAutomationType) ?>" data-accounting-automation-type>
                                            <?php if ($accountingEntryIsTaskLinked): ?>
                                                <input type="hidden" name="task_link_workspace_id" value="<?= e((string) ($accountingEntryTaskLinkWorkspaceId ?? 0)) ?>">
                                                <input type="hidden" name="task_link_group_name" value="<?= e($accountingEntryTaskLinkGroupName) ?>">
                                                <?= $renderAccountingTaskLinkHiddenGroupInputs($accountingEntryTaskLinkGroupNames) ?>
                                                <?= $renderAccountingTaskLinkHiddenAssigneeInputs($accountingEntryTaskLinkAssigneeIds) ?>
                                            <?php endif; ?>
                                            <input type="hidden" name="is_monthly_due" value="<?= (!$accountingEntryIsTaskLinked && $accountingEntryIsMonthly) ? '1' : '0' ?>">
                                            <input type="hidden" name="monthly_day" value="<?= (!$accountingEntryIsTaskLinked && $accountingEntryMonthlyDay !== null) ? e((string) $accountingEntryMonthlyDay) : '' ?>">
                                            <label class="accounting-check">
                                                <input
                                                    type="checkbox"
                                                    <?= $accountingEntryHasSubitems ? '' : 'name="is_settled" value="1"' ?>
                                                    <?= $accountingEntryIsSettled ? 'checked' : '' ?>
                                                    <?= $accountingEntryHasSubitems ? 'disabled aria-disabled="true"' : '' ?>
                                                >
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
                                                <?= ($accountingEntryIsInstallment || $accountingEntryHasSubitems) ? 'readonly' : '' ?>
                                            >
                                            <?php if (!$accountingEntryReceipts && !$accountingEntryIsInstallment && $accountingEntryMonthlyBadge === '' && $accountingEntryWeeklyBadge === ''): ?>
                                                <label class="accounting-entry-date-field" title="Organize esta entrada na semana em que ela ocorreu.">
                                                    <span>Data</span>
                                                    <input type="date" name="entry_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>" class="accounting-installment-select" aria-label="Data da entrada">
                                                </label>
                                            <?php endif; ?>
                                            <?= $renderAccountingEntryTypeControls(
                                                'income',
                                                $accountingEntryTypeChoice,
                                                $accountingEntryMonthlyDay,
                                                $accountingEntryWeeklyDay,
                                                1,
                                                2,
                                                $accountingEntryTotalAmountInput,
                                                (string) ($accountingEntry['period_key'] ?? $accountingPeriod),
                                                (string) ($accountingEntry['weekly_anchor_date'] ?? $accountingEntry['due_date'] ?? '')
                                            ) ?>
                                            <?php if ($accountingEntryIsTaskLinked || $accountingEntryMonthlyBadge !== '' || $accountingEntryWeeklyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowReceiptProgress): ?>
                                                <div class="accounting-entry-meta<?= $accountingEntryShowReceiptProgress ? ' has-discount-progress' : '' ?>">
                                                    <?php if ($accountingEntryShowReceiptProgress): ?>
                                                        <span
                                                            class="accounting-entry-discount-payment-progress is-income-progress<?= $accountingEntryReceiptIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Recebido <?= e($accountingEntryReceivedDisplay) ?> de <?= e($accountingEntryAmountInput) ?>"
                                                        >
                                                            <span class="accounting-entry-discount-payment-progress-fill" style="width: <?= e($accountingEntryReceiptProgressWidth) ?>%"></span>
                                                            <span class="accounting-entry-discount-payment-progress-values">
                                                                <span><?= e($accountingEntryReceivedCompact) ?></span>
                                                                <span aria-hidden="true">/</span>
                                                                <strong><?= e($accountingEntryTotalCompact) ?></strong>
                                                            </span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryIsTaskLinked): ?>
                                                        <span class="accounting-entry-badge is-monthly">Por tarefa</span>
                                                    <?php elseif ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <span class="accounting-entry-badge is-monthly">Mensal</span>
                                                    <?php elseif ($accountingEntryWeeklyBadge !== ''): ?>
                                                        <span class="accounting-entry-badge is-weekly"><?= e($accountingEntryWeeklyBadge) ?></span>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?= $renderAccountingTaskLinkFields(
                                                $accountingEntryTaskLinkWorkspaceId,
                                                $accountingEntryTaskLinkGroupNames,
                                                $accountingEntryTaskLinkAssigneeIds,
                                                !$accountingEntryIsTaskLinked,
                                                !$accountingEntryIsTaskLinked
                                            ) ?>
                                            <div class="accounting-entry-status" data-accounting-settled-check>
                                                <label class="accounting-check">
                                                    <input
                                                        type="checkbox"
                                                        <?= $accountingEntryHasSubitems ? '' : 'name="is_settled" value="1"' ?>
                                                        <?= $accountingEntryIsSettled ? 'checked' : '' ?>
                                                        <?= $accountingEntryHasSubitems ? 'disabled aria-disabled="true"' : '' ?>
                                                    >
                                                    <span>Recebido</span>
                                                </label>
                                            </div>
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                        </form>
                                        <?php if ($accountingEntrySupportsSubitems || $accountingEntrySupportsReceipts): ?>
                                            <div class="accounting-entry-detail-actions">
                                                <?php if ($accountingEntrySupportsSubitems): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-mini btn-ghost"
                                                        data-accounting-entry-panel-toggle="subitems"
                                                        aria-expanded="false"
                                                    >Subitens</button>
                                                <?php endif; ?>
                                                <?php if ($accountingEntrySupportsReceipts): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-mini btn-ghost"
                                                        data-accounting-entry-panel-toggle="discounts"
                                                        aria-expanded="false"
                                                    >Receber</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($accountingEntrySupportsSubitems): ?>
                                            <div class="accounting-entry-subitems-panel" data-accounting-entry-panel="subitems">
                                                <div class="accounting-entry-subitems-head">
                                                    <strong>Subitens</strong>
                                                    <span><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                                </div>
                                                <div class="accounting-entry-subitems-list" data-accounting-subitems-list>
                                                    <?php foreach ($accountingEntrySubitems as $accountingSubitem): ?>
                                                        <?php
                                                        $accountingSubitemId = (int) ($accountingSubitem['id'] ?? 0);
                                                        $accountingSubitemLabel = (string) ($accountingSubitem['label'] ?? '');
                                                        $accountingSubitemAmountInput = (string) ($accountingSubitem['amount_input'] ?? 'R$ 0,00');
                                                        $accountingSubitemDate = (string) ($accountingSubitem['due_date'] ?? $accountingEntry['due_date'] ?? '');
                                                        ?>
                                                        <div class="accounting-entry-subitem-row" data-accounting-subitem-row>
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
                                                            <form method="post" class="accounting-entry-subitem-form" data-accounting-subitem-form autocomplete="off" hidden>
                                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                <input type="hidden" name="action" value="update_accounting_subitem">
                                                                <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                <input type="hidden" name="subitem_id" value="<?= e((string) $accountingSubitemId) ?>">
                                                                <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                <input type="text" name="subitem_label" value="<?= e($accountingSubitemLabel) ?>" maxlength="120" class="accounting-input accounting-input-label" autocomplete="off" required>
                                                                <input type="text" name="subitem_amount_value" value="<?= e($accountingSubitemAmountInput) ?>" class="accounting-input accounting-input-amount" inputmode="numeric" autocomplete="off" required>
                                                                <input type="date" name="subitem_date" value="<?= e($accountingSubitemDate) ?>" class="accounting-installment-select" aria-label="Data do subitem">
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
                                                                <button type="submit" class="accounting-entry-subitem-delete" aria-label="Remover subitem"><span aria-hidden="true">&times;</span></button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <form method="post" class="accounting-entry-subitem-add-form" data-accounting-subitem-form autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="create_accounting_subitem">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="text" name="subitem_label" maxlength="120" class="accounting-input accounting-input-label" placeholder="Subitem" autocomplete="off">
                                                    <input type="text" name="subitem_amount_value" class="accounting-input accounting-input-amount" inputmode="numeric" placeholder="0,00" autocomplete="off" required>
                                                    <input type="date" name="subitem_date" value="<?= e((string) ($accountingEntry['due_date'] ?? '')) ?>" class="accounting-installment-select" aria-label="Data do subitem">
                                                    <button type="submit" class="btn btn-mini">+</button>
                                                </form>
                                                <form method="post" class="accounting-entry-subitem-statuses-form" data-accounting-subitem-statuses-form>
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_accounting_subitem_statuses">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="hidden" name="subitem_statuses_json" value="[]" data-accounting-subitem-statuses-json>
                                                    <input type="hidden" name="create_subitems_json" value="[]" data-accounting-pending-subitems-json>
                                                    <span class="accounting-entry-subitem-statuses-note" data-accounting-subitem-statuses-note hidden>Altera&ccedil;&otilde;es n&atilde;o salvas</span>
                                                    <button type="submit" class="btn btn-mini" data-accounting-subitem-statuses-confirm disabled>Confirmar subitens</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($accountingEntrySupportsReceipts): ?>
                                            <div
                                                class="accounting-entry-discounts-panel is-receipts"
                                                data-accounting-entry-panel="discounts"
                                                data-accounting-adjustment-kind="receipt"
                                                data-accounting-discount-remaining-cents="<?= e((string) $accountingEntryReceiptRemainingCents) ?>"
                                            >
                                                <div class="accounting-entry-discounts-head">
                                                    <strong>Recebimentos</strong>
                                                    <span>Falta <?= $renderAccountingMoney($accountingEntryReceiptRemainingDisplay) ?></span>
                                                </div>
                                                <div class="accounting-entry-discounts-list" data-accounting-discounts-list>
                                                    <?php foreach ($accountingEntryReceipts as $accountingReceipt): ?>
                                                        <?php
                                                        $accountingReceiptId = (int) ($accountingReceipt['id'] ?? 0);
                                                        $accountingReceiptAmountDisplay = (string) ($accountingReceipt['amount_display'] ?? 'R$ 0,00');
                                                        ?>
                                                        <div class="accounting-entry-discount-row">
                                                            <span>+ <?= $renderAccountingMoney($accountingReceiptAmountDisplay) ?><?= !empty($accountingReceipt['due_date']) ? ' · ' . e(accountingDateCompactLabel((string) $accountingReceipt['due_date'])) : '' ?></span>
                                                            <form method="post" class="accounting-entry-discount-delete-form">
                                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                <input type="hidden" name="action" value="delete_accounting_discount">
                                                                <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                <input type="hidden" name="discount_id" value="<?= e((string) $accountingReceiptId) ?>">
                                                                <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                <button type="submit" class="accounting-entry-subitem-delete" aria-label="Remover recebimento"><span aria-hidden="true">&times;</span></button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <form method="post" class="accounting-entry-discount-add-form" autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_discount">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="text" name="discount_amount_value" class="accounting-input accounting-input-amount" inputmode="numeric" placeholder="<?= $accountingEntryReceiptRemainingCents > 0 ? '0,00' : 'Recebido' ?>" autocomplete="off" aria-label="Valor recebido" <?= $accountingEntryReceiptRemainingCents > 0 ? '' : 'disabled' ?> required>
                                                    <input type="date" name="discount_date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" class="accounting-installment-select" aria-label="Data do recebimento">
                                                    <button type="submit" class="btn btn-mini" data-accounting-discount-add-button <?= $accountingEntryReceiptRemainingCents > 0 ? '' : 'disabled' ?>>+</button>
                                                    <button type="button" class="btn btn-mini btn-ghost accounting-entry-discount-settle-button" data-accounting-discount-settle-remaining title="Receber o valor restante" <?= $accountingEntryReceiptRemainingCents > 0 ? '' : 'disabled' ?>>Restante</button>
                                                </form>
                                                <form method="post" class="accounting-entry-discount-confirm-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_discount">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input type="hidden" name="discounts_json" value="[]" data-accounting-pending-discounts-json>
                                                    <span class="accounting-entry-discount-confirm-note" data-accounting-discount-confirm-note hidden>Altera&ccedil;&otilde;es n&atilde;o salvas</span>
                                                    <button type="submit" class="btn btn-mini" data-accounting-discount-confirm disabled>Confirmar recebimentos</button>
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
                                                    aria-label="Tipo de entrada"
                                                    data-accounting-type-select
                                                >
                                                    <option value="single">&Uacute;nica</option>
                                                    <option value="monthly">Mensal</option>
                                                    <option value="weekly">Semanal</option>
                                                    <option value="completed_tasks">Por tarefa</option>
                                                </select>
                                                <label class="accounting-entry-date-field" title="Opcional: use quando esta entrada se refere a outra data.">
                                                    <span>Data</span>
                                                    <input type="date" name="entry_date" class="accounting-installment-select" aria-label="Data da entrada (opcional)">
                                                </label>
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
                                                    type="checkbox"
                                                    name="is_weekly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-weekly-toggle
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
                                                <div class="accounting-monthly-fields" data-accounting-weekly-fields hidden>
                                                    <span class="accounting-entry-inline-label">Toda</span>
                                                    <select
                                                        name="weekly_day"
                                                        class="accounting-installment-select accounting-monthly-day-select"
                                                        aria-label="Dia da recorr&ecirc;ncia semanal"
                                                        data-accounting-weekly-day
                                                        disabled
                                                    >
                                                        <?php foreach ([1 => 'Segunda', 2 => 'Ter&ccedil;a', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'S&aacute;bado', 7 => 'Domingo'] as $weeklyDayOption => $weeklyDayLabel): ?>
                                                            <option value="<?= e((string) $weeklyDayOption) ?>" <?= $weeklyDayOption === (int) (new DateTimeImmutable('today'))->format('N') ? 'selected' : '' ?>><?= $weeklyDayLabel ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?= $renderAccountingTaskLinkFields(
                                                    $accountingTaskLinkDefaultWorkspaceId,
                                                    $accountingTaskLinkDefaultWorkspaceId !== null
                                                        ? $accountingTaskLinkGroupsForWorkspace($accountingTaskLinkDefaultWorkspaceId)
                                                        : [],
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
                    $accountingWeeklyProjection = accountingWeeklyBalanceProjection(
                        $accountingEntries,
                        (int) ($accountingOpeningBalanceCents ?? 0),
                        [
                            'period_key' => $accountingPeriod,
                            'cycle_close_day' => (int) ($accountingCycleCloseDay ?? 0),
                        ]
                    );
                    $accountingWeeklyProjectionWeeks = is_array($accountingWeeklyProjection['weeks'] ?? null)
                        ? $accountingWeeklyProjection['weeks']
                        : [];
                    ?>
                    <div class="accounting-balance-topline">
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
                        <?php if ($accountingIsCurrentPeriodView): ?>
                            <details class="accounting-balance-adjustment">
                                <summary class="btn btn-mini btn-ghost">Informar saldo real</summary>
                                <form method="post" class="accounting-balance-adjustment-form" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_balance_adjustment">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <label>
                                        <span>Saldo real atual</span>
                                        <input
                                            type="text"
                                            name="actual_balance_value"
                                            class="accounting-input accounting-input-amount"
                                            inputmode="decimal"
                                            placeholder="R$ 0,00"
                                            autocomplete="off"
                                            required
                                        >
                                    </label>
                                    <button type="submit" class="btn btn-mini">Corrigir saldo</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                    <?php if ($accountingWeeklyProjectionWeeks): ?>
                        <div class="accounting-weekly-projection" aria-label="Saldo projetado por semana">
                            <div class="accounting-weekly-projection-head">
                                <span>Proje&ccedil;&atilde;o semanal</span>
                                <span>Saldo ao fim de cada semana</span>
                            </div>
                            <div class="accounting-weekly-projection-track" style="grid-template-columns: repeat(<?= e((string) count($accountingWeeklyProjectionWeeks)) ?>, minmax(0, 1fr));">
                                <?php foreach ($accountingWeeklyProjectionWeeks as $accountingWeek): ?>
                                    <?php
                                    $accountingWeekBalanceCents = (int) ($accountingWeek['balance_cents'] ?? 0);
                                    $accountingWeekClass = $accountingWeekBalanceCents < 0
                                        ? ' is-negative'
                                        : ($accountingWeekBalanceCents > 0 ? ' is-positive' : '');
                                    $accountingWeekIsCurrent = !empty($accountingWeek['is_current']);
                                    ?>
                                    <button
                                        type="button"
                                        class="accounting-weekly-projection-week<?= e($accountingWeekClass) ?><?= $accountingWeekIsCurrent ? ' is-current' : '' ?>"
                                        data-accounting-weekly-projection-week
                                        data-accounting-week-index="<?= e((string) ($accountingWeek['index'] ?? '')) ?>"
                                        aria-pressed="false"
                                        title="Semana <?= e((string) ($accountingWeek['index'] ?? '')) ?> (<?= e((string) ($accountingWeek['range_display'] ?? '')) ?>): <?= e((string) ($accountingWeek['balance_display'] ?? 'R$ 0,00')) ?>"
                                    >
                                        <span class="accounting-weekly-projection-fill"></span>
                                        <span class="accounting-weekly-projection-label">Sem. <?= e((string) ($accountingWeek['index'] ?? '')) ?></span>
                                        <strong><?= $renderAccountingMoney((string) ($accountingWeek['balance_display'] ?? 'R$ 0,00')) ?></strong>
                                        <small><?= e((string) ($accountingWeek['range_display'] ?? '')) ?></small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="accounting-weekly-projection-details" data-accounting-weekly-projection-details>
                                <?php foreach ($accountingWeeklyProjectionWeeks as $accountingWeek): ?>
                                    <?php
                                    $accountingWeekIndex = (string) ($accountingWeek['index'] ?? '');
                                    $accountingWeekEvents = is_array($accountingWeek['events'] ?? null)
                                        ? $accountingWeek['events']
                                        : [];
                                    $accountingWeekIsCurrent = !empty($accountingWeek['is_current']);
                                    ?>
                                    <div
                                        class="accounting-weekly-projection-detail"
                                        data-accounting-weekly-projection-detail="<?= e($accountingWeekIndex) ?>"
                                        hidden
                                    >
                                        <div class="accounting-weekly-projection-detail-head">
                                            <span>Semana <?= e($accountingWeekIndex) ?> · <?= e((string) ($accountingWeek['range_display'] ?? '')) ?></span>
                                            <strong><?= $renderAccountingMoney((string) ($accountingWeek['balance_display'] ?? 'R$ 0,00')) ?></strong>
                                        </div>
                                        <?php if ($accountingWeekEvents): ?>
                                            <div class="accounting-weekly-projection-events">
                                                <?php foreach ($accountingWeekEvents as $accountingWeekEvent): ?>
                                                    <?php $accountingWeekEventType = (string) ($accountingWeekEvent['entry_type'] ?? 'expense'); ?>
                                                    <?php $accountingWeekEventIsSettled = ((int) ($accountingWeekEvent['is_settled'] ?? 0)) === 1; ?>
                                                    <div class="accounting-weekly-projection-event is-<?= e($accountingWeekEventType) ?><?= $accountingWeekEventIsSettled ? ' is-settled' : ' is-pending' ?>">
                                                        <span class="accounting-weekly-projection-event-label">
                                                            <span><?= e((string) ($accountingWeekEvent['label'] ?? '')) ?></span>
                                                            <?php if (!$accountingWeekEventIsSettled): ?>
                                                                <small>Previsto</small>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="accounting-weekly-projection-event-date"><?= e((string) ($accountingWeekEvent['event_date_display'] ?? '')) ?></span>
                                                        <strong><?= $accountingWeekEventType === 'income' ? '+' : '−' ?> <?= $renderAccountingMoney((string) ($accountingWeekEvent['amount_display'] ?? 'R$ 0,00')) ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="accounting-weekly-projection-empty">Nenhum lan&ccedil;amento previsto nesta semana.</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
