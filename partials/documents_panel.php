<?php
$documents = is_array($documents ?? null) ? $documents : [];
$selectedDocument = is_array($selectedDocument ?? null) ? $selectedDocument : null;
$documentsSearch = trim((string) ($documentsSearch ?? ''));
$documentsScope = ($documentsScope ?? '') === 'trash' ? 'trash' : 'all';
$documentHistory = is_array($documentHistory ?? null) ? $documentHistory : [];
$documentLinkTasks = is_array($documentLinkTasks ?? null) ? $documentLinkTasks : [];
$documentProjects = is_array($taskGroups ?? null) ? $taskGroups : [];
$selectedDocumentId = (int) ($selectedDocument['id'] ?? 0);
$documentsBasePath = dashboardPath('documents');
$documentsTrashPath = dashboardPath('documents', ['documents_scope' => 'trash']);
$selectedProject = trim((string) ($selectedDocument['task_group_name'] ?? ''));
$selectedTaskId = (int) ($selectedDocument['linked_task_id'] ?? 0);
?>
<section class="documents-wrap panel" id="documents" data-dashboard-view-panel="documents"<?= ($serverSelectedDashboardView ?? '') !== 'documents' ? ' hidden' : '' ?>>
    <header class="panel-header board-header documents-header">
        <div>
            <h2>Documentos</h2>
            <p>Notas e arquivos de texto do workspace.</p>
        </div>
        <button type="button" class="btn btn-mini" data-document-create>Novo documento</button>
    </header>

    <div
        class="documents-layout<?= $selectedDocument ? ' has-document' : '' ?>"
        data-documents-root
        data-document-id="<?= e((string) $selectedDocumentId) ?>"
        data-document-revision="<?= e((string) ($selectedDocument['revision'] ?? 0)) ?>"
        data-document-csrf="<?= e(csrfToken()) ?>"
    >
        <aside class="documents-sidebar" aria-label="Lista de documentos">
            <div class="documents-sidebar-head">
                <a href="<?= e($documentsBasePath) ?>" class="documents-scope-link<?= $documentsScope === 'all' ? ' is-active' : '' ?>">Todos</a>
                <a href="<?= e($documentsTrashPath) ?>" class="documents-scope-link<?= $documentsScope === 'trash' ? ' is-active' : '' ?>">Lixeira</a>
            </div>
            <label class="sr-only" for="documents-search">Buscar documentos</label>
            <input
                id="documents-search"
                type="search"
                class="documents-search"
                value="<?= e($documentsSearch) ?>"
                placeholder="Buscar documentos"
                data-document-search
                autocomplete="off"
            >
            <small class="documents-search-state" data-document-search-state></small>
            <div class="documents-list" data-documents-list>
                <?php if (!$documents): ?>
                    <p class="documents-list-empty"><?= $documentsScope === 'trash' ? 'A lixeira está vazia.' : 'Nenhum documento ainda.' ?></p>
                <?php else: ?>
                    <?php foreach ($documents as $document): ?>
                        <?php
                        $documentId = (int) ($document['id'] ?? 0);
                        $documentTitle = normalizeWorkspaceDocumentTitle((string) ($document['title'] ?? ''));
                        $documentPreview = trim((string) ($document['content_text'] ?? ''));
                        $documentUpdatedAt = trim((string) ($document['updated_at'] ?? ''));
                        $documentProjectName = trim((string) ($document['task_group_name'] ?? ''));
                        $documentPath = dashboardPath('documents', array_filter([
                            'document' => (string) $documentId,
                            'documents_scope' => $documentsScope === 'trash' ? 'trash' : null,
                        ]));
                        ?>
                        <a
                            href="<?= e($documentPath) ?>"
                            class="documents-list-item<?= $documentId === $selectedDocumentId ? ' is-active' : '' ?>"
                            data-document-list-item
                            data-document-search-text="<?= e(mb_strtolower($documentTitle . ' ' . $documentPreview)) ?>"
                        >
                            <strong><?= !empty($document['is_favorite']) ? '★ ' : '' ?><?= e($documentTitle) ?></strong>
                            <span><?= e($documentPreview !== '' ? mb_substr($documentPreview, 0, 78) : 'Documento vazio') ?></span>
                            <?php if ($documentProjectName !== ''): ?><small class="documents-list-project"><?= e($documentProjectName) ?></small><?php endif; ?>
                            <small><?= e($documentUpdatedAt !== '' ? date('d/m H:i', strtotime($documentUpdatedAt)) : '') ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <article class="document-editor-card<?= $selectedDocument ? '' : ' is-empty' ?><?= !empty($selectedDocument['deleted_at']) ? ' is-trashed' : '' ?>">
            <?php if ($selectedDocument): ?>
                <a href="<?= e($documentsBasePath) ?>" class="document-mobile-back" aria-label="Voltar para a lista de documentos">
                    <span aria-hidden="true">&#8249;</span>
                    <span>Documentos</span>
                </a>
            <?php endif; ?>
            <?php if ($selectedDocument && !empty($selectedDocument['deleted_at'])): ?>
                <div class="documents-empty-state">
                    <h3><?= e(normalizeWorkspaceDocumentTitle((string) ($selectedDocument['title'] ?? ''))) ?></h3>
                    <p>Este documento está na lixeira e não aparece para o workspace.</p>
                    <button type="button" class="btn" data-document-restore>Restaurar documento</button>
                </div>
            <?php elseif ($selectedDocument): ?>
                <div class="document-editor-topbar">
                    <button
                        type="button"
                        class="document-favorite-button<?= !empty($selectedDocument['is_favorite']) ? ' is-active' : '' ?>"
                        data-document-favorite
                        aria-pressed="<?= !empty($selectedDocument['is_favorite']) ? 'true' : 'false' ?>"
                        aria-label="Favoritar documento"
                        title="Favoritar documento"
                    >★</button>
                    <span class="document-save-state" data-document-save-state aria-live="polite">Salvo</span>
                    <button type="button" class="document-trash-button" data-document-trash aria-label="Mover documento para a lixeira" title="Mover para a lixeira">&times;</button>
                </div>
                <input
                    type="text"
                    class="document-title-input"
                    value="<?= e(normalizeWorkspaceDocumentTitle((string) ($selectedDocument['title'] ?? ''))) ?>"
                    maxlength="160"
                    aria-label="Título do documento"
                    data-document-title
                >
                <div class="document-link-fields" aria-label="Vínculos do documento">
                    <label>
                        <span>Projeto</span>
                        <select data-document-project>
                            <option value="">Sem projeto</option>
                            <?php foreach ($documentProjects as $projectName): ?>
                                <option value="<?= e((string) $projectName) ?>"<?= $selectedProject === (string) $projectName ? ' selected' : '' ?>><?= e((string) $projectName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Tarefa</span>
                        <select data-document-task>
                            <option value="">Sem tarefa</option>
                            <?php foreach ($documentLinkTasks as $task): ?>
                                <?php $taskId = (int) ($task['id'] ?? 0); ?>
                                <option value="<?= e((string) $taskId) ?>" data-project="<?= e((string) ($task['group_name'] ?? '')) ?>"<?= $selectedTaskId === $taskId ? ' selected' : '' ?>><?= e((string) ($task['title'] ?? 'Tarefa')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="document-editor-toolbar" role="toolbar" aria-label="Formatação do documento">
                    <button type="button" data-document-command="bold" aria-label="Negrito"><strong>B</strong></button>
                    <button type="button" data-document-command="italic" aria-label="Itálico"><em>I</em></button>
                    <button type="button" data-document-command="underline" aria-label="Sublinhado"><u>U</u></button>
                    <span></span>
                    <button type="button" data-document-command="formatBlock" data-document-command-value="h2">Título</button>
                    <button type="button" data-document-command="insertUnorderedList">Lista</button>
                    <button type="button" data-document-command="insertOrderedList">Numerada</button>
                    <button type="button" data-document-command="insertCheckbox">Checklist</button>
                    <button type="button" data-document-command="createLink">Link</button>
                </div>
                <div
                    class="document-editor"
                    contenteditable="true"
                    role="textbox"
                    aria-multiline="true"
                    aria-label="Conteúdo do documento"
                    data-document-editor
                ><?= (string) ($selectedDocument['content_html'] ?? '<p><br></p>') ?></div>
                <footer class="document-editor-footer">
                    <span>Use <strong>-</strong>, <strong>1.</strong> ou <strong>[]</strong> no começo da linha para criar listas.</span>
                    <span data-document-editors></span>
                    <span><?= e(trim((string) ($selectedDocument['updated_by_name'] ?? '')) !== '' ? 'Última edição por ' . (string) $selectedDocument['updated_by_name'] : '') ?></span>
                </footer>
                <?php if ($documentHistory): ?>
                    <details class="document-history">
                        <summary>Histórico de versões (<?= e((string) count($documentHistory)) ?>)</summary>
                        <div>
                            <?php foreach ($documentHistory as $history): ?>
                                <div class="document-history-item">
                                    <span>v<?= e((string) ($history['document_revision'] ?? '')) ?> · <?= e(date('d/m H:i', strtotime((string) ($history['created_at'] ?? '')))) ?><?= trim((string) ($history['changed_by_name'] ?? '')) !== '' ? ' · ' . e((string) $history['changed_by_name']) : '' ?></span>
                                    <button type="button" data-document-restore-revision data-document-revision-id="<?= e((string) ($history['id'] ?? 0)) ?>">Restaurar</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php else: ?>
                <div class="documents-empty-state">
                    <h3>Comece um documento</h3>
                    <p>Crie notas, briefings ou referências para compartilhar com o workspace.</p>
                    <button type="button" class="btn" data-document-create>Criar documento</button>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>
