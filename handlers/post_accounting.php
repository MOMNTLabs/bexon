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

    $submittedGroupNames = normalizeAccountingTaskLinkGroupNames(
        is_array($_POST['task_link_group_names'] ?? null) ? $_POST['task_link_group_names'] : [],
        isset($_POST['task_link_group_name']) ? (string) $_POST['task_link_group_name'] : null
    );
    $taskGroupName = $submittedGroupNames[0] ?? normalizeTaskGroupName((string) ($_POST['task_link_group_name'] ?? ''));
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

    $selectedGroupNames = $submittedGroupNames ?: [$taskGroupName];
    foreach ($selectedGroupNames as $selectedGroupName) {
        if (!in_array($selectedGroupName, $availableGroupNames, true)
            || !userCanViewTaskGroup($userId, $sourceWorkspaceId, $selectedGroupName)) {
            throw new RuntimeException('Um ou mais projetos selecionados são inválidos ou não estão acessíveis.');
        }
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
        'task_link_group_names' => $selectedGroupNames,
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

            case 'create_accounting_balance_adjustment':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                $entryId = createWorkspaceAccountingBalanceAdjustment(
                    $pdo,
                    $workspaceId,
                    $periodKey,
                    $_POST['actual_balance_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );
                $message = $entryId === null
                    ? 'O saldo informado já está correto.'
                    : 'Ajuste de saldo criado.';

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $message,
                    ]);
                }

                flash('success', $message);
                redirectTo(accountingRedirectPathFromRequest());

            case 'create_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                $entryDate = dueDateForStorage((string) ($_POST['entry_date'] ?? ''))
                    ?? (new DateTimeImmutable('today'))->format('Y-m-d');
                $entryDatePeriodKey = accountingPeriodKeyFromDateWithCycleCloseDay(
                    $entryDate,
                    workspaceAccountingCycleCloseDay($workspaceId)
                );
                if ($entryDatePeriodKey !== null) {
                    $periodKey = $entryDatePeriodKey;
                }
                $entryType = normalizeAccountingEntryType((string) ($_POST['entry_type'] ?? 'expense'));
                $createSubitems = normalizeAccountingSubitemPayloads($_POST['create_subitems_json'] ?? null);
                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyDue = $entryType === 'expense' && ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyIncome = $entryType === 'income' && ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;
                $isWeekly = ((string) ($_POST['is_weekly_due'] ?? '0')) === '1'
                    || ((string) ($_POST['accounting_type_choice'] ?? '')) === 'weekly';
                $monthlyMode = (string) ($_POST['monthly_mode'] ?? 'uniform');
                if ($createSubitems) {
                    $isInstallment = 0;
                    $isMonthlyDue = 0;
                    $isMonthlyIncome = 0;
                    $isWeekly = false;
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

                if ($isWeekly) {
                    $entryId = createWorkspaceAccountingWeeklyRecurrence(
                        $pdo,
                        $workspaceId,
                        $periodKey,
                        $entryType,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $_POST['weekly_day'] ?? null,
                        (int) ($authUser['id'] ?? 0),
                        $_POST['weekly_start_date'] ?? $entryDate
                    );
                    if ($isSettled === 1) {
                        updateWorkspaceAccountingWeeklyRecurrenceFromEntry(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            (string) ($_POST['label'] ?? ''),
                            $_POST['amount_value'] ?? null,
                            1
                        );
                    }
                } elseif ($isMonthlyDue === 1 && !$isMonthlyGoal) {
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
                        $automationConfig,
                        $entryDate
                    );
                    foreach ($createSubitems as $subitem) {
                        createWorkspaceAccountingSubitem(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            (string) ($subitem['label'] ?? ''),
                            $subitem['amount_input'] ?? null,
                            (int) ($authUser['id'] ?? 0),
                            0,
                            true,
                            $subitem['due_date'] ?? null
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
                    'SELECT workspace_id, entry_type, weekly_recurrence_id
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

                $existingEntry = workspaceAccountingEntryById($pdo, $workspaceId, $entryId);
                if ($existingEntry === null) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                $entryType = normalizeAccountingEntryType((string) ($existingEntry['entry_type'] ?? 'expense'));
                $isSettled = array_key_exists('is_settled', $_POST)
                    ? 1
                    : (((string) ($_POST['preserve_settlement'] ?? '')) === '1'
                        ? (((int) ($existingEntry['is_settled'] ?? 0)) === 1 ? 1 : 0)
                        : 0);
                $weeklyRecurrenceId = max(0, (int) ($existingEntry['weekly_recurrence_id'] ?? 0));
                $sourceTypeChoice = workspaceAccountingEntryTypeChoice($existingEntry);
                $targetTypeChoice = normalizeAccountingEntryTypeChoice(
                    $entryType,
                    (string) ($_POST['accounting_type_choice'] ?? $sourceTypeChoice)
                );
                $isInstallment = $entryType === 'expense' && $targetTypeChoice === 'installment' ? 1 : 0;
                $isMonthlyFlag = in_array($targetTypeChoice, ['monthly', 'goal'], true) ? 1 : 0;
                $automationConfig = accountingAutomationConfigFromRequest(
                    $pdo,
                    (int) ($authUser['id'] ?? 0),
                    $entryType
                );
                $currentWeeklyDay = $weeklyRecurrenceId > 0
                    ? normalizeAccountingWeeklyDay((new DateTimeImmutable((string) ($existingEntry['due_date'] ?? 'today')))->format('N'))
                    : 0;
                $requestedWeeklyDay = normalizeAccountingWeeklyDay($_POST['weekly_day'] ?? null);
                $shouldRebuildWeekly = $sourceTypeChoice === 'weekly'
                    && $targetTypeChoice === 'weekly'
                    && $currentWeeklyDay !== $requestedWeeklyDay;

                if ($sourceTypeChoice !== $targetTypeChoice || $shouldRebuildWeekly) {
                    convertWorkspaceAccountingEntryType(
                        $pdo,
                        $workspaceId,
                        $entryId,
                        $targetTypeChoice,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        accountingInstallmentProgressFromRequest($_POST),
                        $_POST['total_amount_value'] ?? null,
                        $_POST['installment_number'] ?? null,
                        $_POST['installment_total'] ?? null,
                        $_POST['monthly_day'] ?? null,
                        $_POST['weekly_day'] ?? null,
                        $automationConfig,
                        (int) ($authUser['id'] ?? 0),
                        $shouldRebuildWeekly,
                        $_POST['weekly_start_date'] ?? null,
                        $_POST['recurrence_start_period'] ?? null
                    );
                } elseif ($weeklyRecurrenceId > 0) {
                    updateWorkspaceAccountingWeeklyRecurrenceFromEntry(
                        $pdo,
                        $workspaceId,
                        $entryId,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        $_POST['weekly_start_date'] ?? null
                    );
                } else {
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
                        $automationConfig,
                        $_POST['recurrence_start_period'] ?? null
                    );
                }
                if (!in_array($targetTypeChoice, ['monthly', 'weekly'], true)) {
                    updateWorkspaceAccountingEntryDate(
                        $pdo,
                        $workspaceId,
                        $entryId,
                        $_POST['entry_date'] ?? null
                    );
                }
                if (workspaceAccountingSubitemTotalCents($pdo, $workspaceId, $entryId) !== null) {
                    workspaceAccountingSyncEntrySettlementFromSubitems($pdo, $workspaceId, $entryId);
                }

                if (requestExpectsJson()) {
                    $response = [
                        'ok' => true,
                        'message' => 'Registro atualizado.',
                    ];
                    if (((string) ($_POST['fast_status_only'] ?? '')) === '1') {
                        $summaryPeriodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                        $summaryEntries = workspaceAccountingEntriesListRaw($pdo, $workspaceId, $summaryPeriodKey);
                        $summary = accountingSummary($summaryEntries, 0, [
                            'period_key' => $summaryPeriodKey,
                            'current_period_key' => accountingCycleCurrentPeriodKey(
                                workspaceAccountingCycleCloseDay($workspaceId)
                            ),
                        ]);
                        $response['accounting_summary'] = [
                            'expense_total_cents' => (int) ($summary['expense_total_cents'] ?? 0),
                            'expense_paid_cents' => (int) ($summary['expense_paid_cents'] ?? 0),
                            'income_total_cents' => (int) ($summary['income_total_cents'] ?? 0),
                            'income_received_cents' => (int) ($summary['income_received_cents'] ?? 0),
                        ];
                    }
                    respondJson($response);
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
                    (int) ($authUser['id'] ?? 0),
                    0,
                    true,
                    $_POST['subitem_date'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitem adicionado.',
                    ]);
                }

                flash('success', 'Subitem adicionado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'add_accounting_discount':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $discountEntry = workspaceAccountingEntryById($pdo, $workspaceId, $entryId);
                $discountIsIncome = normalizeAccountingEntryType((string) ($discountEntry['entry_type'] ?? 'expense')) === 'income';

                $discountsJson = trim((string) ($_POST['discounts_json'] ?? ''));
                $discountAmounts = [];
                if ($discountsJson !== '') {
                    $decodedDiscounts = json_decode($discountsJson, true);
                    if (!is_array($decodedDiscounts) || count($decodedDiscounts) > 100) {
                        throw new RuntimeException($discountIsIncome ? 'Recebimentos inválidos.' : 'Abatimentos inválidos.');
                    }

                    foreach ($decodedDiscounts as $discount) {
                        if (!is_array($discount)) {
                            throw new RuntimeException($discountIsIncome ? 'Recebimento inválido.' : 'Abatimento inválido.');
                        }

                        $amount = $discount['amount'] ?? null;
                        $amountCents = normalizeDueAmountCents($amount);
                        if ($amountCents === null || $amountCents <= 0) {
                            throw new RuntimeException($discountIsIncome ? 'Informe um valor recebido válido.' : 'Informe um valor de abatimento válido.');
                        }
                        $discountAmounts[] = [
                            'amount' => $amount,
                            'due_date' => $discount['due_date'] ?? null,
                        ];
                    }

                    if (!$discountAmounts) {
                        throw new RuntimeException($discountIsIncome ? 'Nenhum recebimento foi informado.' : 'Nenhum abatimento foi informado.');
                    }
                } else {
                    $discountAmounts[] = [
                        'amount' => $_POST['discount_amount_value'] ?? null,
                        'due_date' => $_POST['discount_date'] ?? null,
                    ];
                }

                $startedTransaction = !$pdo->inTransaction();
                if ($startedTransaction) {
                    $pdo->beginTransaction();
                }

                try {
                    foreach ($discountAmounts as $discount) {
                        addWorkspaceAccountingDiscount(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            $discount['amount'] ?? null,
                            (int) ($authUser['id'] ?? 0),
                            $discount['due_date'] ?? null
                        );
                    }

                    if ($startedTransaction) {
                        $pdo->commit();
                    }
                } catch (Throwable $e) {
                    if ($startedTransaction && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $discountIsIncome ? 'Recebimentos atualizados.' : 'Abatimentos atualizados.',
                    ]);
                }

                flash('success', $discountIsIncome ? 'Recebimentos atualizados.' : 'Abatimentos atualizados.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_discount':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $discountId = (int) ($_POST['discount_id'] ?? 0);
                if ($entryId <= 0 || $discountId <= 0) {
                    throw new RuntimeException('Abatimento inválido.');
                }

                $discountEntry = workspaceAccountingEntryById($pdo, $workspaceId, $entryId);
                $discountIsIncome = normalizeAccountingEntryType((string) ($discountEntry['entry_type'] ?? 'expense')) === 'income';

                deleteWorkspaceAccountingDiscount($pdo, $workspaceId, $entryId, $discountId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $discountIsIncome ? 'Recebimento removido.' : 'Abatimento removido.',
                    ]);
                }

                flash('success', $discountIsIncome ? 'Recebimento removido.' : 'Abatimento removido.');
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
                    array_key_exists('is_settled', $_POST)
                        ? (((string) ($_POST['is_settled'] ?? '0')) === '1' ? 1 : 0)
                        : null,
                    $_POST['subitem_date'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitem atualizado.',
                    ]);
                }

                flash('success', 'Subitem atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_subitem_statuses':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro invalido.');
                }

                $decodedStatuses = json_decode((string) ($_POST['subitem_statuses_json'] ?? ''), true);
                if (!is_array($decodedStatuses)) {
                    throw new RuntimeException('Pagamentos de subitens invalidos.');
                }

                $createSubitems = normalizeAccountingSubitemPayloads($_POST['create_subitems_json'] ?? null);
                if (!$decodedStatuses && !$createSubitems) {
                    throw new RuntimeException('Nenhuma alteracao de subitem foi informada.');
                }

                $startedTransaction = !$pdo->inTransaction();
                if ($startedTransaction) {
                    $pdo->beginTransaction();
                }

                try {
                    if ($decodedStatuses) {
                        updateWorkspaceAccountingSubitemStatuses(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            $decodedStatuses
                        );
                    }

                    foreach ($createSubitems as $subitem) {
                        createWorkspaceAccountingSubitem(
                            $pdo,
                            $workspaceId,
                            $entryId,
                            (string) ($subitem['label'] ?? ''),
                            $subitem['amount_input'] ?? null,
                            (int) ($authUser['id'] ?? 0),
                            (int) ($subitem['is_settled'] ?? 0),
                            true,
                            $subitem['due_date'] ?? null
                        );
                    }

                    if ($startedTransaction) {
                        $pdo->commit();
                    }
                } catch (Throwable $e) {
                    if ($startedTransaction && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Subitens atualizados.',
                    ]);
                }

                flash('success', 'Subitens atualizados.');
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
        'create_accounting_balance_adjustment',
        'create_accounting_entry',
        'update_accounting_entry',
        'update_accounting_goal_payment',
        'add_accounting_goal_payment',
        'delete_accounting_goal_payment',
        'add_accounting_discount',
        'delete_accounting_discount',
        'create_accounting_subitem',
        'update_accounting_subitem',
        'update_accounting_subitem_statuses',
        'delete_accounting_subitem',
        'delete_accounting_entry',
    ], true);
}
