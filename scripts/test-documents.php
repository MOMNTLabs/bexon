<?php
declare(strict_types=1);

/**
 * Checks document sanitization, formatting persistence and optimistic locking.
 *
 * Run with:
 *   php scripts/test-documents.php
 */

session_save_path(sys_get_temp_dir());
require_once dirname(__DIR__) . '/bootstrap.php';

function documentTestAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, sprintf(
        "FAIL: %s\nExpected: %s\nActual: %s\n",
        $message,
        var_export($expected, true),
        var_export($actual, true)
    ));
    exit(1);
}

$sanitized = sanitizeWorkspaceDocumentHtml(
    '<div class="ignored" onclick="alert(1)">Primeira</div>'
    . '<p style="color:red">Segunda<br data-extra="x">linha</p>'
    . '<script>alert(1)</script>'
    . '<a href="javascript:alert(1)" onmouseover="alert(1)">ruim</a>'
    . '<a href="https://example.com/path">seguro</a>'
    . '<input type="text" checked onclick="alert(1)">'
);

documentTestAssertSame(false, str_contains($sanitized, '<script'), 'Scripts devem ser removidos.');
documentTestAssertSame(false, str_contains($sanitized, 'onclick'), 'Eventos inline devem ser removidos.');
documentTestAssertSame(false, str_contains($sanitized, 'style='), 'Estilos inline devem ser removidos.');
documentTestAssertSame(false, str_contains($sanitized, 'javascript:'), 'Links inseguros devem perder o destino.');
documentTestAssertSame(
    true,
    str_contains($sanitized, '<a href="https://example.com/path" rel="noopener noreferrer" target="_blank">seguro</a>'),
    'Links seguros devem abrir isolados em outra aba.'
);
documentTestAssertSame(
    true,
    str_contains($sanitized, '<input type="checkbox" data-document-checkbox="1" checked>'),
    'Checklists devem preservar o estado marcado.'
);
documentTestAssertSame(
    'Primeira Segunda linha ruim seguro',
    workspaceDocumentTextFromHtml('<div>Primeira</div><p>Segunda<br>linha</p><a>ruim</a> <a>seguro</a>'),
    'O texto de busca e prévia deve manter espaços entre blocos e quebras.'
);
documentTestAssertSame('Documento sem título', normalizeWorkspaceDocumentTitle('   '), 'O título padrão deve estar correto.');

$pdo = db();
ensureWorkspaceSchema($pdo);
ensureWorkspaceDocumentsSchema($pdo);
$member = $pdo->query(
    'SELECT workspace_id, user_id FROM workspace_members ORDER BY id ASC LIMIT 1'
)->fetch();

if ($member) {
    $workspaceId = (int) ($member['workspace_id'] ?? 0);
    $userId = (int) ($member['user_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $created = createWorkspaceDocument($pdo, $workspaceId, $userId, 'Teste temporário de documentos');
        $documentId = (int) ($created['id'] ?? 0);
        documentTestAssertSame(true, $documentId > 0, 'Um documento deve ser criado.');
        documentTestAssertSame(1, (int) ($created['revision'] ?? 0), 'O documento deve começar na revisão 1.');

        $updated = updateWorkspaceDocument(
            $pdo,
            $workspaceId,
            $documentId,
            $userId,
            'Documento atualizado',
            '<div>Primeira linha</div><div>Segunda linha</div>',
            1,
            ['is_favorite' => true]
        );
        documentTestAssertSame(2, (int) ($updated['revision'] ?? 0), 'Salvar deve incrementar a revisão.');
        documentTestAssertSame(
            'Primeira linha Segunda linha',
            (string) ($updated['content_text'] ?? ''),
            'Quebras de linha devem produzir uma prévia legível.'
        );
        documentTestAssertSame(
            null,
            updateWorkspaceDocument($pdo, $workspaceId, $documentId, $userId, 'Conflito', '<p>Conflito</p>', 1),
            'Uma revisão antiga não pode sobrescrever alterações mais novas.'
        );

        $history = workspaceDocumentRevisionHistory($workspaceId, $documentId);
        documentTestAssertSame(1, count($history), 'A versão anterior deve ser arquivada.');
        documentTestAssertSame(true, trashWorkspaceDocument($pdo, $workspaceId, $documentId), 'Mover para a lixeira deve funcionar.');
        documentTestAssertSame(true, restoreWorkspaceDocument($pdo, $workspaceId, $documentId), 'Restaurar da lixeira deve funcionar.');
    } finally {
        $pdo->rollBack();
    }
} else {
    echo "Document database checks skipped: no workspace member found.\n";
}

echo "Document checks passed.\n";
