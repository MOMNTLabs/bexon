<?php
$taskPageMode = normalizeTaskPageMode((string) ($taskPageMode ?? ''));
if ($taskPageMode === '' || $taskPageMode === 'select') {
    $taskPageMode = 'all';
}
$taskPageIsChooser = $taskPageMode === 'select';
$taskPageIsProject = $taskPageMode === 'project' && trim((string) ($groupFilter ?? '')) !== '';
$taskPageShowsProjectFilter = $taskPageMode === 'all';
$taskLayout = normalizeTaskLayoutKey((string) ($taskLayout ?? ''));
if ($taskLayout === '') {
    $taskLayout = 'list';
}
$taskCalendarMonth = normalizeTaskCalendarMonth((string) ($taskCalendarMonth ?? ''));
if ($taskCalendarMonth === '') {
    $taskCalendarMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m');
}
$taskAllProjectsPath = dashboardPath('tasks', ['task_scope' => 'all']);
$taskPageBackPath = $taskAllProjectsPath;
$taskShowBackButton = $taskPageIsProject;
$taskCurrentProjectName = $taskPageIsProject ? normalizeTaskGroupName((string) ($groupFilter ?? '')) : '';
$taskCurrentProjectPermission = $taskPageIsProject
    ? ($taskGroupPermissions[$taskCurrentProjectName] ?? ['can_view' => true, 'can_access' => true])
    : ['can_view' => false, 'can_access' => false];
$taskCurrentProjectCanAccess = !empty($taskCurrentProjectPermission['can_access']);
$taskActiveFilterCount = 0;
if ($taskPageShowsProjectFilter && trim((string) ($groupFilter ?? '')) !== '') {
    $taskActiveFilterCount++;
}
$taskActiveFilterCount += $creatorFilterId !== null ? 1 : 0;
$taskActiveFilterCount += $assigneeFilterId !== null ? 1 : 0;
$taskProjectTaskCounts = array_fill_keys(is_array($taskGroups ?? null) ? $taskGroups : [], 0);
foreach (($allTasks ?? []) as $taskProjectCountItem) {
    $taskProjectCountGroup = normalizeTaskGroupName((string) ($taskProjectCountItem['group_name'] ?? 'Geral'));
    if (array_key_exists($taskProjectCountGroup, $taskProjectTaskCounts)) {
        $taskProjectTaskCounts[$taskProjectCountGroup]++;
    }
}

