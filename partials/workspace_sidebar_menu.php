<?php
$workspaceSidebarConfig = is_array($workspaceSidebarConfig ?? null)
    ? $workspaceSidebarConfig
    : workspaceSidebarToolsConfig($currentWorkspaceId ?? null, $currentWorkspace ?? null);
$enabledSidebarTools = is_array($workspaceSidebarConfig['enabled'] ?? null)
    ? $workspaceSidebarConfig['enabled']
    : ['tasks'];
$currentSidebarView = normalizeDashboardViewKey((string) ($_GET['view'] ?? ''));
$sidebarTaskGroups = array_values(array_filter(
    is_array($taskGroups ?? null) ? $taskGroups : [],
    static fn ($groupName): bool => trim((string) $groupName) !== ''
));
$sidebarTaskScope = normalizeTaskPageMode((string) ($_GET['task_scope'] ?? ''));
if ($sidebarTaskScope === '' || $sidebarTaskScope === 'select') {
    $sidebarTaskScope = 'all';
}
if ($sidebarTaskScope === 'mine' && empty($currentWorkspace['is_personal'])) {
    $sidebarTaskScope = 'all';
}
$sidebarTaskCurrentGroup = isset($_GET['group']) && trim((string) $_GET['group']) !== ''
    ? normalizeTaskGroupName((string) $_GET['group'])
    : '';
if ($sidebarTaskScope === 'project' && $sidebarTaskCurrentGroup === '') {
    $sidebarTaskScope = 'all';
}
$sidebarTaskProjectsOpen = $currentSidebarView === 'tasks';
$sidebarTaskAllProjectsPath = dashboardPath('tasks', ['task_scope' => 'all']);
$sidebarTaskAllProjectsActive = $currentSidebarView === 'tasks' && $sidebarTaskScope !== 'project';
$sidebarTaskInboxPath = dashboardPath('tasks', ['task_scope' => 'mine']);
$sidebarTaskInboxActive = $currentSidebarView === 'tasks' && $sidebarTaskScope === 'mine';
?>

