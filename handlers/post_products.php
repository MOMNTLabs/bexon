<?php
declare(strict_types=1);

function productsRedirectPath(int $productId = 0): string
{
    return dashboardPath('products', $productId > 0 ? ['product' => (string) $productId] : []);
}

function productRequireWorkspaceAccess(): array
{
    $user = requireAuth();
    $workspaceId = activeWorkspaceId($user);
    if ($workspaceId === null || workspaceRoleForUser((int) $user['id'], $workspaceId) === null) {
        throw new RuntimeException('Você não possui acesso a este workspace.');
    }
    return [$user, $workspaceId];
}

function productFormPayload(): array
{
    $pricingMode = normalizeProductPricingMode((string) ($_POST['pricing_mode'] ?? 'margin'));
    $marginPercent = normalizeProductDecimal($_POST['margin_percent'] ?? 30, 30, 0, 95);
    $salesFeePercent = normalizeProductDecimal($_POST['sales_fee_percent'] ?? 0, 0, 0, 95);
    if ($pricingMode === 'margin' && ($marginPercent + $salesFeePercent) >= 99.9) {
        throw new RuntimeException('A soma da margem com as taxas precisa ser menor que 100%.');
    }

    return [
        'name' => normalizeProductName((string) ($_POST['name'] ?? '')),
        'description' => normalizeProductText((string) ($_POST['description'] ?? '')),
        'status' => normalizeProductStatus((string) ($_POST['status'] ?? 'idea')),
        'image_data_url' => productImageDataUrl($_POST['image_data_url'] ?? ''),
        'pricing_mode' => $pricingMode,
        'margin_percent' => $marginPercent,
        'final_price_cents' => productMoneyCents($_POST['final_price'] ?? null),
        'batch_size' => max(1, min(1000000, (int) ($_POST['batch_size'] ?? 1))),
        'labor_cost_cents' => productMoneyCents($_POST['labor_cost'] ?? null),
        'packaging_cost_cents' => productMoneyCents($_POST['packaging_cost'] ?? null),
        'other_unit_cost_cents' => productMoneyCents($_POST['other_unit_cost'] ?? null),
        'fixed_batch_cost_cents' => productMoneyCents($_POST['fixed_batch_cost'] ?? null),
        'sales_fee_percent' => $salesFeePercent,
    ];
}

function productMaterialFormPayload(): array
{
    return [
        'name' => normalizeProductName((string) ($_POST['material_name'] ?? '')),
        'supplier' => normalizeProductText((string) ($_POST['supplier'] ?? ''), 120),
        'image_data_url' => productImageDataUrl($_POST['material_image_data_url'] ?? ''),
        'unit_label' => normalizeInventoryUnitLabel((string) ($_POST['unit_label'] ?? 'un')),
        'quantity_per_unit' => normalizeProductDecimal($_POST['quantity_per_unit'] ?? 1, 1, 0.0001, 100000000),
        'package_quantity' => normalizeProductDecimal($_POST['package_quantity'] ?? 1, 1, 0.0001, 100000000),
        'package_cost_cents' => productMoneyCents($_POST['package_cost'] ?? null),
        'package_shipping_cents' => productMoneyCents($_POST['package_shipping'] ?? null),
        'loss_percent' => normalizeProductDecimal($_POST['loss_percent'] ?? 0, 0, 0, 95),
        'stock_quantity' => normalizeProductDecimal($_POST['stock_quantity'] ?? 0, 0, 0, 100000000),
    ];
}