$taskViewBaseParams = [
    'task_scope' => $taskPageIsProject ? 'project' : 'all',
];
if ($taskPageIsProject && $taskCurrentProjectName !== '') {
    $taskViewBaseParams['group'] = $taskCurrentProjectName;
} elseif ($taskPageShowsProjectFilter && trim((string) ($groupFilter ?? '')) !== '') {
    $taskViewBaseParams['group'] = normalizeTaskGroupName((string) $groupFilter);
}
if ($creatorFilterId !== null) {
    $taskViewBaseParams['created_by'] = (string) $creatorFilterId;
}
if ($assigneeFilterId !== null) {
    $taskViewBaseParams['assignee'] = (string) $assigneeFilterId;
}
$taskListViewPath = dashboardPath('tasks', $taskViewBaseParams);
$taskCalendarViewPath = dashboardPath('tasks', array_merge(
    $taskViewBaseParams,
    [
        'task_layout' => 'calendar',
        'calendar_month' => $taskCalendarMonth,
    ]
));
?>
<div class="panel-header board-header task-page-board-header<?= $taskPageIsChooser ? ' is-chooser' : '' ?>">
    <div class="task-page-heading">
        <?php if ($taskShowBackButton): ?>
            <a
                href="<?= e($taskPageBackPath) ?>"
                class="task-page-back-button"
                aria-label="Voltar"
                title="Voltar"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m15 18-6-6 6-6"></path>
                </svg>
            </a>
        <?php endif; ?>
        <div class="task-page-heading-copy">
            <h2>Lista de tarefas</h2>
            <?php if ($taskPageIsChooser): ?>
                <p>Escolha um projeto ou veja tudo na mesma página.</p>
            <?php elseif ($taskPageIsProject): ?>
                <p><?= e($taskCurrentProjectName) ?></p>
            <?php else: ?>
                <p>Todos projetos</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($taskPageIsChooser): ?>
    <div class="task-project-chooser" aria-label="Selecionar projeto" data-task-project-chooser>
        <a href="<?= e($taskAllProjectsPath) ?>" class="task-project-choice task-project-choice-all">
            <span class="task-project-choice-label">Todos projetos</span>
            <strong class="task-project-choice-count"><?= e((string) count($taskGroups ?? [])) ?></strong>
        </a>
        <?php foreach (($taskGroups ?? []) as $taskProjectOption): ?>
            <?php
            $taskProjectName = (string) $taskProjectOption;
            $taskProjectPath = dashboardPath('tasks', ['task_scope' => 'project', 'group' => $taskProjectName]);
            $taskProjectPermission = $taskGroupPermissions[$taskProjectName] ?? ['can_view' => true, 'can_access' => true];
            $taskProjectCanAccess = !empty($taskProjectPermission['can_access']);
            $taskProjectPermissionsModalKey = 'task-group-perm-' . md5($taskProjectName);
            ?>
            <div
                class="task-project-choice task-project-choice-entry<?= $taskProjectCanAccess ? '' : ' is-readonly' ?>"
                data-task-project-choice
            >
                <form
                    method="post"
                    class="task-group-rename-form task-project-choice-main"
                    data-group-rename-form
                    data-task-project-open-path="<?= e($taskProjectPath) ?>"
                    role="link"
                    tabindex="0"
                    aria-label="Abrir projeto <?= e($taskProjectName) ?>"
                >
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="rename_group">
                    <input type="hidden" name="old_group_name" value="<?= e($taskProjectName) ?>">
                    <span class="task-project-choice-label-shell">
                        <span class="task-project-choice-label" data-group-name-display><?= e($taskProjectName) ?></span>
                        <?php if ($taskProjectCanAccess): ?>
                            <button
                                type="button"
                                class="task-group-name-edit-button task-project-choice-edit-button"
                                data-enable-group-rename
                                aria-label="Editar nome do projeto <?= e($taskProjectName) ?>"
                                title="Editar nome do projeto"
                            >
                                <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                    <path d="M11.8 1.8a1.75 1.75 0 0 1 2.47 2.47L6.09 12.45 3 13l.55-3.09L11.8 1.8Zm1.41.71a.75.75 0 0 0-1.06 0l-.77.77 1.06 1.06.77-.77a.75.75 0 0 0 0-1.06ZM11.73 5l-1.06-1.06-6.16 6.16-.24 1.37 1.37-.24L11.73 5Z" fill="currentColor"/>
                                </svg>
                            </button>
                        <?php endif; ?>
                        <?php if (!$taskProjectCanAccess): ?>
                            <span class="task-project-choice-readonly">Somente leitura</span>
                        <?php endif; ?>
                    </span>
                    <input
                        type="text"
                        name="new_group_name"
                        value="<?= e($taskProjectName) ?>"
                        maxlength="60"
                        class="task-group-name-input task-project-choice-name-input"
                        data-group-name-input
                        aria-label="Nome do projeto"
                        spellcheck="false"
                        hidden
                        <?= $taskProjectCanAccess ? 'disabled' : 'readonly disabled' ?>
                    >
                    <strong class="task-project-choice-count"><?= e((string) ($taskProjectTaskCounts[$taskProjectName] ?? 0)) ?></strong>
                    <button type="submit" class="sr-only">Salvar projeto</button>
                </form>

                <?php if ($taskProjectCanAccess): ?>
                    <div class="task-project-choice-actions">
                        <?php if (!empty($canManageWorkspace)): ?>
                            <button
                                type="button"
                                class="task-project-choice-action task-project-choice-access"
                                data-open-group-permissions-modal="<?= e($taskProjectPermissionsModalKey) ?>"
                                aria-label="Gerenciar acesso do projeto <?= e($taskProjectName) ?>"
                            >
                                Acesso
                            </button>
                        <?php endif; ?>

                        <form method="post" class="task-group-delete-form task-project-choice-delete-form" data-group-delete-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="group_name" value="<?= e($taskProjectName) ?>">
                            <button
                                type="button"
                                class="task-project-choice-action task-project-choice-action-icon task-project-choice-delete"
                                data-group-delete
                                aria-label="Excluir projeto <?= e($taskProjectName) ?>"
                                title="Excluir projeto"
                            >
                                <span aria-hidden="true">&#10005;</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($canManageWorkspace)): ?>
        <div class="task-project-chooser-create">
            <button
                type="button"
                class="icon-gear-button task-project-chooser-create-button"
                data-open-create-group-modal
                aria-label="Criar projeto"
                title="Criar projeto"
            >
                <svg class="task-project-chooser-create-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 6v12"></path>
                    <path d="M6 12h12"></path>
                </svg>
            </button>
        </div>
    <?php endif; ?>
