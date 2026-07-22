<?php
declare(strict_types=1);

function documentsRedirectPath(int $documentId = 0, bool $trash = false): string
{
    $params = $documentId > 0 ? ['document' => (string) $documentId] : [];
    if ($trash) {
        $params['documents_scope'] = 'trash';
    }
    return dashboardPath('documents', $params);
}

function workspaceDocumentLinkMetadata(PDO $pdo, int $workspaceId, int $userId): array
{
    $rawProjectName = trim((string) ($_POST['task_group_name'] ?? ''));
    $projectName = $rawProjectName !== '' ? normalizeTaskGroupName($rawProjectName) : '';
    $linkedTaskId = max(0, (int) ($_POST['linked_task_id'] ?? 0));

    if ($linkedTaskId > 0) {
        $taskStmt = $pdo->prepare(
            'SELECT id, group_name FROM tasks WHERE id = :task_id AND workspace_id = :workspace_id LIMIT 1'
        );
        $taskStmt->execute([':task_id' => $linkedTaskId, ':workspace_id' => $workspaceId]);
        $task = $taskStmt->fetch();
        if (!$task) {
            throw new RuntimeException('A tarefa vinculada nÃ£o existe neste workspace.');
        }
        $projectName = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
    }

    if ($projectName !== '' && !userCanViewTaskGroup($userId, $workspaceId, $projectName)) {
        throw new RuntimeException('VocÃª nÃ£o possui acesso a esse projeto.');
    }

    return [
        'task_group_name' => $projectName,
        'linked_task_id' => $linkedTaskId,
        'is_favorite' => !empty($_POST['is_favorite']),
    ];
}

function handleDocumentsPostAction(PDO $pdo, string $action): bool
{
    if (!in_array($action, [
        'create_workspace_document',
        'update_workspace_document',
        'trash_workspace_document',
        'restore_workspace_document',
        'restore_workspace_document_revision',
        'touch_workspace_document_presence',
    ], true)) {
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

    if ($action === 'restore_workspace_document') {
        if (!restoreWorkspaceDocument($pdo, $workspaceId, $documentId)) {
            throw new RuntimeException('O documento nÃ£o estÃ¡ na lixeira.');
        }
        if (requestExpectsJson()) {
            respondJson([
                'ok' => true,
                'redirect_path' => documentsRedirectPath($documentId),
            ]);
        }
        redirectTo(documentsRedirectPath($documentId));
    }

    if ($action === 'touch_workspace_document_presence') {
        if (requestExpectsJson()) {
            respondJson([
                'ok' => true,
                'active_editors' => touchWorkspaceDocumentPresence($pdo, $workspaceId, $documentId, $userId),
            ]);
        }
        redirectTo(documentsRedirectPath($documentId));
    }

    $expectedRevision = max(0, (int) ($_POST['expected_revision'] ?? 0));
    if ($action === 'restore_workspace_document_revision') {
        $revisionId = max(0, (int) ($_POST['revision_id'] ?? 0));
        $document = restoreWorkspaceDocumentRevision(
            $pdo,
            $workspaceId,
            $documentId,
            $revisionId,
            $userId,
            $expectedRevision
        );
        if ($document === null) {
            throw new RuntimeException('NÃ£o foi possÃ­vel restaurar esta versÃ£o. Recarregue o documento e tente novamente.');
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

    $document = updateWorkspaceDocument(
        $pdo,
        $workspaceId,
        $documentId,
        $userId,
        (string) ($_POST['title'] ?? ''),
        (string) ($_POST['content_html'] ?? ''),
        $expectedRevision,
        workspaceDocumentLinkMetadata($pdo, $workspaceId, $userId)
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