function handleProductsPostAction(PDO $pdo, string $action): bool
{
    if (!in_array($action, [
        'create_workspace_product',
        'update_workspace_product',
        'delete_workspace_product',
        'create_workspace_product_material',
        'update_workspace_product_material',
        'delete_workspace_product_material',
    ], true)) {
        return false;
    }

    [$user, $workspaceId] = productRequireWorkspaceAccess();
    ensureWorkspaceProductsSchema($pdo);

    if ($action === 'create_workspace_product') {
        $name = normalizeProductName((string) ($_POST['name'] ?? ''));
        $now = nowIso();
        $params = [
            ':workspace_id' => $workspaceId,
            ':name' => $name,
            ':created_by' => (int) $user['id'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
        $sql = 'INSERT INTO workspace_products (workspace_id, name, created_by, created_at, updated_at)
                VALUES (:workspace_id, :name, :created_by, :created_at, :updated_at)';
        if (dbDriverName($pdo) === 'pgsql') {
            $stmt = $pdo->prepare($sql . ' RETURNING id');
            $stmt->execute($params);
            $productId = (int) $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $productId = (int) $pdo->lastInsertId();
        }
        flash('success', 'Produto criado. Agora detalhe os custos.');
        redirectTo(productsRedirectPath($productId));
    }

    $productId = max(0, (int) ($_POST['product_id'] ?? 0));
    $product = workspaceProductById($workspaceId, $productId);
    if ($product === null) {
        throw new RuntimeException('Produto não encontrado neste workspace.');
    }

    if ($action === 'update_workspace_product') {
        $payload = productFormPayload();
        $payload[':id'] = $productId;
        $payload[':workspace_id'] = $workspaceId;
        $payload[':expected_revision'] = max(1, (int) ($_POST['expected_revision'] ?? 1));
        $payload[':updated_at'] = nowIso();
        $params = [];
        foreach ($payload as $key => $value) {
            $params[str_starts_with((string) $key, ':') ? (string) $key : ':' . $key] = $value;
        }
        $stmt = $pdo->prepare(
            'UPDATE workspace_products
             SET name = :name, description = :description, status = :status,
                 image_data_url = :image_data_url, pricing_mode = :pricing_mode,
                 margin_percent = :margin_percent, final_price_cents = :final_price_cents,
                 batch_size = :batch_size, labor_cost_cents = :labor_cost_cents,
                 packaging_cost_cents = :packaging_cost_cents,
                 other_unit_cost_cents = :other_unit_cost_cents,
                 fixed_batch_cost_cents = :fixed_batch_cost_cents,
                 sales_fee_percent = :sales_fee_percent,
                 revision = revision + 1, updated_at = :updated_at
             WHERE id = :id AND workspace_id = :workspace_id AND revision = :expected_revision'
        );
        $stmt->execute($params);
        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('Este produto foi alterado por outra pessoa. Recarregue a página antes de salvar.');
        }
        flash('success', 'Produto e precificação atualizados.');
        redirectTo(productsRedirectPath($productId));
    }

    if ($action === 'delete_workspace_product') {
        $stmt = $pdo->prepare('DELETE FROM workspace_products WHERE id = :id AND workspace_id = :workspace_id');
        $stmt->execute([':id' => $productId, ':workspace_id' => $workspaceId]);
        flash('success', 'Produto removido.');
        redirectTo(productsRedirectPath());
    }

    if ($action === 'create_workspace_product_material') {
        $material = productMaterialFormPayload();
        $now = nowIso();
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_product_materials (
                product_id, name, supplier, image_data_url, unit_label,
                quantity_per_unit, package_quantity, package_cost_cents,
                package_shipping_cents, loss_percent, stock_quantity, created_at, updated_at
             ) VALUES (
                :product_id, :name, :supplier, :image_data_url, :unit_label,
                :quantity_per_unit, :package_quantity, :package_cost_cents,
                :package_shipping_cents, :loss_percent, :stock_quantity, :created_at, :updated_at
             )'
        );
        $stmt->execute(array_merge([':product_id' => $productId], array_combine(
            array_map(static fn (string $key): string => ':' . $key, array_keys($material)),
            array_values($material)
        ), [':created_at' => $now, ':updated_at' => $now]));
        flash('success', 'Material adicionado ao produto.');
        redirectTo(productsRedirectPath($productId));
    }

    $materialId = max(0, (int) ($_POST['material_id'] ?? 0));
    $materialCheck = $pdo->prepare(
        'SELECT m.id FROM workspace_product_materials m
         INNER JOIN workspace_products p ON p.id = m.product_id
         WHERE m.id = :id AND m.product_id = :product_id AND p.workspace_id = :workspace_id LIMIT 1'
    );
    $materialCheck->execute([':id' => $materialId, ':product_id' => $productId, ':workspace_id' => $workspaceId]);
    if (!$materialCheck->fetchColumn()) {
        throw new RuntimeException('Material não encontrado.');
    }

    if ($action === 'update_workspace_product_material') {
        $material = productMaterialFormPayload();
        $params = array_combine(
            array_map(static fn (string $key): string => ':' . $key, array_keys($material)),
            array_values($material)
        );
        $params[':id'] = $materialId;
        $params[':product_id'] = $productId;
        $params[':updated_at'] = nowIso();
        $stmt = $pdo->prepare(
            'UPDATE workspace_product_materials
             SET name = :name, supplier = :supplier, image_data_url = :image_data_url,
                 unit_label = :unit_label, quantity_per_unit = :quantity_per_unit,
                 package_quantity = :package_quantity, package_cost_cents = :package_cost_cents,
                 package_shipping_cents = :package_shipping_cents, loss_percent = :loss_percent,
                 stock_quantity = :stock_quantity, updated_at = :updated_at
             WHERE id = :id AND product_id = :product_id'
        );
        $stmt->execute($params);
        flash('success', 'Material atualizado.');
        redirectTo(productsRedirectPath($productId));
    }

    $stmt = $pdo->prepare('DELETE FROM workspace_product_materials WHERE id = :id AND product_id = :product_id');
    $stmt->execute([':id' => $materialId, ':product_id' => $productId]);
    flash('success', 'Material removido.');
    redirectTo(productsRedirectPath($productId));
}
