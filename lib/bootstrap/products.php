<?php
declare(strict_types=1);

function ensureWorkspaceProductsSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_products (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                status VARCHAR(20) NOT NULL DEFAULT \'idea\',
                image_data_url TEXT NOT NULL DEFAULT \'\',
                pricing_mode VARCHAR(20) NOT NULL DEFAULT \'margin\',
                margin_percent NUMERIC(8,3) NOT NULL DEFAULT 30,
                final_price_cents BIGINT NOT NULL DEFAULT 0,
                batch_size INTEGER NOT NULL DEFAULT 1,
                labor_cost_cents BIGINT NOT NULL DEFAULT 0,
                packaging_cost_cents BIGINT NOT NULL DEFAULT 0,
                other_unit_cost_cents BIGINT NOT NULL DEFAULT 0,
                fixed_batch_cost_cents BIGINT NOT NULL DEFAULT 0,
                sales_fee_percent NUMERIC(8,3) NOT NULL DEFAULT 0,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                revision INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_product_materials (
                id BIGSERIAL PRIMARY KEY,
                product_id BIGINT NOT NULL REFERENCES workspace_products(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                supplier TEXT NOT NULL DEFAULT \'\',
                image_data_url TEXT NOT NULL DEFAULT \'\',
                unit_label VARCHAR(30) NOT NULL DEFAULT \'un\',
                quantity_per_unit NUMERIC(14,4) NOT NULL DEFAULT 1,
                package_quantity NUMERIC(14,4) NOT NULL DEFAULT 1,
                package_cost_cents BIGINT NOT NULL DEFAULT 0,
                package_shipping_cents BIGINT NOT NULL DEFAULT 0,
                loss_percent NUMERIC(8,3) NOT NULL DEFAULT 0,
                stock_quantity NUMERIC(14,4) NOT NULL DEFAULT 0,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                status TEXT NOT NULL DEFAULT \'idea\',
                image_data_url TEXT NOT NULL DEFAULT \'\',
                pricing_mode TEXT NOT NULL DEFAULT \'margin\',
                margin_percent REAL NOT NULL DEFAULT 30,
                final_price_cents INTEGER NOT NULL DEFAULT 0,
                batch_size INTEGER NOT NULL DEFAULT 1,
                labor_cost_cents INTEGER NOT NULL DEFAULT 0,
                packaging_cost_cents INTEGER NOT NULL DEFAULT 0,
                other_unit_cost_cents INTEGER NOT NULL DEFAULT 0,
                fixed_batch_cost_cents INTEGER NOT NULL DEFAULT 0,
                sales_fee_percent REAL NOT NULL DEFAULT 0,
                created_by INTEGER DEFAULT NULL,
                revision INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_product_materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                supplier TEXT NOT NULL DEFAULT \'\',
                image_data_url TEXT NOT NULL DEFAULT \'\',
                unit_label TEXT NOT NULL DEFAULT \'un\',
                quantity_per_unit REAL NOT NULL DEFAULT 1,
                package_quantity REAL NOT NULL DEFAULT 1,
                package_cost_cents INTEGER NOT NULL DEFAULT 0,
                package_shipping_cents INTEGER NOT NULL DEFAULT 0,
                loss_percent REAL NOT NULL DEFAULT 0,
                stock_quantity REAL NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (product_id) REFERENCES workspace_products(id) ON DELETE CASCADE
            )'
        );
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_workspace_products_workspace_updated ON workspace_products(workspace_id, updated_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_workspace_product_materials_product ON workspace_product_materials(product_id, id)');
}

function normalizeProductName(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        throw new RuntimeException('Informe o nome do produto.');
    }
    return mb_substr($value, 0, 120);
}

function normalizeProductText(string $value, int $maxLength = 1200): string
{
    return mb_substr(trim($value), 0, $maxLength);
}

function normalizeProductStatus(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['idea', 'testing', 'active', 'paused'], true) ? $value : 'idea';
}

function productStatusLabel(string $status): string
{
    return [
        'idea' => 'Ideia',
        'testing' => 'Em teste',
        'active' => 'Em operação',
        'paused' => 'Pausado',
    ][normalizeProductStatus($status)] ?? 'Ideia';
}

function normalizeProductPricingMode(string $value): string
{
    return strtolower(trim($value)) === 'price' ? 'price' : 'margin';
}

function normalizeProductDecimal($value, float $default = 0, float $min = 0, float $max = 999999999): float
{
    if ($value === null || trim((string) $value) === '') {
        return $default;
    }
    $raw = str_replace([' ', '.'], ['', ''], trim((string) $value));
    if (str_contains((string) $value, '.') && !str_contains((string) $value, ',')) {
        $raw = trim((string) $value);
    } else {
        $raw = str_replace(',', '.', $raw);
    }
    if (!is_numeric($raw)) {
        throw new RuntimeException('Revise os valores numéricos informados.');
    }
    return min($max, max($min, (float) $raw));
}

function productDecimalInput($value, int $precision = 2): string
{
    $formatted = number_format((float) $value, $precision, ',', '');
    return rtrim(rtrim($formatted, '0'), ',') ?: '0';
}

function productMoneyCents($value): int
{
    return normalizeDueAmountCents($value) ?? 0;
}

function productMoneyInput($cents): string
{
    return number_format(max(0, (int) $cents) / 100, 2, ',', '.');
}

function productImageDataUrl($value): string
{
    return normalizeTaskGroupImageDataUrl($value);
}

