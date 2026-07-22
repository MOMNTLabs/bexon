<?php
$documents = is_array($documents ?? null) ? $documents : [];
$selectedDocument = is_array($selectedDocument ?? null) ? $selectedDocument : null;
$documentsSearch = trim((string) ($documentsSearch ?? ''));
$selectedDocumentId = (int) ($selectedDocument['id'] ?? 0);
$documentsBasePath = dashboardPath('documents');
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
        class="documents-layout"
        data-documents-root
        data-document-id="<?= e((string) $selectedDocumentId) ?>"
        data-document-revision="<?= e((string) ($selectedDocument['revision'] ?? 0)) ?>"
        data-document-csrf="<?= e(csrfToken()) ?>"
    >
        <aside class="documents-sidebar" aria-label="Lista de documentos">
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
            <div class="documents-list" data-documents-list>
                <?php if (!$documents): ?>
                    <p class="documents-list-empty">Nenhum documento ainda.</p>
                <?php else: ?>
                    <?php foreach ($documents as $document): ?>
                        <?php
                        $documentId = (int) ($document['id'] ?? 0);
                        $documentTitle = normalizeWorkspaceDocumentTitle((string) ($document['title'] ?? ''));
                        $documentPreview = trim((string) ($document['content_text'] ?? ''));
                        $documentUpdatedAt = trim((string) ($document['updated_at'] ?? ''));
                        $documentPath = dashboardPath('documents', ['document' => (string) $documentId]);
                        ?>
                        <a
                            href="<?= e($documentPath) ?>"
                            class="documents-list-item<?= $documentId === $selectedDocumentId ? ' is-active' : '' ?>"
                            data-document-list-item
                            data-document-search-text="<?= e(mb_strtolower($documentTitle . ' ' . $documentPreview)) ?>"
                        >
                            <strong><?= e($documentTitle) ?></strong>
                            <span><?= e($documentPreview !== '' ? mb_substr($documentPreview, 0, 78) : 'Documento vazio') ?></span>
                            <small><?= e($documentUpdatedAt !== '' ? date('d/m H:i', strtotime($documentUpdatedAt)) : '') ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <article class="document-editor-card<?= $selectedDocument ? '' : ' is-empty' ?>">
            <?php if ($selectedDocument): ?>
                <div class="document-editor-topbar">
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
                <div class="document-editor-toolbar" role="toolbar" aria-label="Formatação do documento">
                    <button type="button" data-document-command="bold" aria-label="Negrito"><strong>B</strong></button>
                    <button type="button" data-document-command="italic" aria-label="Itálico"><em>I</em></button>
                    <button type="button" data-document-command="underline" aria-label="Sublinhado"><u>U</u></button>
                    <span></span>
                    <button type="button" data-document-command="formatBlock" data-document-command-value="h2">Título</button>
                    <button type="button" data-document-command="insertUnorderedList">Lista</button>
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
                    <span>Formatação simples. O conteúdo é salvo automaticamente.</span>
                    <span><?= e(trim((string) ($selectedDocument['updated_by_name'] ?? '')) !== '' ? 'Última edição por ' . (string) $selectedDocument['updated_by_name'] : '') ?></span>
                </footer>
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
