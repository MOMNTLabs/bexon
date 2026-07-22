<?php
declare(strict_types=1);

function renderSectionGroupSelectOptionsHtml(
    array $groupNames,
    string $emptyLabel = 'Sem grupo com acesso'
): string {
    ob_start();
    if (!$groupNames) {
        echo '<option value="">' . e($emptyLabel) . '</option>';
    } else {
        foreach ($groupNames as $groupName) {
            $groupValue = trim((string) $groupName);
            echo '<option value="' . e($groupValue) . '">'
                . e($groupValue)
                . '</option>';
        }
    }

    return (string) ob_get_clean();
}

function requireSnapshotWorkspaceContext(): array
{
    $currentUser = currentUser();
    if (!$currentUser) {
        throw new RuntimeException('Sessão expirada. Faça login novamente.');
    }

    $workspaceId = activeWorkspaceId($currentUser);
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo não encontrado.');
    }
    $currentWorkspace = workspaceById($workspaceId);

    $userId = (int) ($currentUser['id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    return [
        'current_user' => $currentUser,
        'workspace_id' => $workspaceId,
        'current_workspace' => $currentWorkspace,
        'user_id' => $userId,
        'can_manage_workspace' => userCanManageWorkspace($userId, $workspaceId),
        'is_personal_workspace' => !empty($currentWorkspace['is_personal']),
    ];
}

function respondVaultPanelSnapshot(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $currentUser = $ctx['current_user'];
    $workspaceId = (int) $ctx['workspace_id'];
    $currentUserId = (int) $ctx['user_id'];
    $canManageWorkspace = (bool) $ctx['can_manage_workspace'];
    $isPersonalWorkspace = (bool) ($ctx['is_personal_workspace'] ?? false);

    $vaultGroupsAll = vaultGroupsList($workspaceId);
    $vaultGroupPermissions = [];
    $vaultGroups = [];
    $vaultGroupsWithAccess = [];

    foreach ($vaultGroupsAll as $vaultGroupName) {
        $vaultGroupName = normalizeVaultGroupName((string) $vaultGroupName);
        $permission = vaultGroupPermissionForUser($workspaceId, $vaultGroupName, $currentUserId);
        $vaultGroupPermissions[$vaultGroupName] = $permission;

        if (!empty($permission['can_view'])) {
            $vaultGroups[] = $vaultGroupName;
        }
        if (!empty($permission['can_access'])) {
            $vaultGroupsWithAccess[] = $vaultGroupName;
        }
    }

    $vaultVisibleKeys = [];
    foreach ($vaultGroups as $vaultGroupName) {
        $vaultVisibleKeys[mb_strtolower(normalizeVaultGroupName($vaultGroupName))] = true;
    }

    $vaultEntries = workspaceVaultEntriesList($workspaceId);
    $vaultEntries = array_values(array_filter(
        $vaultEntries,
        static function (array $entry) use ($vaultVisibleKeys): bool {
            $groupKey = mb_strtolower(normalizeVaultGroupName((string) ($entry['group_name'] ?? 'Geral')));
            return isset($vaultVisibleKeys[$groupKey]);
        }
    ));
    $vaultEntriesByGroup = vaultEntriesByGroup($vaultEntries, $vaultGroups);

    ob_start();
    include __DIR__ . '/../partials/vault_panel.php';
    $panelHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'panel_html' => $panelHtml,
        'group_options_html' => renderSectionGroupSelectOptionsHtml($vaultGroupsWithAccess),
        'has_group_access' => !empty($vaultGroupsWithAccess),
        'total_count' => count($vaultEntries),
    ]);
}

