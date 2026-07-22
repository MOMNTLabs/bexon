<?php
$statusMetaByKey = is_array($statusConfig['meta_by_key'] ?? null) ? $statusConfig['meta_by_key'] : [];
$storedTaskGroupDoneHiddenMap = storedTaskGroupDoneHiddenMap($currentWorkspaceId ?? null);
require __DIR__ . '/tasks_page_intro.php';
?>

<form method="post" id="task-history-undo-form" class="task-history-form" data-task-history-form data-task-history-action="undo" data-loading-label="Desfazendo...">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="task_undo">
</form>
<form method="post" id="task-history-redo-form" class="task-history-form" data-task-history-form data-task-history-action="redo" data-loading-label="Refazendo...">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="task_redo">
</form>

<datalist id="task-group-options">
    <?php foreach ($taskGroups as $groupNameOption): ?>
        <option value="<?= e((string) $groupNameOption) ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php if ($taskPageMode !== 'select'): ?>
    <div
        class="task-groups-list<?= $taskLayout === 'calendar' ? ' is-calendar-layout' : '' ?>"
        data-task-groups-list
        data-task-layout="<?= e($taskLayout) ?>"
    >
        <?php if ($taskLayout === 'calendar'): ?>
            <?php
            $calendarMonthDate = DateTimeImmutable::createFromFormat('!Y-m', $taskCalendarMonth);
            if (!$calendarMonthDate instanceof DateTimeImmutable) {
                $calendarMonthDate = new DateTimeImmutable('first day of this month');
            }
            $calendarMonthStart = $calendarMonthDate->modify('first day of this month');
            $calendarMonthEnd = $calendarMonthDate->modify('last day of this month');
            $calendarGridStart = $calendarMonthStart->modify('-' . (((int) $calendarMonthStart->format('N')) - 1) . ' days');
            $calendarGridEnd = $calendarMonthEnd->modify('+' . (7 - (int) $calendarMonthEnd->format('N')) . ' days');
            $calendarGridDaySpan = ((int) $calendarGridStart->diff($calendarGridEnd)->format('%a')) + 1;
            $calendarWeekRows = max(1, (int) ceil($calendarGridDaySpan / 7));
            $calendarToday = (new DateTimeImmutable('today'))->format('Y-m-d');
            $calendarTodayMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m');
            $calendarMonthNames = [
                1 => 'Janeiro',
                2 => 'Fevereiro',
                3 => 'Março',
                4 => 'Abril',
                5 => 'Maio',
                6 => 'Junho',
                7 => 'Julho',
                8 => 'Agosto',
                9 => 'Setembro',
                10 => 'Outubro',
                11 => 'Novembro',
                12 => 'Dezembro',
            ];
            $calendarWeekdayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
            $calendarCurrentMonthKey = $calendarMonthStart->format('Y-m');
            $calendarPrevMonth = $calendarMonthStart->modify('-1 month')->format('Y-m');
            $calendarNextMonth = $calendarMonthStart->modify('+1 month')->format('Y-m');
            $calendarPrevPath = dashboardPath('tasks', array_merge(
                $taskViewBaseParams,
                [
                    'task_layout' => 'calendar',
                    'calendar_month' => $calendarPrevMonth,
                ]
            ));
            $calendarNextPath = dashboardPath('tasks', array_merge(
                $taskViewBaseParams,
                [
                    'task_layout' => 'calendar',
                    'calendar_month' => $calendarNextMonth,
                ]
            ));
            $calendarTodayPath = dashboardPath('tasks', array_merge(
                $taskViewBaseParams,
                [
                    'task_layout' => 'calendar',
                    'calendar_month' => $calendarTodayMonth,
                ]
            ));
            $calendarTasksByDate = [];
            $calendarUndatedTaskCount = 0;
            $calendarCurrentMonthTaskCount = 0;
            $buildCalendarTask = static function (array $task) use (
                $statusMetaByKey,
                $statusOptions,
                $taskTitleTagColors,
                $storedTaskGroupDoneHiddenMap,
                $taskGroupPermissions,
                $taskGroupVisuals,
                $currentWorkspaceId
            ): ?array {
                $groupName = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
                $statusKey = normalizeTaskStatus((string) ($task['status'] ?? ''));
                $statusMeta = $statusMetaByKey[$statusKey] ?? taskStatusMeta($statusKey);
                $statusKind = (string) ($task['status_kind'] ?? $statusMeta['kind'] ?? 'todo');
                $groupDoneHidden = !empty(
                    $storedTaskGroupDoneHiddenMap[normalizeStoredTaskGroupStateName($groupName)]
                );
                if ($groupDoneHidden && $statusKind === 'done') {
                    return null;
                }

                $statusLabel = (string) ($task['status_label'] ?? $statusMeta['label'] ?? ($statusOptions[$statusKey] ?? 'A fazer'));
                $statusColor = normalizeTaskStatusColor(
                    (string) ($task['status_color'] ?? $statusMeta['color'] ?? ''),
                    $statusKind
                );
                $titleTag = normalizeTaskTitleTag((string) ($task['title_tag'] ?? ''));
                $titleTagColor = taskTitleTagColorForTag($titleTag, $taskTitleTagColors);
                $groupVisual = $taskGroupVisuals[$groupName]
                    ?? taskGroupVisual($groupName, $currentWorkspaceId ?? null);
                $sourceWorkspaceName = trim((string) ($task['inbox_source_workspace_name'] ?? ''));
                $sourceGroupName = normalizeTaskGroupName((string) ($task['inbox_source_group_name'] ?? ''));
                $sourceGroupVisual = is_array($task['inbox_source_group_visual'] ?? null)
                    ? $task['inbox_source_group_visual']
                    : $groupVisual;

                return [
                    'id' => (int) ($task['id'] ?? 0),
                    'title' => normalizeTaskTitle((string) ($task['title'] ?? '')),
                    'group_name' => $groupName,
                    'group_visual' => $groupVisual,
                    'source_workspace_name' => $sourceWorkspaceName,
                    'source_group_name' => $sourceGroupName,
                    'source_group_visual' => $sourceGroupVisual,
                    'due_date' => dueDateForStorage((string) ($task['due_date'] ?? '')),
                    'status_label' => $statusLabel,
                    'status_kind' => $statusKind,
                    'status_color' => $statusColor,
                    'assignee_summary' => assigneeNamesSummary($task),
                    'assignees' => is_array($task['assignees'] ?? null)
                        ? array_values(array_filter($task['assignees'], static fn ($assignee): bool => is_array($assignee)))
                        : [],
                    'can_drag' => !array_key_exists($groupName, $taskGroupPermissions)
                        || !empty($taskGroupPermissions[$groupName]['can_access']),
                    'title_tag' => $titleTag,
                    'title_tag_color' => $titleTagColor,
                ];
            };

            foreach ($tasks as $task) {
                $calendarTask = $buildCalendarTask($task);
                if ($calendarTask === null) {
                    continue;
                }

                $dueDateValue = (string) ($calendarTask['due_date'] ?? '');
                if ($dueDateValue !== '') {
                    if (!isset($calendarTasksByDate[$dueDateValue])) {
                        $calendarTasksByDate[$dueDateValue] = [];
                    }
                    $calendarTasksByDate[$dueDateValue][] = $calendarTask;
                    if (str_starts_with($dueDateValue, $calendarCurrentMonthKey . '-')) {
                        $calendarCurrentMonthTaskCount++;
                    }
                    continue;
                }

                $calendarUndatedTaskCount++;
            }

            $calendarVisibleTaskCount = 0;
            foreach ($calendarTasksByDate as $calendarDateItems) {
                $calendarVisibleTaskCount += count($calendarDateItems);
            }
            $calendarAgendaDates = [];
            foreach ($calendarTasksByDate as $calendarDateKey => $calendarDateItems) {
                if (
                    !empty($calendarDateItems)
                    && str_starts_with((string) $calendarDateKey, $calendarCurrentMonthKey . '-')
                ) {
                    $calendarAgendaDates[(string) $calendarDateKey] = $calendarDateItems;
                }
            }
            if (!empty($calendarAgendaDates)) {
                ksort($calendarAgendaDates);
            }
            $calendarAgendaWeekdayShort = [
                1 => 'Seg',
                2 => 'Ter',
                3 => 'Qua',
                4 => 'Qui',
                5 => 'Sex',
                6 => 'Sab',
                7 => 'Dom',
            ];
            $calendarMonthLabel = ($calendarMonthNames[(int) $calendarMonthStart->format('n')] ?? $calendarMonthStart->format('F'))
                . ' '
                . $calendarMonthStart->format('Y');
            ?>
            <div
                class="task-calendar-layout"
                data-task-calendar-layout
                data-task-calendar-weeks="<?= e((string) $calendarWeekRows) ?>"
                style="--task-calendar-week-rows: <?= e((string) $calendarWeekRows) ?>;"
            >
                <?php if ($calendarVisibleTaskCount === 0): ?>
                    <div class="empty-card task-list-empty task-calendar-empty">
                        <p>
                            <?php if ($calendarUndatedTaskCount > 0): ?>
                                Nenhuma tarefa com prazo aparece neste calendário. As tarefas sem data continuam disponíveis na visualização em lista.
                            <?php else: ?>
                                Nenhuma tarefa encontrada com os filtros atuais.
                            <?php endif; ?>
                        </p>
                        <button
                            type="button"
                            class="btn btn-mini"
                            data-open-create-task-modal
                            <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>
                        >Nova tarefa</button>
                    </div>
                <?php else: ?>
                    <div class="task-calendar-toolbar">
                        <div class="task-calendar-toolbar-copy">
                            <span class="task-calendar-kicker">Visualização mensal</span>
                            <h3><?= e($calendarMonthLabel) ?></h3>
                        </div>
                        <div class="task-calendar-toolbar-actions">
                            <?php if ($taskCalendarMonth !== $calendarTodayMonth): ?>
                                <a href="<?= e($calendarTodayPath) ?>" class="task-calendar-nav-link">Hoje</a>
                            <?php endif; ?>
                            <a href="<?= e($calendarPrevPath) ?>" class="task-calendar-nav-link" aria-label="Ver mês anterior">Anterior</a>
                            <a href="<?= e($calendarNextPath) ?>" class="task-calendar-nav-link" aria-label="Ver próximo mês">Próximo</a>
                        </div>
                    </div>

                    <div class="task-calendar-main">
                        <div class="task-calendar-grid-shell">
                            <div class="task-calendar-grid">
                                <?php foreach ($calendarWeekdayLabels as $calendarWeekdayLabel): ?>
                                    <div class="task-calendar-weekday"><?= e($calendarWeekdayLabel) ?></div>
                                <?php endforeach; ?>

                                <?php
                                $calendarCursor = $calendarGridStart;
                                while ($calendarCursor <= $calendarGridEnd):
                                    $calendarCellDate = $calendarCursor->format('Y-m-d');
                                    $calendarDayTasks = $calendarTasksByDate[$calendarCellDate] ?? [];
                                    $calendarIsCurrentMonth = $calendarCursor->format('Y-m') === $calendarCurrentMonthKey;
                                    $calendarIsToday = $calendarCellDate === $calendarToday;
                                ?>
                                    <section
                                        class="task-calendar-day<?= $calendarIsCurrentMonth ? '' : ' is-outside-month' ?><?= $calendarIsToday ? ' is-today' : '' ?>"
                                        data-task-calendar-date="<?= e($calendarCellDate) ?>"
                                        aria-label="<?= e($calendarCursor->format('d/m/Y')) ?>"
                                    >
                                        <header class="task-calendar-day-head">
                                            <span class="task-calendar-day-number"><?= e($calendarCursor->format('j')) ?></span>
                                            <?php if ($calendarDayTasks): ?>
                                                <span class="task-calendar-day-count"><?= e((string) count($calendarDayTasks)) ?></span>
                                            <?php endif; ?>
                                        </header>
                                        <div class="task-calendar-day-list">
                                            <?php foreach ($calendarDayTasks as $calendarTask): ?>
                                                <?php
                                                $calendarIsPersonalInboxTask = !empty($taskPageIsPersonalInbox);
                                                $calendarGroupMeta = !$taskPageIsProject
                                                    ? (string) $calendarTask['group_name']
                                                    : ($calendarIsPersonalInboxTask
                                                        ? trim((string) ($calendarTask['source_workspace_name'] ?? ''))
                                                            . ' · '
                                                            . trim((string) ($calendarTask['source_group_name'] ?? ''))
                                                        : '');
                                                $calendarGroupVisual = $calendarIsPersonalInboxTask && is_array($calendarTask['source_group_visual'] ?? null)
                                                    ? $calendarTask['source_group_visual']
                                                    : (is_array($calendarTask['group_visual'] ?? null)
                                                        ? $calendarTask['group_visual']
                                                        : taskGroupVisual($calendarGroupMeta, $currentWorkspaceId ?? null));
                                                $calendarAssigneeSummary = (string) ($calendarTask['assignee_summary'] ?? '');
                                                $calendarAssignees = is_array($calendarTask['assignees'] ?? null)
                                                    ? array_values($calendarTask['assignees'])
                                                    : [];
                                                $calendarHasAssigneeVisual = $calendarAssigneeSummary !== ''
                                                    && $calendarAssigneeSummary !== 'Sem responsável'
                                                    && !empty($calendarAssignees);
                                                $calendarPrimaryAssignee = $calendarHasAssigneeVisual ? $calendarAssignees[0] : null;
                                                ?>
                                                <button
                                                    type="button"
                                                    class="task-calendar-card task-status-<?= e((string) $calendarTask['status_kind']) ?>"
                                                    data-task-calendar-open-task="<?= e((string) $calendarTask['id']) ?>"
                                                    data-task-calendar-task-id="<?= e((string) $calendarTask['id']) ?>"
                                                    data-task-calendar-date="<?= e($calendarCellDate) ?>"
                                                    style="--task-calendar-accent: <?= e((string) $calendarTask['status_color']) ?>;"
                                                    aria-label="Abrir tarefa <?= e((string) $calendarTask['title']) ?>"
                                                    draggable="<?= !empty($calendarTask['can_drag']) ? 'true' : 'false' ?>"
                                                >
                                                    <span class="task-calendar-card-title-row">
                                                        <?php if ((string) ($calendarTask['title_tag'] ?? '') !== ''): ?>
                                                            <span
                                                                class="task-calendar-card-tag"
                                                                style="--wf-tag-color: <?= e((string) $calendarTask['title_tag_color']) ?>;"
                                                            ><?= e((string) $calendarTask['title_tag']) ?></span>
                                                        <?php endif; ?>
                                                        <span class="task-calendar-card-title"><?= e((string) $calendarTask['title']) ?></span>
                                                    </span>
                                                    <?php if ($calendarGroupMeta !== '' || $calendarHasAssigneeVisual): ?>
                                                        <span class="task-calendar-card-meta-row">
                                                            <?php if ($calendarGroupMeta !== ''): ?>
                                                                <span class="task-calendar-card-meta task-calendar-card-project-meta"><?= renderTaskGroupVisual($calendarGroupVisual, 'task-project-visual task-project-visual-calendar', 'span') ?><?= e($calendarGroupMeta) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($calendarHasAssigneeVisual && is_array($calendarPrimaryAssignee)): ?>
                                                                <span
                                                                    class="task-calendar-card-assignees"
                                                                    title="<?= e($calendarAssigneeSummary) ?>"
                                                                    aria-label="Responsáveis: <?= e($calendarAssigneeSummary) ?>"
                                                                >
                                                                    <span class="assignee-summary-avatars<?= count($calendarAssignees) > 1 ? ' has-multiple' : '' ?>" aria-hidden="true">
                                                                        <?php if (count($calendarAssignees) > 1): ?>
                                                                            <span class="assignee-summary-avatar-back"></span>
                                                                        <?php endif; ?>
                                                                        <?= renderUserAvatar($calendarPrimaryAssignee, 'avatar assignee-summary-avatar', true, 'span') ?>
                                                                    </span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <?php $calendarCursor = $calendarCursor->modify('+1 day'); ?>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <?php if (!empty($calendarAgendaDates)): ?>
                            <div class="task-calendar-mobile-agenda" data-task-calendar-mobile-agenda>
                                <div class="task-calendar-mobile-agenda-head">
                                    <strong>Agenda do mes</strong>
                                    <span>
                                        <?= e((string) $calendarCurrentMonthTaskCount) ?>
                                        <?= $calendarCurrentMonthTaskCount === 1 ? ' tarefa' : ' tarefas' ?>
                                    </span>
                                </div>

                                <div class="task-calendar-mobile-agenda-list">
                                    <?php foreach ($calendarAgendaDates as $calendarAgendaDate => $calendarAgendaTasks): ?>
                                        <?php
                                        $calendarAgendaDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $calendarAgendaDate);
                                        if (!$calendarAgendaDateObject instanceof DateTimeImmutable) {
                                            $calendarAgendaDateObject = new DateTimeImmutable((string) $calendarAgendaDate);
                                        }
                                        $calendarAgendaWeekdayKey = (int) $calendarAgendaDateObject->format('N');
                                        $calendarAgendaWeekdayLabel = $calendarAgendaWeekdayShort[$calendarAgendaWeekdayKey]
                                            ?? $calendarAgendaDateObject->format('D');
                                        $calendarAgendaTaskCount = count($calendarAgendaTasks);
                                        ?>
                                        <section class="task-calendar-mobile-day">
                                            <header class="task-calendar-mobile-day-head">
                                                <strong class="task-calendar-mobile-day-date">
                                                    <?= e($calendarAgendaWeekdayLabel . ' • ' . $calendarAgendaDateObject->format('d/m')) ?>
                                                </strong>
                                                <span class="task-calendar-mobile-day-count">
                                                    <?= e((string) $calendarAgendaTaskCount) ?>
                                                </span>
                                            </header>

                                            <div class="task-calendar-mobile-day-items">
                                                <?php foreach ($calendarAgendaTasks as $calendarTask): ?>
                                                    <?php
                                                    $calendarIsPersonalInboxTask = !empty($taskPageIsPersonalInbox);
                                                    $calendarGroupMeta = !$taskPageIsProject
                                                        ? (string) $calendarTask['group_name']
                                                        : ($calendarIsPersonalInboxTask
                                                            ? trim((string) ($calendarTask['source_workspace_name'] ?? ''))
                                                                . ' · '
                                                                . trim((string) ($calendarTask['source_group_name'] ?? ''))
                                                            : '');
                                                    $calendarGroupVisual = $calendarIsPersonalInboxTask && is_array($calendarTask['source_group_visual'] ?? null)
                                                        ? $calendarTask['source_group_visual']
                                                        : (is_array($calendarTask['group_visual'] ?? null)
                                                            ? $calendarTask['group_visual']
                                                            : taskGroupVisual($calendarGroupMeta, $currentWorkspaceId ?? null));
                                                    $calendarAssigneeSummary = (string) ($calendarTask['assignee_summary'] ?? '');
                                                    $calendarAssignees = is_array($calendarTask['assignees'] ?? null)
                                                        ? array_values($calendarTask['assignees'])
                                                        : [];
                                                    $calendarHasAssigneeVisual = $calendarAssigneeSummary !== ''
                                                        && $calendarAssigneeSummary !== 'Sem responsavel'
                                                        && !empty($calendarAssignees);
                                                    $calendarPrimaryAssignee = $calendarHasAssigneeVisual ? $calendarAssignees[0] : null;
                                                    ?>
                                                    <button
                                                        type="button"
                                                        class="task-calendar-card task-calendar-mobile-task task-status-<?= e((string) $calendarTask['status_kind']) ?>"
                                                        data-task-calendar-open-task="<?= e((string) $calendarTask['id']) ?>"
                                                        data-task-calendar-task-id="<?= e((string) $calendarTask['id']) ?>"
                                                        data-task-calendar-date="<?= e((string) $calendarAgendaDate) ?>"
                                                        style="--task-calendar-accent: <?= e((string) $calendarTask['status_color']) ?>;"
                                                        aria-label="Abrir tarefa <?= e((string) $calendarTask['title']) ?>"
                                                        draggable="<?= !empty($calendarTask['can_drag']) ? 'true' : 'false' ?>"
                                                    >
                                                        <span class="task-calendar-card-title-row">
                                                            <?php if ((string) ($calendarTask['title_tag'] ?? '') !== ''): ?>
                                                                <span
                                                                    class="task-calendar-card-tag"
                                                                    style="--wf-tag-color: <?= e((string) $calendarTask['title_tag_color']) ?>;"
                                                                ><?= e((string) $calendarTask['title_tag']) ?></span>
                                                            <?php endif; ?>
                                                            <span class="task-calendar-card-title"><?= e((string) $calendarTask['title']) ?></span>
                                                        </span>
                                                        <?php if ($calendarGroupMeta !== '' || $calendarHasAssigneeVisual): ?>
                                                            <span class="task-calendar-card-meta-row">
                                                                <?php if ($calendarGroupMeta !== ''): ?>
                                                                    <span class="task-calendar-card-meta task-calendar-card-project-meta"><?= renderTaskGroupVisual($calendarGroupVisual, 'task-project-visual task-project-visual-calendar', 'span') ?><?= e($calendarGroupMeta) ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($calendarHasAssigneeVisual && is_array($calendarPrimaryAssignee)): ?>
                                                                    <span
                                                                        class="task-calendar-card-assignees"
                                                                        title="<?= e($calendarAssigneeSummary) ?>"
                                                                        aria-label="Responsaveis: <?= e($calendarAssigneeSummary) ?>"
                                                                    >
                                                                        <span class="assignee-summary-avatars<?= count($calendarAssignees) > 1 ? ' has-multiple' : '' ?>" aria-hidden="true">
                                                                            <?php if (count($calendarAssignees) > 1): ?>
                                                                                <span class="assignee-summary-avatar-back"></span>
                                                                            <?php endif; ?>
                                                                            <?= renderUserAvatar($calendarPrimaryAssignee, 'avatar assignee-summary-avatar', true, 'span') ?>
                                                                        </span>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>
            </div>

            <div class="task-calendar-source" hidden aria-hidden="true">
                <?php require __DIR__ . '/tasks_group_sections.php'; ?>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/tasks_group_sections.php'; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
