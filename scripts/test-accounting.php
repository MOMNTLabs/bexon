<?php
declare(strict_types=1);

/**
 * Checks the calculation rules that must remain stable as accounting evolves.
 *
 * Run with:
 *   php scripts/test-accounting.php
 */

// O bootstrap inicia a sessão. Nos testes de terminal, usamos a pasta
// temporária do sistema em vez da instalação local do Laragon.
session_save_path(sys_get_temp_dir());

require_once dirname(__DIR__) . '/bootstrap.php';

function accountingTestAssertSame(mixed $expected, mixed $actual, string $message): void
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

function accountingTestEntry(array $overrides): array
{
    return array_replace([
        'entry_type' => 'expense',
        'amount_cents' => 0,
        'is_settled' => 0,
        'is_monthly_goal' => 0,
        'paid_amount_cents' => 0,
        'discount_total_cents' => 0,
        'due_date' => null,
        'label' => 'Item de teste',
    ], $overrides);
}

// O saldo atual reflete apenas o dinheiro que já entrou ou saiu.
$summary = accountingSummary([
    accountingTestEntry([
        'entry_type' => 'income',
        'amount_cents' => 60000,
        'discount_total_cents' => 30000,
        'label' => 'Entrada parcialmente recebida',
    ]),
    accountingTestEntry([
        'entry_type' => 'expense',
        'amount_cents' => 50000,
        'discount_total_cents' => 20000,
        'label' => 'Conta parcialmente abatida',
    ]),
], 10000);
accountingTestAssertSame(20000, $summary['current_balance_cents'], 'Saldo atual deve usar apenas recebimentos e abatimentos realizados.');
accountingTestAssertSame(20000, $summary['final_balance_cents'], 'Saldo projetado deve considerar os valores integrais previstos.');

// Um recebimento agendado para o futuro ainda não torna a entrada recebida.
$futureReceiptSummary = accountingSummary([
    accountingTestEntry([
        'entry_type' => 'income',
        'amount_cents' => 60000,
        'discount_total_cents' => 0,
        'discount_scheduled_total_cents' => 60000,
        'label' => 'Entrada agendada',
    ]),
], 0);
accountingTestAssertSame(0, $futureReceiptSummary['income_received_cents'], 'Recebimento futuro não pode compor o saldo atual.');
accountingTestAssertSame(60000, $futureReceiptSummary['income_total_cents'], 'Recebimento futuro deve compor o saldo projetado.');

// Ao quitar o item, o total integral deve ser contabilizado uma única vez.
$settledSummary = accountingSummary([
    accountingTestEntry([
        'entry_type' => 'income',
        'amount_cents' => 60000,
        'discount_total_cents' => 30000,
        'is_settled' => 1,
        'label' => 'Entrada quitada',
    ]),
    accountingTestEntry([
        'entry_type' => 'expense',
        'amount_cents' => 50000,
        'discount_total_cents' => 20000,
        'is_settled' => 1,
        'label' => 'Conta quitada',
    ]),
], 0);
accountingTestAssertSame(10000, $settledSummary['current_balance_cents'], 'Itens quitados devem usar o valor integral, sem somar o parcial novamente.');

// A projeção semanal deve terminar no mesmo saldo projetado do período.
$weeklyProjection = accountingWeeklyBalanceProjection([
    accountingTestEntry([
        'entry_type' => 'income',
        'amount_cents' => 60000,
        'due_date' => '2026-07-10',
        'label' => 'Entrada semanal',
    ]),
    accountingTestEntry([
        'entry_type' => 'expense',
        'amount_cents' => 50000,
        'due_date' => '2026-07-17',
        'label' => 'Conta semanal',
    ]),
], 10000, [
    'period_key' => '2026-07',
    'cycle_close_day' => 1,
    'expected_final_balance_cents' => 20000,
]);
accountingTestAssertSame(20000, $weeklyProjection['final_balance_cents'], 'O saldo final semanal deve coincidir com o saldo projetado do período.');

// Uma ramificação antiga da mesma pendência não pode duplicar o item nem os totais.
$collapsedCarryEntries = workspaceAccountingCollapseDuplicateCarryEntriesWithProfiles([
    accountingTestEntry([
        'id' => 20,
        'carry_source_entry_id' => 10,
        'is_carried' => 1,
        'amount_cents' => 17000,
        'due_date' => '2026-06-25',
        'label' => 'Pendência duplicada',
    ]),
    accountingTestEntry([
        'id' => 21,
        'carry_source_entry_id' => 11,
        'is_carried' => 1,
        'amount_cents' => 17000,
        'due_date' => '2026-06-25',
        'label' => 'Pendência duplicada',
    ]),
], [
    20 => ['key' => 'entry:10', 'root_id' => 10, 'depth' => 1],
    21 => ['key' => 'entry:10', 'root_id' => 10, 'depth' => 2],
]);
accountingTestAssertSame(1, count($collapsedCarryEntries), 'A mesma pendência deve aparecer uma única vez no período.');
accountingTestAssertSame(21, (int) ($collapsedCarryEntries[0]['id'] ?? 0), 'A cadeia mais completa deve prevalecer sobre a ramificação antiga.');

$distinctMonthlyCarries = workspaceAccountingCollapseDuplicateCarryEntriesWithProfiles([
    $collapsedCarryEntries[0],
    accountingTestEntry([
        'id' => 22,
        'carry_source_entry_id' => 12,
        'is_carried' => 1,
        'amount_cents' => 17000,
        'label' => 'Pendência de outra competência',
    ]),
], [
    21 => ['key' => 'entry:10', 'root_id' => 10, 'depth' => 2],
    22 => ['key' => 'entry:12', 'root_id' => 12, 'depth' => 1],
]);
accountingTestAssertSame(2, count($distinctMonthlyCarries), 'Pendências de competências diferentes devem continuar separadas.');

echo "Accounting checks passed.\n";