function respondDuePanelSnapshot(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $currentUser = $ctx['current_user'];
    $workspaceId = (int) $ctx['workspace_id'];
    $currentUserId = (int) $ctx['user_id'];
    $canManageWorkspace = (bool) $ctx['can_manage_workspace'];
    $isPersonalWorkspace = (bool) ($ctx['is_personal_workspace'] ?? false);

    $dueGroupsAll = dueGroupsList($workspaceId);
    $dueGroupPermissions = [];
    $dueGroups = [];
    $dueGroupsWithAccess = [];

    foreach ($dueGroupsAll as $dueGroupName) {
        $dueGroupName = normalizeDueGroupName((string) $dueGroupName);
        $permission = dueGroupPermissionForUser($workspaceId, $dueGroupName, $currentUserId);
        $dueGroupPermissions[$dueGroupName] = $permission;

        if (!empty($permission['can_view'])) {
            $dueGroups[] = $dueGroupName;
        }
        if (!empty($permission['can_access'])) {
            $dueGroupsWithAccess[] = $dueGroupName;
        }
    }

    $dueVisibleKeys = [];
    foreach ($dueGroups as $dueGroupName) {
        $dueVisibleKeys[mb_strtolower(normalizeDueGroupName($dueGroupName))] = true;
    }

    $dueEntries = workspaceDueEntriesList($workspaceId);
    $dueEntries = array_values(array_filter(
        $dueEntries,
        static function (array $entry) use ($dueVisibleKeys): bool {
            $groupKey = mb_strtolower(normalizeDueGroupName((string) ($entry['group_name'] ?? 'Geral')));
            return isset($dueVisibleKeys[$groupKey]);
        }
    ));
    $dueEntriesByGroup = dueEntriesByGroup($dueEntries, $dueGroups);

    ob_start();
    include __DIR__ . '/../partials/due_panel.php';
    $panelHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'panel_html' => $panelHtml,
        'group_options_html' => renderSectionGroupSelectOptionsHtml($dueGroupsWithAccess),
        'has_group_access' => !empty($dueGroupsWithAccess),
        'total_count' => count($dueEntries),
    ]);
}

function respondInventoryPanelSnapshot(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $workspaceId = (int) $ctx['workspace_id'];
    $inventoryGroups = inventoryGroupsList($workspaceId);
    $inventoryGroupsWithAccess = $inventoryGroups;
    $inventoryEntries = workspaceInventoryEntriesList($workspaceId);
    $inventoryEntriesByGroup = inventoryEntriesByGroup($inventoryEntries, $inventoryGroups);

    ob_start();
    include __DIR__ . '/../partials/inventory_panel.php';
    $panelHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'panel_html' => $panelHtml,
        'group_options_html' => renderSectionGroupSelectOptionsHtml($inventoryGroupsWithAccess),
        'has_group_access' => !empty($inventoryGroupsWithAccess),
        'total_count' => count($inventoryEntries),
    ]);
}

function respondAccountingPanelSnapshot(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $workspaceId = (int) $ctx['workspace_id'];
    $userId = (int) $ctx['user_id'];
    $accountingPeriod = normalizeAccountingPeriodKey((string) ($_GET['accounting_period'] ?? ''));
    $accountingCurrentPeriodKey = accountingCycleCurrentPeriodKey(workspaceAccountingCycleCloseDay($workspaceId));
    $accountingShowFutureCarryover = ((string) ($_GET['accounting_forecast_carry'] ?? '')) === '1'
        && strcmp($accountingPeriod, $accountingCurrentPeriodKey) > 0;
    $accountingEntries = workspaceAccountingEntriesList($workspaceId, $accountingPeriod);
    if ($accountingShowFutureCarryover) {
        $accountingEntries = array_merge(
            $accountingEntries,
            workspaceAccountingFutureCarryoverPreviewEntries(db(), $workspaceId, $accountingPeriod)
        );
    }
    $accountingEntriesByType = workspaceAccountingEntriesByType($accountingEntries);
    $accountingExpenseEntries = $accountingEntriesByType['expense'] ?? [];
    $accountingIncomeEntries = $accountingEntriesByType['income'] ?? [];
    $accountingTaskLinkOptions = accountingTaskLinkOptionsForUser($userId);
    $accountingOpeningBalanceCents = workspaceAccountingOpeningBalanceCents($workspaceId, $accountingPeriod);
    $accountingSummary = accountingSummary($accountingEntries, $accountingOpeningBalanceCents, [
        'period_key' => $accountingPeriod,
        'current_period_key' => $accountingCurrentPeriodKey,
    ]);
    $accountingInheritedBalanceEntry = workspaceAccountingInheritedBalanceDisplayEntry($accountingOpeningBalanceCents);
    if ($accountingInheritedBalanceEntry !== null) {
        if ((string) $accountingInheritedBalanceEntry['entry_type'] === 'income') {
            array_unshift($accountingIncomeEntries, $accountingInheritedBalanceEntry);
        } else {
            array_unshift($accountingExpenseEntries, $accountingInheritedBalanceEntry);
        }
    }
    $accountingSyncVersion = workspaceAccountingPanelSyncVersion(db(), $workspaceId);
    ob_start();
    include __DIR__ . '/../partials/accounting_sheet.php';
    $accountingSheetHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'accounting_sheet_html' => $accountingSheetHtml,
        'accounting_sync_version' => $accountingSyncVersion,
    ]);
}