function workspaceProductsList(int $workspaceId): array
{
    ensureWorkspaceProductsSchema(db());
    $stmt = db()->prepare(
        'SELECT p.*, COUNT(m.id) AS material_count
         FROM workspace_products p
         LEFT JOIN workspace_product_materials m ON m.product_id = p.id
         WHERE p.workspace_id = :workspace_id
         GROUP BY p.id
         ORDER BY p.updated_at DESC, p.id DESC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return $stmt->fetchAll() ?: [];
}

function workspaceProductById(int $workspaceId, int $productId): ?array
{
    ensureWorkspaceProductsSchema(db());
    $stmt = db()->prepare('SELECT * FROM workspace_products WHERE id = :id AND workspace_id = :workspace_id LIMIT 1');
    $stmt->execute([':id' => $productId, ':workspace_id' => $workspaceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function workspaceProductMaterials(int $workspaceId, int $productId): array
{
    $stmt = db()->prepare(
        'SELECT m.*
         FROM workspace_product_materials m
         INNER JOIN workspace_products p ON p.id = m.product_id
         WHERE m.product_id = :product_id AND p.workspace_id = :workspace_id
         ORDER BY m.id ASC'
    );
    $stmt->execute([':product_id' => $productId, ':workspace_id' => $workspaceId]);
    return $stmt->fetchAll() ?: [];
}

function productCostSummary(array $product, array $materials): array
{
    $batchSize = max(1, (int) ($product['batch_size'] ?? 1));
    $materialUnitCents = 0.0;
    $purchaseNowCents = 0;
    $purchaseRows = [];

    foreach ($materials as $material) {
        $quantityPerUnit = max(0, (float) ($material['quantity_per_unit'] ?? 0));
        $packageQuantity = max(0.0001, (float) ($material['package_quantity'] ?? 1));
        $lossRate = min(0.95, max(0, (float) ($material['loss_percent'] ?? 0) / 100));
        $packageCents = max(0, (int) ($material['package_cost_cents'] ?? 0)) + max(0, (int) ($material['package_shipping_cents'] ?? 0));
        $usablePackageQuantity = max(0.0001, $packageQuantity * (1 - $lossRate));
        $unitMaterialCents = ($packageCents / $usablePackageQuantity) * $quantityPerUnit;
        $materialUnitCents += $unitMaterialCents;

        $grossRequired = ($quantityPerUnit * $batchSize) / max(0.05, 1 - $lossRate);
        $stock = max(0, (float) ($material['stock_quantity'] ?? 0));
        $shortage = max(0, $grossRequired - $stock);
        $packages = $shortage > 0 ? (int) ceil($shortage / $packageQuantity) : 0;
        $costCents = $packages * $packageCents;
        $purchaseNowCents += $costCents;
        $purchaseRows[] = array_merge($material, [
            'gross_required' => $grossRequired,
            'shortage' => $shortage,
            'packages_to_buy' => $packages,
            'purchase_cost_cents' => $costCents,
            'unit_cost_cents' => $unitMaterialCents,
        ]);
    }

    $labor = max(0, (int) ($product['labor_cost_cents'] ?? 0));
    $packaging = max(0, (int) ($product['packaging_cost_cents'] ?? 0));
    $other = max(0, (int) ($product['other_unit_cost_cents'] ?? 0));
    $fixedBatch = max(0, (int) ($product['fixed_batch_cost_cents'] ?? 0));
    $baseUnitCents = $materialUnitCents + $labor + $packaging + $other + ($fixedBatch / $batchSize);
    $feeRate = min(0.95, max(0, (float) ($product['sales_fee_percent'] ?? 0) / 100));
    $targetMarginRate = min(0.95, max(0, (float) ($product['margin_percent'] ?? 0) / 100));
    $pricingMode = normalizeProductPricingMode((string) ($product['pricing_mode'] ?? 'margin'));
    $denominator = 1 - $feeRate - $targetMarginRate;
    $sellingPriceCents = $pricingMode === 'margin' && $denominator > 0.001
        ? $baseUnitCents / $denominator
        : max(0, (int) ($product['final_price_cents'] ?? 0));
    $feeUnitCents = $sellingPriceCents * $feeRate;
    $profitUnitCents = $sellingPriceCents - $baseUnitCents - $feeUnitCents;
    $effectiveMargin = $sellingPriceCents > 0 ? ($profitUnitCents / $sellingPriceCents) * 100 : 0;
    $purchaseNowCents += ($labor + $packaging + $other) * $batchSize + $fixedBatch;
    $revenueBatchCents = $sellingPriceCents * $batchSize;
    $profitBatchCents = $profitUnitCents * $batchSize;

    return [
        'batch_size' => $batchSize,
        'material_unit_cents' => (int) round($materialUnitCents),
        'base_unit_cents' => (int) round($baseUnitCents),
        'selling_price_cents' => (int) round($sellingPriceCents),
        'fee_unit_cents' => (int) round($feeUnitCents),
        'profit_unit_cents' => (int) round($profitUnitCents),
        'effective_margin_percent' => $effectiveMargin,
        'purchase_now_cents' => (int) round($purchaseNowCents),
        'revenue_batch_cents' => (int) round($revenueBatchCents),
        'profit_batch_cents' => (int) round($profitBatchCents),
        'purchase_rows' => $purchaseRows,
        'is_viable' => $sellingPriceCents > 0 && $profitUnitCents > 0,
        'pricing_invalid' => $pricingMode === 'margin' && $denominator <= 0.001,
    ];
}
