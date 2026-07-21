<?php if (empty($tasksGroupedByGroup)): ?>
    <div class="empty-card task-list-empty">
        <p>Nenhuma tarefa encontrada com os filtros atuais.</p>
        <button
            type="button"
            class="btn btn-mini"
            data-open-create-task-modal
            <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>
        >Nova tarefa</button>
    </div>
<?php else: ?>
    <?php foreach ($tasksGroupedByGroup as $groupName => $groupTasks): ?>
        <?php
        $taskGroupPermission = $taskGroupPermissions[$groupName] ?? ['can_view' => true, 'can_access' => true];
        $taskGroupCanAccess = !empty($taskGroupPermission['can_access']);
        $taskGroupPermissionsModalKey = 'task-group-perm-' . md5((string) $groupName);
        $taskGroupDoneHidden = !empty(
            $storedTaskGroupDoneHiddenMap[normalizeStoredTaskGroupStateName((string) $groupName)]
        );
        $taskGroupDoneToggleLabel = $taskGroupDoneHidden ? 'Exibir concluídas' : 'Ocultar concluídas';
        $groupVisibleTaskCount = 0;
        $groupHiddenDoneCount = 0;
        $taskGroupIsProjectView = !empty($taskPageIsProject);
        ?>
        <section
            class="task-group<?= $taskGroupCanAccess ? '' : ' task-group-readonly' ?><?= $taskGroupDoneHidden ? ' is-done-hidden' : '' ?><?= $taskGroupIsProjectView ? ' task-group-project-view' : '' ?>"
            <?= $taskGroupIsProjectView ? 'aria-label="' . e((string) $groupName) . '"' : 'aria-labelledby="group-' . e(md5((string) $groupName)) . '"' ?>
            data-task-group
            data-group-name="<?= e((string) $groupName) ?>"
            data-group-can-access="<?= $taskGroupCanAccess ? '1' : '0' ?>"
        >
            <?php if (!$taskGroupIsProjectView): ?>
            <header class="task-group-head" data-task-group-head-toggle>
                <div class="task-group-head-main">
                    <form method="post" class="task-group-rename-form" data-group-rename-form>
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="rename_group">
                        <input type="hidden" name="old_group_name" value="<?= e((string) $groupName) ?>">
                        <h3 id="group-<?= e(md5((string) $groupName)) ?>">
                            <span class="task-group-name-shell">
                                <span class="task-group-name-display" data-group-name-display><?= e((string) $groupName) ?></span>
                                <?php if ($taskGroupCanAccess): ?>
                                    <button
                                        type="button"
                                        class="task-group-name-edit-button"
                                        data-enable-group-rename
                                        aria-label="Editar nome do grupo <?= e((string) $groupName) ?>"
                                        title="Editar nome do grupo"
                                    >
                                        <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                            <path d="M11.8 1.8a1.75 1.75 0 0 1 2.47 2.47L6.09 12.45 3 13l.55-3.09L11.8 1.8Zm1.41.71a.75.75 0 0 0-1.06 0l-.77.77 1.06 1.06.77-.77a.75.75 0 0 0 0-1.06ZM11.73 5l-1.06-1.06-6.16 6.16-.24 1.37 1.37-.24L11.73 5Z" fill="currentColor"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </span>
                            <input
                                type="text"
                                name="new_group_name"
                                value="<?= e((string) $groupName) ?>"
                                maxlength="60"
                                class="task-group-name-input"
                                data-group-name-input
                                aria-label="Nome do grupo"
                                spellcheck="false"
                                hidden
                                <?= $taskGroupCanAccess ? 'disabled' : 'readonly disabled' ?>
                            >
                        </h3>
                        <button type="submit" class="sr-only">Salvar grupo</button>
                    </form>
                </div>
                <div class="task-group-head-actions">
                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                    <?php if ($taskGroupCanAccess): ?>
                        <button
                            type="button"
                            class="group-add-button task-group-add-button"
                            data-open-create-task-modal
                            data-create-group="<?= e((string) $groupName) ?>"
                            aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                        >+</button>
                        <details class="task-group-actions-menu" data-inline-select-picker data-task-group-actions-menu>
                            <summary
                                class="task-group-actions-trigger"
                                aria-label="Ações do grupo <?= e((string) $groupName) ?>"
                                title="Ações"
                            >
                                <span aria-hidden="true">&#8942;</span>
                            </summary>
                            <div class="task-group-actions-menu-list" role="menu" aria-label="Ações do grupo <?= e((string) $groupName) ?>">
                                <button
                                    type="button"
                                    class="task-group-actions-menu-item"
                                    data-toggle-group-done
                                    data-label-hide="Ocultar concluídas"
                                    data-label-show="Exibir concluídas"
                                    role="menuitem"
                                    aria-pressed="<?= $taskGroupDoneHidden ? 'true' : 'false' ?>"
                                    aria-label="<?= e($taskGroupDoneToggleLabel) ?> do grupo <?= e((string) $groupName) ?>"
                                ><?= e($taskGroupDoneToggleLabel) ?></button>
                                <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                    <button
                                        type="button"
                                        class="task-group-actions-menu-item"
                                        data-open-group-permissions-modal="<?= e($taskGroupPermissionsModalKey) ?>"
                                        role="menuitem"
                                        aria-label="Gerenciar acesso do grupo <?= e((string) $groupName) ?>"
                                    >
                                        Acesso
                                    </button>
                                <?php endif; ?>
                            </div>
                        </details>
                        <form method="post" class="task-group-delete-form" data-group-delete-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="group_name" value="<?= e((string) $groupName) ?>">
                            <button
                                type="button"
                                class="task-group-delete"
                                data-group-delete
                                aria-label="Excluir grupo <?= e((string) $groupName) ?>"
                            ><span aria-hidden="true">&#10005;</span></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$taskGroupCanAccess): ?>
                        <span class="task-group-readonly-tag">Somente leitura</span>
                    <?php endif; ?>
                    <span class="task-group-count task-group-count-subtle"><?= e((string) count($groupTasks)) ?></span>
                </div>
            </header>
            <?php endif; ?>

            <div class="task-list-rows" data-task-dropzone data-group-name="<?= e((string) $groupName) ?>">
                <?php if (!$groupTasks): ?>
                    <div class="task-group-empty-row">
                        <?php if ($taskGroupCanAccess && !$taskGroupIsProjectView): ?>
                            <button
                                type="button"
                                class="task-group-empty-add"
                                data-open-create-task-modal
                                data-create-group="<?= e((string) $groupName) ?>"
                                aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                            >+</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($groupTasks as $task): ?>
                    <?php
                    $taskId = (int) $task['id'];
                    $priorityKey = normalizeTaskPriority((string) $task['priority']);
                    $statusKey = normalizeTaskStatus((string) $task['status']);
                    $statusMeta = $statusMetaByKey[$statusKey] ?? taskStatusMeta($statusKey);
                    $statusKind = (string) ($task['status_kind'] ?? $statusMeta['kind'] ?? 'todo');
                    $statusLabel = (string) ($task['status_label'] ?? $statusMeta['label'] ?? ($statusOptions[$statusKey] ?? 'A fazer'));
                    $statusOrder = (int) ($task['status_order'] ?? $statusMeta['order'] ?? 1);
                    $statusColor = normalizeTaskStatusColor(
                        (string) ($task['status_color'] ?? $statusMeta['color'] ?? ''),
                        $statusKind
                    );
                    $statusCssVars = (string) ($statusMeta['css_vars'] ?? taskStatusCssVars($statusColor));
                    $assigneeSummary = assigneeNamesSummary($task);
                    $dueDateValue = (string) ($task['due_date'] ?? '');
                    $dueDateUi = taskDueDatePresentation($dueDateValue);
                    $isOverdueMarked = ((int) ($task['overdue_flag'] ?? 0)) === 1;
                    $taskSubtasksDependencyEnabled = normalizePermissionFlag($task['subtasks_dependency_enabled'] ?? 0);
                    $taskSubtasks = is_array($task['subtasks'] ?? null)
                        ? $task['subtasks']
                        : decodeTaskSubtasks($task['subtasks_json'] ?? null, $taskSubtasksDependencyEnabled === 1);
                    $taskSubtasksProgress = taskSubtasksProgress($taskSubtasks, $taskSubtasksDependencyEnabled === 1);
                    $taskSubtasksTotal = (int) ($taskSubtasksProgress['total'] ?? 0);
                    $taskSubtasksCompleted = (int) ($taskSubtasksProgress['completed'] ?? 0);
                    $taskTitleTag = normalizeTaskTitleTag((string) ($task['title_tag'] ?? ''));
                    $taskTitleTagColor = taskTitleTagColorForTag($taskTitleTag, $taskTitleTagColors);
                    $hasActiveRevisionRequest = taskHasActiveRevisionRequest(
                        (string) ($task['description'] ?? ''),
                        is_array($task['history'] ?? null) ? $task['history'] : []
                    );
                    $taskReviewFile = is_array($task['review_file'] ?? null)
                        ? $task['review_file']
                        : decodeTaskReviewFile($task['review_file_json'] ?? null);
                    $hasReviewFile = is_array($taskReviewFile);
                    $taskStartsHidden = $taskGroupDoneHidden && $statusKind === 'done';
                    if ($taskStartsHidden) {
                        $groupHiddenDoneCount++;
                    } else {
                        $groupVisibleTaskCount++;
                    }
                    ?>
                    <article
                        class="task-list-item task-status-<?= e($statusKind) ?><?= $isOverdueMarked ? ' has-overdue-flag' : '' ?><?= $hasReviewFile ? ' has-review-file' : '' ?>"
                        id="task-<?= e((string) $taskId) ?>"
                        data-task-item
                        data-task-readonly="<?= $taskGroupCanAccess ? '0' : '1' ?>"
                        data-group-name="<?= e((string) ($task['group_name'] ?? 'Geral')) ?>"
                        data-status-value="<?= e($statusKey) ?>"
                        data-status-kind="<?= e($statusKind) ?>"
                        data-status-color="<?= e($statusColor) ?>"
                        data-status-order="<?= e((string) $statusOrder) ?>"
                        style="<?= e($statusCssVars) ?>"
                        draggable="<?= $taskGroupCanAccess ? 'true' : 'false' ?>"
                        <?= $taskStartsHidden ? 'hidden' : '' ?>
                    >
                        <form method="post" class="task-list-form" id="update-task-<?= e((string) $taskId) ?>" data-task-autosave-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="update_task">
                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">
                            <input type="hidden" name="autosave" value="1">
                            <input type="hidden" name="include_history" value="1">
                            <input type="hidden" name="reference_links_json" value="<?= e(encodeReferenceUrlList($task['reference_links'] ?? [])) ?>" data-task-reference-links-json>
                            <input type="hidden" value="<?= e(encodeReferenceImageList($task['reference_images'] ?? [])) ?>" data-task-reference-images-json>
                            <input type="hidden" value="<?= e(encodeTaskReviewFile($taskReviewFile)) ?>" data-task-review-file-json>
                            <input type="hidden" name="subtasks_json" value="<?= e(encodeTaskSubtasks($taskSubtasks, $taskSubtasksDependencyEnabled === 1)) ?>" data-task-subtasks-json>
                            <input type="hidden" name="subtasks_dependency_enabled" value="<?= $taskSubtasksDependencyEnabled === 1 ? '1' : '0' ?>" data-task-subtasks-dependency>
                            <input type="hidden" name="title_tag" value="<?= e($taskTitleTag) ?>" data-task-title-tag>
                            <input type="hidden" name="title_tag_color" value="<?= e($taskTitleTagColor) ?>" data-task-title-tag-color>
                            <input type="hidden" name="overdue_flag" value="<?= $isOverdueMarked ? '1' : '0' ?>" data-task-overdue-flag>
                            <input type="hidden" name="overdue_since_date" value="<?= e((string) ($task['overdue_since_date'] ?? '')) ?>" data-task-overdue-since-date>
                            <input type="hidden" value="<?= e((string) (($task['overdue_days'] ?? 0))) ?>" data-task-overdue-days>
                            <input type="hidden" value="<?= e(json_encode(is_array($task['history'] ?? null) ? $task['history'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>" data-task-history-json>
                            <input type="hidden" name="has_active_revision" value="<?= $hasActiveRevisionRequest ? '1' : '0' ?>" data-task-has-active-revision>
                            <input type="hidden" name="expected_updated_at" value="<?= e((string) ($task['updated_at'] ?? '')) ?>" data-task-expected-updated-at>

                            <fieldset class="task-row-fieldset" <?= $taskGroupCanAccess ? '' : 'disabled' ?>>
                            <div class="task-line-row">
                                <div class="task-line-title">
                                    <span
                                        class="task-title-tag-badge"
                                        data-task-title-tag-badge
                                        data-tag-color="<?= e($taskTitleTagColor) ?>"
                                        style="--wf-tag-color: <?= e($taskTitleTagColor) ?>;"
                                        <?= $taskTitleTag === '' ? ' hidden' : '' ?>
                                    ><?= e($taskTitleTag) ?></span>
                                    <input
                                        type="text"
                                        name="title"
                                        value="<?= e((string) $task['title']) ?>"
                                        maxlength="140"
                                        class="task-title-input"
                                        aria-label="Título da tarefa"
                                        required
                                    >
                                    <div
                                        class="task-subtasks-progress<?= $taskSubtasksTotal > 0 ? '' : ' is-hidden' ?>"
                                        data-task-subtasks-progress
                                        aria-label="Progresso das subtarefas"
                                    >
                                        <div class="task-subtasks-progress-steps" data-task-subtasks-progress-steps>
                                            <?php for ($index = 0; $index < $taskSubtasksTotal; $index++): ?>
                                                <?php $isDoneStep = $index < $taskSubtasksCompleted; ?>
                                                <span
                                                    class="task-subtasks-progress-step<?= $isDoneStep ? ' is-done' : '' ?>"
                                                    aria-hidden="true"
                                                ></span>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="task-subtasks-progress-text" data-task-subtasks-progress-text>
                                            <?= e((string) $taskSubtasksCompleted) ?>/<?= e((string) $taskSubtasksTotal) ?> etapas
                                        </span>
                                    </div>
                                </div>

                                <div class="status-stepper" data-status-stepper>
                                    <?php if ($hasActiveRevisionRequest): ?>
                                        <button
                                            type="button"
                                            class="task-revision-badge"
                                            data-task-revision-badge
                                            title="Solicitação de revisão ativa. Clique para remover."
                                            aria-label="Remover solicitação de revisão"
                                        >Revisão</button>
                                    <?php endif; ?>
                                    <?php if ($hasReviewFile): ?>
                                        <span
                                            class="task-review-file-badge"
                                            data-task-review-file-badge
                                            title="Arquivo para revisao anexado"
                                        >Arquivo</span>
                                    <?php endif; ?>
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
                                            class="row-inline-picker status-inline-picker status-<?= e($statusKind) ?>"
                                            data-inline-select-picker
                                            data-status-color="<?= e($statusColor) ?>"
                                            style="<?= e($statusCssVars) ?>"
                                        >
                                            <summary aria-label="Status da tarefa">
                                                <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($statusLabel) ?></span>
                                            </summary>
                                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar status" data-sheet-title="Status">
                                                <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                    <?php
                                                    $optionMeta = $statusMetaByKey[$optionKey] ?? taskStatusMeta($optionKey);
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
                                                        class="row-inline-picker-option status-<?= e($optionKind) ?><?= $optionKey === $statusKey ? ' is-active' : '' ?>"
                                                        data-inline-select-option
                                                        data-value="<?= e($optionKey) ?>"
                                                        data-label="<?= e($optionLabel) ?>"
                                                        data-status-kind="<?= e($optionKind) ?>"
                                                        data-status-color="<?= e($optionColor) ?>"
                                                        data-status-order="<?= e((string) $optionOrder) ?>"
                                                        style="<?= e($optionCssVars) ?>"
                                                        role="option"
                                                        aria-selected="<?= $optionKey === $statusKey ? 'true' : 'false' ?>"
                                                    ><?= e($optionLabel) ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                        <select
                                            name="status"
                                            class="tag-select status-select status-<?= e($statusKind) ?> row-inline-picker-native"
                                            data-inline-select-source
                                            data-status-color="<?= e($statusColor) ?>"
                                            style="<?= e($statusCssVars) ?>"
                                            hidden
                                        >
                                            <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                <?php
                                                $optionMeta = $statusMetaByKey[$optionKey] ?? taskStatusMeta($optionKey);
                                                $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                                $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                                $optionColor = normalizeTaskStatusColor(
                                                    (string) ($optionMeta['color'] ?? ''),
                                                    $optionKind
                                                );
                                                ?>
                                                <option
                                                    value="<?= e($optionKey) ?>"
                                                    data-status-kind="<?= e($optionKind) ?>"
                                                    data-status-color="<?= e($optionColor) ?>"
                                                    data-status-order="<?= e((string) $optionOrder) ?>"
                                                    <?= $optionKey === $statusKey ? ' selected' : '' ?>
                                                >
                                                    <?= e($optionLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button
                                        type="button"
                                        class="status-stepper-btn"
                                        data-status-step="1"
                                        aria-label="Próximo status"
                                    >
                                        <span aria-hidden="true">&#8250;</span>
                                    </button>
                                </div>

                                <div class="tag-field due-tag-field">
                                    <span class="sr-only">Prazo</span>
                                    <?php if ($isOverdueMarked): ?>
                                        <button
                                            type="button"
                                            class="task-overdue-badge"
                                            data-task-overdue-badge
                                            title="Tarefa em atraso. Clique para remover o aviso."
                                            aria-label="Remover aviso de atraso"
                                        >Atraso</button>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="due-date-display<?= !empty($dueDateUi['is_relative']) ? ' is-relative' : '' ?>"
                                        data-due-date-display
                                        aria-label="Prazo: <?= e((string) $dueDateUi['title']) ?>"
                                    ><?= e((string) $dueDateUi['display']) ?></button>
                                    <input
                                        type="date"
                                        name="due_date"
                                        value="<?= e($dueDateValue) ?>"
                                        class="due-date-input due-date-input-overlay"
                                        data-due-date-input
                                    >
                                </div>

                                <div class="tag-field assignee-tag-field">
                                    <details class="assignee-picker row-assignee-picker">
                                        <summary><?= e($assigneeSummary) ?></summary>
                                        <div class="assignee-picker-menu" aria-label="Selecionar responsáveis" data-sheet-title="Responsáveis">
                                            <?php foreach ($users as $user): ?>
                                                <label class="assignee-option">
                                                    <input
                                                        type="checkbox"
                                                        name="assigned_to[]"
                                                        value="<?= e((string) $user['id']) ?>"
                                                        data-assignee-name="<?= e((string) $user['name']) ?>"
                                                        data-assignee-avatar="<?= e(userAvatarImageSrc($user)) ?>"
                                                        data-assignee-initial="<?= e(userDisplayInitial((string) $user['name'])) ?>"
                                                        <?= in_array((int) $user['id'], $task['assignee_ids'] ?? [], true) ? 'checked' : '' ?>
                                                    >
                                                    <?= renderUserAvatar($user, 'avatar small assignee-option-avatar', true, 'span') ?>
                                                    <span class="assignee-option-text"><?= e((string) $user['name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                </div>

                                <div class="tag-field tag-field-priority row-inline-picker-wrap" data-inline-select-wrap data-inline-picker-kind="priority">
                                    <details class="row-inline-picker priority-inline-picker priority-<?= e($priorityKey) ?>" data-inline-select-picker>
                                        <summary aria-label="Prioridade da tarefa">
                                            <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                            <span class="row-inline-picker-summary-text sr-only" data-inline-select-text><?= e((string) ($priorityOptions[$priorityKey] ?? 'Média')) ?></span>
                                        </summary>
                                        <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar prioridade" data-sheet-title="Prioridade">
                                            <?php foreach ($priorityOptions as $optionKey => $optionLabel): ?>
                                                <button
                                                    type="button"
                                                    class="row-inline-picker-option priority-<?= e($optionKey) ?><?= $optionKey === $priorityKey ? ' is-active' : '' ?>"
                                                    data-inline-select-option
                                                    data-value="<?= e($optionKey) ?>"
                                                    data-label="<?= e($optionLabel) ?>"
                                                    role="option"
                                                    aria-selected="<?= $optionKey === $priorityKey ? 'true' : 'false' ?>"
                                                >
                                                    <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                                    <span><?= e($optionLabel) ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <select name="priority" class="tag-select priority-select priority-<?= e($priorityKey) ?> row-inline-picker-native" data-inline-select-source hidden>
                                        <?php foreach ($priorityOptions as $optionKey => $optionLabel): ?>
                                            <option value="<?= e($optionKey) ?>"<?= $optionKey === $priorityKey ? ' selected' : '' ?>>
                                                &#9873;
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    form="delete-task-<?= e((string) $taskId) ?>"
                                    class="task-row-delete"
                                    aria-label="Excluir tarefa"
                                >
                                    <span aria-hidden="true">&#10005;</span>
                                </button>

                                <button
                                    type="button"
                                    class="task-expand-toggle"
                                    data-task-expand
                                    aria-label="Abrir tarefa"
                                >
                                    <span class="sr-only">Abrir tarefa</span>
                                </button>
                            </div>

                            <div class="task-line-details" id="task-details-<?= e((string) $taskId) ?>" hidden>
                                <div class="task-line-details-grid">
                                    <label class="task-group-select-wrap">
                                        <select
                                            name="group_name"
                                            class="tag-select group-tag-select"
                                            data-task-group-select
                                            aria-label="Grupo"
                                        >
                                            <?php
                                            $currentTaskGroup = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
                                            $groupRendered = false;
                                            foreach ($taskGroupsWithAccess as $groupNameOption):
                                                $optionValue = normalizeTaskGroupName((string) $groupNameOption);
                                                $selected = $optionValue === $currentTaskGroup;
                                                if ($selected) {
                                                    $groupRendered = true;
                                                }
                                            ?>
                                                <option value="<?= e($optionValue) ?>"<?= $selected ? ' selected' : '' ?>><?= e($optionValue) ?></option>
                                            <?php endforeach; ?>
                                            <?php if (!$groupRendered): ?>
                                                <option value="<?= e($currentTaskGroup) ?>" selected><?= e($currentTaskGroup) ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </label>

                                    <label>
                                        <span>Descrição</span>
                                        <textarea name="description" rows="3" maxlength="<?= e((string) taskDescriptionMaxLength()) ?>"><?= e((string) $task['description']) ?></textarea>
                                    </label>
                                </div>

                                <div class="task-line-footer">
                                    <div class="task-line-meta">
                                        <span>Criado por <?= e((string) $task['creator_name']) ?></span>
                                        <?php if (!empty($task['updated_at'])): ?>
                                            <span data-task-updated-at>Atualizado em <?= e((new DateTimeImmutable((string) $task['updated_at']))->format('d/m H:i')) ?></span>
                                        <?php endif; ?>
                                        <span class="task-autosave-status" data-task-autosave-status role="status" aria-live="polite"></span>
                                    </div>

                                </div>
                            </div>
                            </fieldset>
                        </form>

                        <form method="post" id="delete-task-<?= e((string) $taskId) ?>" class="task-delete-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_task">
                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if ($groupHiddenDoneCount > 0 && $groupVisibleTaskCount === 0 && $groupTasks): ?>
                    <div class="task-group-hidden-done-row" data-task-group-hidden-done-row>
                        <?= e($groupHiddenDoneCount === 1 ? '1 tarefa concluída oculta.' : $groupHiddenDoneCount . ' tarefas concluídas ocultas.') ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
