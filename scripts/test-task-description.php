<?php
declare(strict_types=1);

/**
 * Checks task-description normalization and storage limits.
 *
 * Run with:
 *   php scripts/test-task-description.php
 */

session_save_path(sys_get_temp_dir());

require_once dirname(__DIR__) . '/bootstrap.php';

function taskDescriptionTestAssertSame(mixed $expected, mixed $actual, string $message): void
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

$normalized = normalizeTaskDescription(
    "\n  Linha preservada\r\n\tOutra linha\u{00A0}\u{200B}\n\n"
);
taskDescriptionTestAssertSame(
    "  Linha preservada\n\tOutra linha ",
    $normalized,
    'A normalização deve preservar a indentação e remover apenas ruído invisível.'
);

$maximumDescription = str_repeat('a', taskDescriptionMaxLength());
taskDescriptionTestAssertSame(
    $maximumDescription,
    taskDescriptionForStorage($maximumDescription),
    'O limite exato deve ser aceito.'
);

$limitRejected = false;
try {
    taskDescriptionForStorage($maximumDescription . 'b');
} catch (RuntimeException $exception) {
    $limitRejected = str_contains($exception->getMessage(), (string) taskDescriptionMaxLength());
}
taskDescriptionTestAssertSame(true, $limitRejected, 'Descrições acima do limite devem ser rejeitadas.');

$versionTimestamp = taskVersionTimestamp();
taskDescriptionTestAssertSame(
    1,
    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/', $versionTimestamp),
    'O controle de concorrência deve usar microssegundos.'
);

echo "Task description checks passed.\n";
