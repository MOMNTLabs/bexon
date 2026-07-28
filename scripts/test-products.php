<?php
declare(strict_types=1);

/**
 * Checks pricing, loss and purchasing rules for the product tool.
 *
 * Run with:
 *   php scripts/test-products.php
 */

session_save_path(sys_get_temp_dir());
require_once dirname(__DIR__) . '/bootstrap.php';

function productTestAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }
    fwrite(STDERR, sprintf("FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export($expected, true), var_export($actual, true)));
    exit(1);
}

$summary = productCostSummary([
    'batch_size' => 10,
    'labor_cost_cents' => 500,
    'packaging_cost_cents' => 200,
    'other_unit_cost_cents' => 0,
    'fixed_batch_cost_cents' => 1000,
    'sales_fee_percent' => 5,
    'margin_percent' => 30,
    'pricing_mode' => 'margin',
    'final_price_cents' => 0,
], [[
    'id' => 1,
    'name' => 'Material',
    'quantity_per_unit' => 2,
    'package_quantity' => 20,
    'package_cost_cents' => 10000,
    'package_shipping_cents' => 1000,
    'loss_percent' => 10,
    'stock_quantity' => 0,
    'unit_label' => 'un',
]]);

productTestAssertSame(2022, $summary['base_unit_cents'], 'O custo unitário deve incluir material, perda, operação e rateio fixo.');
productTestAssertSame(3111, $summary['selling_price_cents'], 'O preço deve preservar margem e taxa sobre a venda.');
productTestAssertSame(2, $summary['purchase_rows'][0]['packages_to_buy'], 'A compra deve respeitar pacotes inteiros e perda de material.');
productTestAssertSame(true, $summary['is_viable'], 'O cenário com lucro positivo deve ser viável.');

$priceSummary = productCostSummary([
    'batch_size' => 5,
    'labor_cost_cents' => 0,
    'packaging_cost_cents' => 0,
    'other_unit_cost_cents' => 0,
    'fixed_batch_cost_cents' => 0,
    'sales_fee_percent' => 10,
    'margin_percent' => 0,
    'pricing_mode' => 'price',
    'final_price_cents' => 10000,
], []);

productTestAssertSame(10000, $priceSummary['selling_price_cents'], 'No modo preço final, o valor informado deve ser preservado.');
productTestAssertSame(9000, $priceSummary['profit_unit_cents'], 'A taxa de venda deve ser abatida do lucro informado.');

echo "Product pricing checks passed.\n";