<?php else: ?>
    <form
        method="get"
        class="task-filters<?= $taskPageIsProject && $taskCurrentProjectName !== '' ? ' task-filters-has-inline-mobile-actions' : '' ?>"
        id="task-filters"
        data-task-filter-form
    >
        <input type="hidden" name="task_scope" value="<?= e($taskPageIsProject ? 'project' : 'all') ?>">
        <input type="hidden" name="task_layout" value="<?= e($taskLayout) ?>">
        <input type="hidden" name="calendar_month" value="<?= e($taskCalendarMonth) ?>">
        <?php if ($taskPageIsProject && $taskCurrentProjectName !== ''): ?>
            <input type="hidden" name="group" value="<?= e($taskCurrentProjectName) ?>">
        <?php endif; ?>
        <button
            type="button"
            class="task-filters-mobile-toggle<?= $taskActiveFilterCount > 0 ? ' is-active' : '' ?>"
            data-task-filters-toggle
            aria-expanded="false"
            aria-controls="task-filters-panel"
        >
            <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M3 5h14M6 10h8M8 15h4"></path>
            </svg>
            <span>Filtros</span>
            <?php if ($taskActiveFilterCount > 0): ?>
                <span class="task-filters-active-count"><?= e((string) $taskActiveFilterCount) ?></span>
            <?php endif; ?>
        </button>

        <div class="task-filters-fields" id="task-filters-panel" data-task-filters-panel>
            <div class="task-filters-panel-head">
                <strong>Filtrar tarefas</strong>
                <?php if ($taskActiveFilterCount > 0): ?>
                    <button type="button" class="task-filters-clear" data-task-filters-clear>Limpar</button>
                <?php endif; ?>
            </div>

            <?php if ($taskPageShowsProjectFilter): ?>
                <label class="task-filter-field" data-filter-label="Projeto">
                    <?php $groupFilterValue = (string) ($groupFilter ?? ''); ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                            <summary aria-label="Filtrar por projeto">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($groupFilterValue === ''): ?>
                                        Todos Projetos
                                    <?php else: ?>
                                        <?= e($groupFilterValue) ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de projeto">
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $groupFilterValue === '' ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value=""
                                    data-label="Todos Projetos"
                                    role="option"
                                    aria-selected="<?= $groupFilterValue === '' ? 'true' : 'false' ?>"
                                >Todos Projetos</button>
                                <?php foreach (($taskGroups ?? []) as $groupOption): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option<?= $groupFilterValue === (string) $groupOption ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e((string) $groupOption) ?>"
                                        data-label="<?= e((string) $groupOption) ?>"
                                        role="option"
                                        aria-selected="<?= $groupFilterValue === (string) $groupOption ? 'true' : 'false' ?>"
                                    ><?= e((string) $groupOption) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select
                            name="group"
                            class="tag-select row-inline-picker-native"
                            data-inline-select-source
                            hidden
                        >
                            <option value="">Todos Projetos</option>
                            <?php foreach (($taskGroups ?? []) as $groupOption): ?>
                                <option value="<?= e((string) $groupOption) ?>"<?= $groupFilterValue === (string) $groupOption ? ' selected' : '' ?>>
                                    <?= e((string) $groupOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </label>
            <?php endif; ?>

            <label class="task-filter-field" data-filter-label="Criado por">
                <?php $creatorFilterValue = $creatorFilterId !== null ? (string) $creatorFilterId : ''; ?>
                <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                    <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                        <summary aria-label="Filtrar por criador">
                            <span class="row-inline-picker-summary-text" data-inline-select-text>
                                <?php if ($creatorFilterValue === ''): ?>
                                    Criado por
                                <?php else: ?>
                                    <?php
                                    $creatorLabel = 'Criado por';
                                    foreach (($users ?? []) as $user) {
                                        if ((string) ((int) $user['id']) === $creatorFilterValue) {
                                            $creatorLabel = (string) $user['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <?= e($creatorLabel) ?>
                                <?php endif; ?>
                            </span>
                        </summary>
                        <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de criador">
                            <button
                                type="button"
                                class="row-inline-picker-option<?= $creatorFilterValue === '' ? ' is-active' : '' ?>"
                                data-inline-select-option
                                data-value=""
                                data-label="Criado por"
                                role="option"
                                aria-selected="<?= $creatorFilterValue === '' ? 'true' : 'false' ?>"
                            >Criado por</button>
                            <?php foreach (($users ?? []) as $user): ?>
                                <?php $optionValue = (string) ((int) $user['id']); ?>
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $creatorFilterValue === $optionValue ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value="<?= e($optionValue) ?>"
                                    data-label="<?= e((string) $user['name']) ?>"
                                    role="option"
                                    aria-selected="<?= $creatorFilterValue === $optionValue ? 'true' : 'false' ?>"
                                ><?= e((string) $user['name']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <select name="created_by" class="tag-select row-inline-picker-native" data-inline-select-source hidden>
                        <option value="">Criado por</option>
                        <?php foreach (($users ?? []) as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>"<?= $creatorFilterId === (int) $user['id'] ? ' selected' : '' ?>>
                                <?= e((string) $user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </label>

            <label class="task-filter-field" data-filter-label="Responsavel">
                <?php $assigneeFilterValue = $assigneeFilterId !== null ? (string) $assigneeFilterId : ''; ?>
                <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                    <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                        <summary aria-label="Filtrar por responsavel">
                            <span class="row-inline-picker-summary-text" data-inline-select-text>
                                <?php if ($assigneeFilterValue === ''): ?>
                                    Responsavel
                                <?php else: ?>
                                    <?php
                                    $assigneeLabel = 'Responsavel';
                                    foreach (($users ?? []) as $user) {
                                        if ((string) ((int) $user['id']) === $assigneeFilterValue) {
                                            $assigneeLabel = (string) $user['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <?= e($assigneeLabel) ?>
                                <?php endif; ?>
                            </span>
                        </summary>
                        <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de responsavel">
                            <button
                                type="button"
                                class="row-inline-picker-option<?= $assigneeFilterValue === '' ? ' is-active' : '' ?>"
                                data-inline-select-option
                                data-value=""
                                data-label="Responsavel"
                                role="option"
                                aria-selected="<?= $assigneeFilterValue === '' ? 'true' : 'false' ?>"
                            >Responsavel</button>
                            <?php foreach (($users ?? []) as $user): ?>
                                <?php $optionValue = (string) ((int) $user['id']); ?>
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $assigneeFilterValue === $optionValue ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value="<?= e($optionValue) ?>"
                                    data-label="<?= e((string) $user['name']) ?>"
                                    role="option"
                                    aria-selected="<?= $assigneeFilterValue === $optionValue ? 'true' : 'false' ?>"
                                ><?= e((string) $user['name']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <select name="assignee" class="tag-select row-inline-picker-native" data-inline-select-source hidden>
                        <option value="">Responsavel</option>
                        <?php foreach (($users ?? []) as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>"<?= $assigneeFilterId === (int) $user['id'] ? ' selected' : '' ?>>
                                <?= e((string) $user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </label>
        </div>

        <?php if ($taskPageShowsProjectFilter || $taskPageIsProject): ?>
            <div class="task-filters-create<?= $taskPageIsProject ? ' task-filters-create-project' : '' ?>">
                <div class="task-view-toggle-group" role="tablist" aria-label="Visualização das tarefas">
                    <a
                        href="<?= e($taskListViewPath) ?>"
                        class="task-view-toggle<?= $taskLayout === 'list' ? ' is-active' : '' ?>"
                        data-task-view-toggle
                        data-task-view="list"
                        aria-pressed="<?= $taskLayout === 'list' ? 'true' : 'false' ?>"
                    >Lista</a>
                    <a
                        href="<?= e($taskCalendarViewPath) ?>"
                        class="task-view-toggle<?= $taskLayout === 'calendar' ? ' is-active' : '' ?>"
                        data-task-view-toggle
                        data-task-view="calendar"
                        aria-pressed="<?= $taskLayout === 'calendar' ? 'true' : 'false' ?>"
                    >Calendário</a>
                </div>
                <?php if ($taskPageShowsProjectFilter): ?>
                    <button
                        type="button"
                        class="icon-gear-button task-filters-reorder-groups"
                        data-toggle-task-group-reorder
                        aria-label="Ativar organizacao de grupos"
                        aria-pressed="false"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 7h10"></path>
                            <path d="M8 12h10"></path>
                            <path d="M8 17h10"></path>
                            <path d="M5 7h.01"></path>
                            <path d="M5 12h.01"></path>
                            <path d="M5 17h.01"></path>
                        </svg>
                    </button>
                <?php endif; ?>
                <?php if ($taskPageShowsProjectFilter && !empty($canManageWorkspace)): ?>
                    <button
                        type="button"
                        class="icon-gear-button task-filters-create-group"
                        data-open-create-group-modal
                        aria-label="Criar projeto"
                    >
                        <span class="task-filters-create-group-plus" aria-hidden="true">+</span>
                        <span>Projeto</span>
                    </button>
                <?php endif; ?>
                <?php if ($taskPageIsProject && $taskCurrentProjectName !== ''): ?>
                    <?php
                    $taskProjectDoneHidden = !empty(
                        ($storedTaskGroupDoneHiddenMap ?? [])[normalizeStoredTaskGroupStateName($taskCurrentProjectName)]
                    );
                    $taskProjectDoneToggleLabel = $taskProjectDoneHidden ? 'Exibir concluídas' : 'Ocultar concluídas';
                    $taskProjectDoneToggleShortLabel = 'Concluídas';
                    ?>
                    <button
                        type="button"
                        class="task-filters-mobile-toggle task-filters-mobile-toggle-inline<?= $taskActiveFilterCount > 0 ? ' is-active' : '' ?>"
                        data-task-filters-toggle
                        aria-expanded="false"
                        aria-controls="task-filters-panel"
                    >
                        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="M3 5h14M6 10h8M8 15h4"></path>
                        </svg>
                        <span>Filtros</span>
                        <?php if ($taskActiveFilterCount > 0): ?>
                            <span class="task-filters-active-count"><?= e((string) $taskActiveFilterCount) ?></span>
                        <?php endif; ?>
                    </button>
                    <button
                        type="button"
                        class="task-project-done-toggle<?= $taskProjectDoneHidden ? ' is-active' : '' ?>"
                        data-toggle-group-done
                        data-task-group-toggle-name="<?= e($taskCurrentProjectName) ?>"
                        data-label-hide="Ocultar concluídas"
                        data-label-show="Exibir concluídas"
                        data-label-short="Concluídas"
                        aria-pressed="<?= $taskProjectDoneHidden ? 'true' : 'false' ?>"
                        aria-label="<?= e($taskProjectDoneToggleLabel . ' do projeto ' . $taskCurrentProjectName) ?>"
                    >
                        <svg class="task-project-done-toggle-icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <g class="task-project-done-toggle-icon-open">
                                <path d="M3.5 10c1.9-3 4.1-4.5 6.5-4.5s4.6 1.5 6.5 4.5c-1.9 3-4.1 4.5-6.5 4.5S5.4 13 3.5 10Z"></path>
                                <circle cx="10" cy="10" r="1.9"></circle>
                            </g>
                            <g class="task-project-done-toggle-icon-closed">
                                <path d="M4.2 11.8c1.7 1.7 3.6 2.5 5.8 2.5s4.1-.8 5.8-2.5"></path>
                                <path d="M3.8 4.4 16.2 15.6"></path>
                            </g>
                        </svg>
                        <span class="task-project-done-toggle-label"><?= e($taskProjectDoneToggleShortLabel) ?></span>
                    </button>
                <?php endif; ?>
                <?php if ($taskPageIsProject && $taskCurrentProjectCanAccess): ?>
                    <button
                        type="button"
                        class="icon-gear-button task-filters-create-group task-filters-create-task"
                        data-open-create-task-modal
                        data-create-group="<?= e($taskCurrentProjectName) ?>"
                        aria-label="Adicionar tarefa ao projeto <?= e($taskCurrentProjectName) ?>"
                    >
                        <svg class="task-filters-create-task-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 6v12"></path>
                            <path d="M6 12h12"></path>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>
