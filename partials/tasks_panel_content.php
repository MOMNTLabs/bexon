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
                $storedTaskGroupDoneHiddenMap
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

                return [
                    'id' => (int) ($task['id'] ?? 0),
                    'title' => normalizeTaskTitle((string) ($task['title'] ?? '')),
                    'group_name' => $groupName,
                    'due_date' => dueDateForStorage((string) ($task['due_date'] ?? '')),
                    'status_label' => $statusLabel,
                    'status_kind' => $statusKind,
                    'status_color' => $statusColor,
                    'assignee_summary' => assigneeNamesSummary($task),
                    'assignees' => is_array($task['assignees'] ?? null)
                        ? array_values(array_filter($task['assignees'], static fn ($assignee): bool => is_array($assignee)))
                        : [],
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
            $calendarMonthLabel = ($calendarMonthNames[(int) $calendarMonthStart->format('n')] ?? $calendarMonthStart->format('F'))
                . ' '
                . $calendarMonthStart->format('Y');
            ?>
            <div class="task-calendar-layout" data-task-calendar-layout>
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
                                                $calendarGroupMeta = !$taskPageIsProject ? (string) $calendarTask['group_name'] : '';
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
                                                    style="--task-calendar-accent: <?= e((string) $calendarTask['status_color']) ?>;"
                                                    aria-label="Abrir tarefa <?= e((string) $calendarTask['title']) ?>"
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
                                                                <span class="task-calendar-card-meta"><?= e($calendarGroupMeta) ?></span>
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
