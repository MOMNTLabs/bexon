<?php
declare(strict_types=1);

function documentsRedirectPath(int $documentId = 0): string
{
    $params = $documentId > 0 ? ['document' => (string) $documentId] : [];
    return dashboardPath('documents', $params);
}

function handleDocumentsPostAction(PDO $pdo, string $action): bool
{
    if (!in_array($action, ['create_workspace_document', 'update_workspace_document', 'trash_workspace_document'], true)) {
        return false;
    }

    $authUser = requireAuth();
    $workspaceId = activeWorkspaceId($authUser);
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo não encontrado.');
    }
    $userId = (int) ($authUser['id'] ?? 0);
    if ($userId <= 0 || workspaceRoleForUser($userId, $workspaceId) === null) {
        throw new RuntimeException('Você não possui acesso a este workspace.');
    }

    if ($action === 'create_workspace_document') {
        $document = createWorkspaceDocument(
            $pdo,
            $workspaceId,
            $userId,
            (string) ($_POST['title'] ?? '')
        );
        $documentId = (int) ($document['id'] ?? 0);
        if ($documentId <= 0) {
            throw new RuntimeException('Não foi possível criar o documento.');
        }

        if (requestExpectsJson()) {
            respondJson([
                'ok' => true,
                'document' => $document,
                'redirect_path' => documentsRedirectPath($documentId),
            ]);
        }

        redirectTo(documentsRedirectPath($documentId));
    }

    $documentId = max(0, (int) ($_POST['document_id'] ?? 0));
    if ($documentId <= 0) {
        throw new RuntimeException('Documento não encontrado.');
    }

    if ($action === 'trash_workspace_document') {
        if (!trashWorkspaceDocument($pdo, $workspaceId, $documentId)) {
            throw new RuntimeException('O documento já foi removido ou não existe.');
        }

        if (requestExpectsJson()) {
            respondJson([
                'ok' => true,
                'redirect_path' => documentsRedirectPath(),
            ]);
        }

        redirectTo(documentsRedirectPath());
    }

    $expectedRevision = max(0, (int) ($_POST['expected_revision'] ?? 0));
    $document = updateWorkspaceDocument(
        $pdo,
        $workspaceId,
        $documentId,
        $userId,
        (string) ($_POST['title'] ?? ''),
        (string) ($_POST['content_html'] ?? ''),
        $expectedRevision
    );
    if ($document === null) {
        $latest = workspaceDocumentById($workspaceId, $documentId);
        if (requestExpectsJson()) {
            respondJson([
                'ok' => false,
                'code' => 'document_conflict',
                'error' => 'Este documento foi alterado por outra pessoa. Recarregue-o antes de salvar novamente.',
                'document' => $latest,
            ], 409);
        }
        throw new RuntimeException('Este documento foi alterado por outra pessoa. Recarregue-o antes de salvar novamente.');
    }

    if (requestExpectsJson()) {
        respondJson([
            'ok' => true,
            'document' => $document,
            'message' => 'Salvo',
        ]);
    }

    redirectTo(documentsRedirectPath($documentId));
}