function respondAccountingPanelVersion(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $workspaceId = (int) $ctx['workspace_id'];

    respondJson([
        'ok' => true,
        'accounting_sync_version' => workspaceAccountingPanelSyncVersion(db(), $workspaceId),
    ]);
}

function respondUsersPanelSnapshot(): void
{
    $ctx = requireSnapshotWorkspaceContext();
    $currentUser = $ctx['current_user'];
    $workspaceId = (int) $ctx['workspace_id'];
    $canManageWorkspace = (bool) $ctx['can_manage_workspace'];

    $currentWorkspace = activeWorkspace($currentUser);
    $currentWorkspaceId = $workspaceId;
    $userWorkspaces = workspacesForUser((int) ($currentUser['id'] ?? 0));
    $workspaceMembers = workspaceMembersList($workspaceId);
    $workspacePendingInvitations = workspacePendingInvitationsForWorkspace($workspaceId);
    $currentUserWorkspaceInvitations = workspacePendingInvitationsForUser((int) ($currentUser['id'] ?? 0));
    $workspaceTaskStatusConfig = taskStatusConfig($workspaceId, $currentWorkspace);
    $workspaceSidebarConfig = workspaceSidebarToolsConfig($workspaceId, $currentWorkspace);
    $isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
    $showUsersDashboardTab = true;
    $taskGroups = [];
    foreach (taskGroupsList($workspaceId) as $taskGroupName) {
        $taskGroupName = normalizeTaskGroupName((string) $taskGroupName);
        if ($taskGroupName === '') {
            continue;
        }

        $permission = taskGroupPermissionForUser($workspaceId, $taskGroupName, (int) $currentUser['id']);
        if (!empty($permission['can_view'])) {
            $taskGroups[] = $taskGroupName;
        }
    }
    $taskGroupVisuals = taskGroupVisualsMap($workspaceId);

    ob_start();
    include __DIR__ . '/../partials/users_panel.php';
    $panelHtml = (string) ob_get_clean();

    ob_start();
    include __DIR__ . '/../partials/workspace_sidebar_picker_list.php';
    $workspacePickerListHtml = (string) ob_get_clean();

    ob_start();
    include __DIR__ . '/../partials/workspace_sidebar_picker_summary.php';
    $workspacePickerSummaryHtml = (string) ob_get_clean();

    ob_start();
    include __DIR__ . '/../partials/workspace_sidebar_menu.php';
    $workspaceSidebarMenuHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'panel_html' => $panelHtml,
        'workspace_picker_list_html' => $workspacePickerListHtml,
        'workspace_picker_summary_html' => $workspacePickerSummaryHtml,
        'workspace_sidebar_menu_html' => $workspaceSidebarMenuHtml,
        'workspace_picker_title' => (string) ($currentWorkspace['name'] ?? 'Workspace'),
    ]);
}
