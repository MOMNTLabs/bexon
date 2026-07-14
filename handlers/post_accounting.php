<?php
declare(strict_types=1);

function accountingAutomationConfigFromRequest(PDO $pdo, int $userId, string $entryType): ?array
{
    $requestedAutomationType = normalizeAccountingAutomationType(
        (string) (
            $_POST['automation_type']
            ?? (((string) ($_POST['accounting_type_choice'] ?? '')) === 'completed_tasks' ? 'completed_tasks' : 'manual')
        )
    );
    if ($requestedAutomationType !== 'completed_tasks') {
        return null;
    }

    if ($entryType !== 'income') {
        throw new RuntimeException('A automação por tarefas concluídas só pode ser usada em entradas.');
    }

    $sourceWorkspaceId = (int) ($_POST['task_link_workspace_id'] ?? 0);
    if ($sourceWorkspaceId <= 0 || !userHasWorkspaceAccess($userId, $sourceWorkspaceId)) {
        throw new RuntimeException('Selecione um workspace de tarefas concluídas que você possa acessar.');
    }

    $taskGroupName = normalizeTaskGroupName((string) ($_POST['task_link_group_name'] ?? ''));
    $availableGroupNames = array_values(array_unique(array_map(
        static fn ($groupName): string => normalizeTaskGroupName((string) $groupName),
        taskGroupsList($sourceWorkspaceId)
    )));
    if ($taskGroupName === '' || !in_array($taskGroupName, $availableGroupNames, true)) {
        throw new RuntimeException('Selecione um projeto válido para vincular as tarefas concluídas.');
    }
    if (!userCanViewTaskGroup($userId, $sourceWorkspaceId, $taskGroupName)) {
        throw new RuntimeException('Você não possui acesso ao projeto selecionado.');
    }

    $rawAssigneeIds = is_array($_POST['task_link_assignee_ids'] ?? null)
        ? $_POST['task_link_assignee_ids']
        : [];
    $submittedAssigneeIds = array_values(array_unique(array_filter(
        array_map('intval', $rawAssigneeIds),
        static fn (int $assigneeId): bool => $assigneeId > 0
    )));
    $sourceUsersById = usersMapById($sourceWorkspaceId);
    $selectedAssigneeIds = normalizeAssigneeIds($submittedAssigneeIds, $sourceUsersById);
    if (count($selectedAssigneeIds) !== count($submittedAssigneeIds)) {
        throw new RuntimeException('Um ou mais responsáveis selecionados são inválidos para esse workspace.');
    }

    $taskLinkRateCents = normalizeDueAmountCents($_POST['amount_value'] ?? null);
    if ($taskLinkRateCents === null || $taskLinkRateCents <= 0) {
        throw new RuntimeException('Informe um valor válido por tarefa concluída.');
    }

    return [
        'automation_type' => 'completed_tasks',
        'task_link_workspace_id' => $sourceWorkspaceId,
        'task_link_group_name' => $taskGroupName,
        'task_link_assignee_ids' => $selectedAssigneeIds,
        'task_link_rate_cents' => $taskLinkRateCents,
    ];
}

function handleAccountingPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'set_accounting_balance_snapshot':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nÃ£o encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                setWorkspaceAccountingOpeningBalance(
                    $pdo,
                    $workspaceId,
                    $periodKey,
                    $_POST['opening_balance_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Saldo atualizado.',
                    ]);
                }

                flash('success', 'Saldo atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'set_accounting_opening_balance':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                setWorkspaceAccountingOpeningBalance(
                    $pdo,
                    $workspaceId,
                    $periodKey,
                    $_POST['opening_balance_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Saldo atualizado.',
                    ]);
                }

                flash('success', 'Saldo atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'create_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                $entryType = normalizeAccountingEntryType((string) ($_POST['entry_type'] ?? 'expense'));
                $createSubitems = normalizeAccountingSubitemPayloads($_POST['create_subitems_json'] ?? null);
                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyDue = $entryType === 'expense' && ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyIncome = $entryType === 'income' && ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;
                $monthlyMode = (string) ($_POST['monthly_mode'] ?? 'uniform');
                if ($createSubitems) {
                    if ($entryType !== 'expense') {
                        throw new RuntimeException('Subitens estão disponíveis apenas para contas.');
                    }
                    $isInstallment = 0;
                    $isMonthlyDue = 0;
                    $isMonthlyIncome = 0;
                    $monthlyMode = 'uniform';
                    $_POST['amount_value'] = dueAmountLabelFromCents(
                        array_sum(array_map(static fn (array $subitem): int => (int) ($subitem['amount_cents'] ?? 0), $createSubitems))
                    );
                }
                $isMonthlyGoal = $entryType === 'expense'
                    && $isMonthlyDue === 1
                    && normalizeAccountingMonthlyMode($monthlyMode, $entryType, 1) === 'goal';
                $automationConfig = accountingAutomationConfigFromRequest(
                    $pdo,
                    (int) ($authUser['id'] ?? 0),
                    $entryType
                );

                if ($isMonthlyDue === 1 && !$isMonthlyGoal) {
                    createWorkspaceAccountingMonthlyDue(
                        $pdo,
                        $workspaceId,
                        $periodKey,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        (int) ($authUser['id'] ?? 0),
                        $_POST['monthly_day'] ?? null
                    );
                } else {
                    $entryId = createWorkspaceAccountingEntry(
                        $pdo,
                        $workspaceId,
                        $periodKey,
                        $entryType,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        (int) ($authUser['id'] ?? 0),
                        $isInstallment,
                        accountingInstallmentProgressFromRequest($_POST),
                        $_POST['total_amount_value'] ?? null,
                        $_POST['installment_number'] ?? null,
                        $_POST['installment_total'] ?? null,
                        ($isMonthlyIncome === 1 || $isMonthlyGoal) ? 1 : 0,
                        $_POST['monthly_day'] ?? null,
                        $monthlyMode,
                        $automationConfig
                    );
                    foreach ($createSubitems as $subitem) {
                        createWorkspaceAccountingSubitem(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            (string) ($subitem['label'] ?? ''),
                            $subitem['amount_input'] ?? null,
                            (int) ($authUser['id'] ?? 0)
                        );
                    }
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.',
                    ]);
                }

                flash('success', $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id, entry_type
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryRow = $entryWorkspaceStmt->fetch(PDO::FETCH_ASSOC);
                $entryWorkspaceId = (int) ($entryRow['workspace_id'] ?? 0);
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $entryType = normalizeAccountingEntryType((string) ($entryRow['entry_type'] ?? 'expense'));
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyFlag = ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;
                $automationConfig = accountingAutomationConfigFromRequest(
                    $pdo,
                    (int) ($authUser['id'] ?? 0),
                    $entryType
                );
                updateWorkspaceAccountingEntryWithCarrySync(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['amount_value'] ?? null,
                    $isSettled,
                    $isInstallment,
                    accountingInstallmentProgressFromRequest($_POST),
                    $_POST['total_amount_value'] ?? null,
                    $_POST['installment_number'] ?? null,
                    $_POST['installment_total'] ?? null,
                    $_POST['monthly_day'] ?? null,
                    $isMonthlyFlag,
                    (string) ($_POST['monthly_mode'] ?? 'uniform'),
                    $automationConfig
                );
                if (workspaceAccountingSubitemTotalCents($pdo, $workspaceId, $entryId) !== null) {
                    workspaceAccountingSyncEntrySettlementFromSubitems($pdo, $workspaceId, $entryId);
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro atualizado.',
                    ]);
                }

                flash('success', 'Registro atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_goal_payment':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                updateWorkspaceAccountingGoalPaymentWithCarrySync(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $_POST['paid_amount_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Pagamento mensal atualizado.',
                    ]);
                }

                flash('success', 'Pagamento mensal atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'add_accounting_goal_payment':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                addWorkspaceAccountingGoalPaymentWithCarrySync(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $_POST['payment_amount_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Pagamento adicionado.',
                    ]);
                }

                flash('success', 'Pagamento adicionado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_goal_payment':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $paymentId = (int) ($_POST['payment_id'] ?? 0);
                if ($entryId <= 0 || $paymentId <= 0) {
                    throw new RuntimeException('Lançamento inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                deleteWorkspaceAccountingGoalPaymentWithCarrySync(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $paymentId
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Pagamento removido.',
                    ]);
                }

                flash('success', 'Pagamento removido.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'create_accounting_subitem':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nÃ£o encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro invÃ¡lido.');
                }

                createWorkspaceAccountingSubitem(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['subitem_label'] ?? ''),
                    $_POST['subitem_amount_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitem adicionado.',
                    ]);
                }

                flash('success', 'Subitem adicionado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_subitem':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nÃ£o encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $subitemId = (int) ($_POST['subitem_id'] ?? 0);
                if ($entryId <= 0 || $subitemId <= 0) {
                    throw new RuntimeException('Subitem invÃ¡lido.');
                }

                updateWorkspaceAccountingSubitem(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $subitemId,
                    (string) ($_POST['subitem_label'] ?? ''),
                    $_POST['subitem_amount_value'] ?? null,
                    isset($_POST['is_settled']) ? 1 : 0
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitem atualizado.',
                    ]);
                }

                flash('success', 'Subitem atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_subitem':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nÃ£o encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $subitemId = (int) ($_POST['subitem_id'] ?? 0);
                if ($entryId <= 0 || $subitemId <= 0) {
                    throw new RuntimeException('Subitem invÃ¡lido.');
                }

                deleteWorkspaceAccountingSubitem($pdo, $workspaceId, $entryId, $subitemId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitem removido.',
                    ]);
                }

                flash('success', 'Subitem removido.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                deleteWorkspaceAccountingEntryWithCarrySync($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro removido.',
                    ]);
                }

                flash('success', 'Registro removido.');
                redirectTo(accountingRedirectPathFromRequest());
    }

    return in_array($action, [
        'set_accounting_balance_snapshot',
        'set_accounting_opening_balance',
        'create_accounting_entry',
        'update_accounting_entry',
        'update_accounting_goal_payment',
        'add_accounting_goal_payment',
        'delete_accounting_goal_payment',
        'create_accounting_subitem',
        'update_accounting_subitem',
        'delete_accounting_subitem',
        'delete_accounting_entry',
    ], true);
}