<nav class="sidebar-view-menu" id="workspace-sidebar-menu" aria-label="Menu do workspace">
    <?php foreach ($enabledSidebarTools as $sidebarToolView): ?>
        <?php if ($sidebarToolView === 'tasks'): ?>
            <div
                class="sidebar-view-branch sidebar-task-projects<?= $sidebarTaskProjectsOpen ? ' is-open' : '' ?>"
                data-sidebar-task-projects
            >
                <button
                    type="button"
                    class="sidebar-view-toggle sidebar-task-projects-toggle"
                    data-dashboard-view-toggle
                    data-sidebar-task-projects-toggle
                    data-view="tasks"
                    aria-pressed="false"
                    aria-expanded="<?= $sidebarTaskProjectsOpen ? 'true' : 'false' ?>"
                    aria-controls="workspace-sidebar-task-projects-panel"
                >
                    <span class="sidebar-view-toggle-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M8 7h11"></path>
                            <path d="M8 12h11"></path>
                            <path d="M8 17h11"></path>
                            <path d="M4.5 7h.01"></path>
                            <path d="M4.5 12h.01"></path>
                            <path d="M4.5 17h.01"></path>
                        </svg>
                    </span>
                    <span class="sidebar-view-toggle-label">Lista de tarefas</span>
                    <span class="sidebar-task-projects-toggle-chevron" aria-hidden="true">
                        <svg viewBox="0 0 16 16" focusable="false">
                            <path d="m4 6 4 4 4-4"></path>
                        </svg>
                    </span>
                </button>
                <div
                    class="sidebar-view-submenu sidebar-task-projects-panel"
                    id="workspace-sidebar-task-projects-panel"
                    data-sidebar-task-projects-panel
                    <?= $sidebarTaskProjectsOpen ? '' : 'hidden' ?>
                >
                    <div class="sidebar-task-projects-header">
                        <span class="sidebar-task-projects-title">Projetos</span>
                        <?php if (!empty($canManageWorkspace)): ?>
                            <button
                                type="button"
                                class="sidebar-task-projects-create"
                                data-open-create-group-modal
                                aria-label="Criar projeto"
                                title="Criar projeto"
                            >
                                <span aria-hidden="true">+</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <a
                        href="<?= e($sidebarTaskAllProjectsPath) ?>"
                        class="sidebar-task-project-link<?= $sidebarTaskAllProjectsActive && $sidebarTaskScope !== 'mine' ? ' is-active' : '' ?>"
                        data-sidebar-task-project-link
                        data-task-scope="all"
                    >Todos projetos</a>
                    <?php if (!empty($currentWorkspace['is_personal'])): ?>
                        <a
                            href="<?= e($sidebarTaskInboxPath) ?>"
                            class="sidebar-task-project-link sidebar-task-inbox-link<?= $sidebarTaskInboxActive ? ' is-active' : '' ?>"
                            data-sidebar-task-project-link
                            data-task-scope="mine"
                        >Minhas tarefas</a>
                    <?php endif; ?>
                    <?php foreach ($sidebarTaskGroups as $sidebarTaskProjectName): ?>
                        <?php
                        $sidebarTaskProjectPath = dashboardPath('tasks', [
                            'task_scope' => 'project',
                            'group' => $sidebarTaskProjectName,
                        ]);
                        $sidebarTaskProjectActive =
                            $currentSidebarView === 'tasks'
                            && $sidebarTaskScope === 'project'
                            && mb_strtolower($sidebarTaskCurrentGroup) === mb_strtolower($sidebarTaskProjectName);
                        $sidebarTaskProjectVisual = $taskGroupVisuals[$sidebarTaskProjectName]
                            ?? taskGroupVisual($sidebarTaskProjectName, $currentWorkspaceId ?? null);
                        ?>
                        <a
                            href="<?= e($sidebarTaskProjectPath) ?>"
                            class="sidebar-task-project-link<?= $sidebarTaskProjectActive ? ' is-active' : '' ?>"
                            data-sidebar-task-project-link
                            data-task-scope="project"
                            data-task-group-key="<?= e(mb_strtolower($sidebarTaskProjectName)) ?>"
                        ><?= renderTaskGroupVisual($sidebarTaskProjectVisual, 'task-project-visual task-project-visual-sidebar', 'span') ?><span><?= e($sidebarTaskProjectName) ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($sidebarToolView === 'vault'): ?>
            <button
                type="button"
                class="sidebar-view-toggle"
                data-dashboard-view-toggle
                data-view="vault"
                aria-pressed="false"
            >
                <span class="sidebar-view-toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                        <path d="M8 10V7a4 4 0 1 1 8 0v3"></path>
                    </svg>
                </span>
                <span class="sidebar-view-toggle-label">Gerenciador de acessos</span>
            </button>
        <?php elseif ($sidebarToolView === 'inventory'): ?>
            <button
                type="button"
                class="sidebar-view-toggle"
                data-dashboard-view-toggle
                data-view="inventory"
                aria-pressed="false"
            >
                <span class="sidebar-view-toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 7.5 12 4l8 3.5-8 3.5-8-3.5Z"></path>
                        <path d="M4 12.5 12 16l8-3.5"></path>
                        <path d="M4 17.5 12 21l8-3.5"></path>
                        <path d="M12 11v10"></path>
                    </svg>
                </span>
                <span class="sidebar-view-toggle-label">Estoque</span>
            </button>
        <?php elseif ($sidebarToolView === 'accounting'): ?>
            <button
                type="button"
                class="sidebar-view-toggle"
                data-dashboard-view-toggle
                data-view="accounting"
                aria-pressed="false"
            >
                <span class="sidebar-view-toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <circle cx="12" cy="12" r="8"></circle>
                        <path d="M12 8v8"></path>
                        <path d="M9.5 9.5h4"></path>
                        <path d="M9.5 14.5h4"></path>
                    </svg>
                </span>
                <span class="sidebar-view-toggle-label">Contabilidade</span>
            </button>
        <?php elseif ($sidebarToolView === 'documents'): ?>
            <button
                type="button"
                class="sidebar-view-toggle"
                data-dashboard-view-toggle
                data-view="documents"
                aria-pressed="false"
            >
                <span class="sidebar-view-toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M7 3.5h7l3.5 3.5v13.5H7z"></path>
                        <path d="M14 3.5V7h3.5"></path>
                        <path d="M10 11h5"></path>
                        <path d="M10 15h5"></path>
                    </svg>
                </span>
                <span class="sidebar-view-toggle-label">Documentos</span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
