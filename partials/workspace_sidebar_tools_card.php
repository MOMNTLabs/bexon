<?php
$workspaceSidebarConfig = is_array($workspaceSidebarConfig ?? null)
    ? $workspaceSidebarConfig
    : workspaceSidebarToolsConfig($currentWorkspaceId ?? null, $currentWorkspace ?? null);
$sidebarOptionalLabels = is_array($workspaceSidebarConfig['optional_labels'] ?? null)
    ? $workspaceSidebarConfig['optional_labels']
    : workspaceSidebarOptionalToolLabels();
$enabledOptionalTools = is_array($workspaceSidebarConfig['enabled_optional'] ?? null)
    ? $workspaceSidebarConfig['enabled_optional']
    : [];
$availableToAddTools = is_array($workspaceSidebarConfig['available_to_add'] ?? null)
    ? $workspaceSidebarConfig['available_to_add']
    : array_keys($sidebarOptionalLabels);
?>

<section class="workspace-settings-card workspace-sidebar-tools-card<?= empty($canManageWorkspace) ? ' is-readonly' : '' ?>">
    <?php
    $visibleSidebarToolsCount = 1 + count($enabledOptionalTools);
    $visibleSidebarToolsLabel = $visibleSidebarToolsCount === 1 ? 'item' : 'itens';
    ?>
    <div class="workspace-settings-card-head">
        <div>
            <h3>Ferramentas <span class="workspace-sidebar-tools-count"><?= e((string) count($enabledOptionalTools)) ?></span></h3>
            <p>Arraste para reorganizar <span class="workspace-sidebar-tools-save-state" aria-live="polite">Salvo automaticamente</span></p>
        </div>
    </div>

    <?php if (!empty($canManageWorkspace)): ?>
        <form method="post" class="workspace-settings-form workspace-sidebar-tools-form" data-sidebar-tools-form data-sidebar-tools-autosave-add="1">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="workspace_update_sidebar_tools">

            <label>
                <span>Adicionar ferramenta</span>
                <div class="workspace-sidebar-tools-add-row">
                    <select data-sidebar-tools-add-select>
                        <option value="">Escolha uma ferramenta</option>
                        <?php foreach ($sidebarOptionalLabels as $toolKey => $toolLabel): ?>
                            <?php $toolAvailable = in_array($toolKey, $availableToAddTools, true); ?>
                            <option
                                value="<?= e((string) $toolKey) ?>"
                                <?= $toolAvailable ? '' : 'disabled hidden' ?>
                            >
                                <?= e((string) $toolLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-mini" data-sidebar-tools-add-button>Adicionar</button>
                </div>
            </label>

            <ul class="workspace-sidebar-tools-list" data-sidebar-tools-list aria-label="Ferramentas ativas">
                <?php foreach ($enabledOptionalTools as $toolKey): ?>
                    <?php $toolLabel = (string) ($sidebarOptionalLabels[$toolKey] ?? $toolKey); ?>
                    <li class="workspace-sidebar-tool-item" data-sidebar-tool-key="<?= e((string) $toolKey) ?>" draggable="true">
                        <input type="hidden" name="sidebar_tools[]" value="<?= e((string) $toolKey) ?>" data-sidebar-tool-input>
                        <span class="workspace-sidebar-tool-drag-handle" aria-hidden="true" title="Arraste para reorganizar">⠿</span>
                        <span class="workspace-sidebar-tool-item-label"><?= e($toolLabel) ?></span>
                        <div class="workspace-sidebar-tool-item-actions">
                            <details class="workspace-sidebar-tool-actions-menu">
                                <summary aria-label="Ações da ferramenta" title="Ações"><span aria-hidden="true">&hellip;</span></summary>
                                <div class="workspace-sidebar-tool-actions-popover">
                                    <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="up">↑ Mover para cima</button>
                                    <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="down">↓ Mover para baixo</button>
                                    <button type="button" class="workspace-sidebar-tool-action is-remove" data-sidebar-tools-remove>Remover</button>
                                </div>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="workspace-sidebar-tools-empty" data-sidebar-tools-empty <?= $enabledOptionalTools === [] ? '' : 'hidden' ?>>
                Nenhuma ferramenta adicional no sidebar.
            </p>

            <template data-sidebar-tools-row-template>
                <li class="workspace-sidebar-tool-item" data-sidebar-tool-key="" draggable="true">
                    <input type="hidden" name="sidebar_tools[]" value="" data-sidebar-tool-input>
                    <span class="workspace-sidebar-tool-drag-handle" aria-hidden="true" title="Arraste para reorganizar">⠿</span>
                    <span class="workspace-sidebar-tool-item-label"></span>
                    <div class="workspace-sidebar-tool-item-actions">
                        <details class="workspace-sidebar-tool-actions-menu">
                            <summary aria-label="Ações da ferramenta" title="Ações"><span aria-hidden="true">&hellip;</span></summary>
                            <div class="workspace-sidebar-tool-actions-popover">
                                <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="up">↑ Mover para cima</button>
                                <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="down">↓ Mover para baixo</button>
                                <button type="button" class="workspace-sidebar-tool-action is-remove" data-sidebar-tools-remove>Remover</button>
                            </div>
                        </details>
                    </div>
                </li>
            </template>
        </form>
    <?php else: ?>
        <ul class="workspace-sidebar-tools-readonly-list">
            <li>Lista de tarefas</li>
            <?php foreach ($enabledOptionalTools as $toolKey): ?>
                <li><?= e((string) ($sidebarOptionalLabels[$toolKey] ?? $toolKey)) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
