<?php
$productCount = count(is_array($products ?? null) ? $products : []);
$selectedProductId = (int) ($selectedProduct['id'] ?? 0);
$productSummary = is_array($selectedProductSummary ?? null) ? $selectedProductSummary : [];
$productImage = productImageDataUrl($selectedProduct['image_data_url'] ?? '');
$productInitial = $selectedProduct !== null ? mb_strtoupper(mb_substr((string) ($selectedProduct['name'] ?? 'P'), 0, 1)) : 'P';
?>
<section class="products-wrap panel" id="products" data-dashboard-view-panel="products"<?= $serverSelectedDashboardView !== 'products' ? ' hidden' : '' ?>>
    <div class="panel-header board-header products-header">
        <div>
            <h2>Produtos</h2>
            <p>Planeje custos, preço e compras sem perder a visão da operação.</p>
        </div>
        <div class="board-summary products-header-actions">
            <span><?= e(appItemCountLabel($productCount)) ?></span>
            <button type="button" class="btn btn-pill" data-open-product-create>+ Novo produto</button>
        </div>
    </div>

    <div class="products-workspace<?= $selectedProduct === null ? ' is-empty' : '' ?>">
        <aside class="products-catalog" aria-label="Produtos do workspace">
            <?php if (!$products): ?>
                <div class="products-catalog-empty">
                    <span aria-hidden="true">◇</span>
                    <strong>Transforme uma ideia em números</strong>
                    <p>Cadastre o primeiro produto para descobrir custo, preço e lucro.</p>
                    <button type="button" class="btn btn-mini" data-open-product-create>Criar produto</button>
                </div>
            <?php else: ?>
                <?php foreach ($products as $productOption): ?>
                    <?php
                    $productOptionId = (int) ($productOption['id'] ?? 0);
                    $productOptionImage = productImageDataUrl($productOption['image_data_url'] ?? '');
                    $productOptionInitial = mb_strtoupper(mb_substr((string) ($productOption['name'] ?? 'P'), 0, 1));
                    ?>
                    <a
                        href="<?= e(dashboardPath('products', ['product' => (string) $productOptionId])) ?>"
                        class="product-catalog-card<?= $productOptionId === $selectedProductId ? ' is-active' : '' ?>"
                    >
                        <span class="product-thumb">
                            <?php if ($productOptionImage !== ''): ?>
                                <img src="<?= e($productOptionImage) ?>" alt="">
                            <?php else: ?>
                                <span><?= e($productOptionInitial) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="product-catalog-copy">
                            <strong><?= e((string) ($productOption['name'] ?? 'Produto')) ?></strong>
                            <small><?= e(productStatusLabel((string) ($productOption['status'] ?? 'idea'))) ?> · <?= e((string) ((int) ($productOption['material_count'] ?? 0))) ?> materiais</small>
                        </span>
                        <span aria-hidden="true">›</span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <?php if ($selectedProduct !== null): ?>
            <div class="product-detail" data-product-calculator data-material-unit-cents="<?= e((string) ((int) ($productSummary['material_unit_cents'] ?? 0))) ?>">
                <form method="post" class="product-main-form" data-product-main-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_workspace_product">
                    <input type="hidden" name="product_id" value="<?= e((string) $selectedProductId) ?>">
                    <input type="hidden" name="expected_revision" value="<?= e((string) ((int) ($selectedProduct['revision'] ?? 1))) ?>">
                    <input type="hidden" name="image_data_url" value="<?= e($productImage) ?>" data-product-image-value>

                    <section class="product-identity-card">
                        <label class="product-image-picker" title="Alterar imagem do produto">
                            <span class="product-image-preview" data-product-image-preview>
                                <?php if ($productImage !== ''): ?>
                                    <img src="<?= e($productImage) ?>" alt="Imagem de <?= e((string) ($selectedProduct['name'] ?? 'produto')) ?>">
                                <?php else: ?>
                                    <span><?= e($productInitial) ?></span>
                                <?php endif; ?>
                            </span>
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-product-image-input hidden>
                            <small>Trocar imagem</small>
                        </label>
                        <div class="product-identity-fields">
                            <div class="product-field-row">
                                <label class="product-field is-grow">
                                    <span>Produto</span>
                                    <input type="text" name="name" maxlength="120" value="<?= e((string) ($selectedProduct['name'] ?? '')) ?>" required>
                                </label>
                                <label class="product-field">
                                    <span>Etapa</span>
                                    <select name="status">
                                        <?php foreach (['idea' => 'Ideia', 'testing' => 'Em teste', 'active' => 'Em operação', 'paused' => 'Pausado'] as $statusKey => $statusLabel): ?>
                                            <option value="<?= e($statusKey) ?>" <?= normalizeProductStatus((string) ($selectedProduct['status'] ?? '')) === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <label class="product-field">
                                <span>Contexto <small>opcional</small></span>
                                <textarea name="description" rows="2" maxlength="1200" placeholder="Ex.: pulseira artesanal, público e objetivo do produto."><?= e((string) ($selectedProduct['description'] ?? '')) ?></textarea>
                            </label>
                        </div>
                    </section>

                    <section class="product-pricing-grid">
                        <div class="product-form-card">
                            <div class="product-section-title">
                                <div><span>01</span><h3>Custos da operação</h3></div>
                                <small>por unidade, exceto o custo fixo</small>
                            </div>
                            <div class="product-cost-fields">
                                <label class="product-field"><span>Mão de obra</span><input type="text" inputmode="decimal" name="labor_cost" value="<?= e(productMoneyInput($selectedProduct['labor_cost_cents'] ?? 0)) ?>" data-money-cents-source></label>
                                <label class="product-field"><span>Embalagem</span><input type="text" inputmode="decimal" name="packaging_cost" value="<?= e(productMoneyInput($selectedProduct['packaging_cost_cents'] ?? 0)) ?>" data-money-cents-source></label>
                                <label class="product-field"><span>Outros</span><input type="text" inputmode="decimal" name="other_unit_cost" value="<?= e(productMoneyInput($selectedProduct['other_unit_cost_cents'] ?? 0)) ?>" data-money-cents-source></label>
                                <label class="product-field"><span>Fixo do lote</span><input type="text" inputmode="decimal" name="fixed_batch_cost" value="<?= e(productMoneyInput($selectedProduct['fixed_batch_cost_cents'] ?? 0)) ?>" data-money-cents-source></label>
                                <label class="product-field"><span>Lote planejado</span><input type="number" min="1" max="1000000" name="batch_size" value="<?= e((string) max(1, (int) ($selectedProduct['batch_size'] ?? 1))) ?>"></label>
                            </div>
                        </div>

                        <div class="product-form-card">
                            <div class="product-section-title">
                                <div><span>02</span><h3>Estratégia de preço</h3></div>
                                <small>escolha o que deseja controlar</small>
                            </div>
                            <label class="product-field">
                                <span>Calcular a partir de</span>
                                <select name="pricing_mode" data-product-pricing-mode>
                                    <option value="margin" <?= normalizeProductPricingMode((string) ($selectedProduct['pricing_mode'] ?? '')) === 'margin' ? 'selected' : '' ?>>Margem desejada</option>
                                    <option value="price" <?= normalizeProductPricingMode((string) ($selectedProduct['pricing_mode'] ?? '')) === 'price' ? 'selected' : '' ?>>Preço final</option>
                                </select>
                            </label>
                            <div class="product-cost-fields is-pricing">
                                <label class="product-field" data-margin-field><span>Margem desejada</span><span class="product-input-suffix"><input type="text" inputmode="decimal" name="margin_percent" value="<?= e(productDecimalInput($selectedProduct['margin_percent'] ?? 30, 2)) ?>"><b>%</b></span></label>
                                <label class="product-field" data-price-field><span>Preço final</span><input type="text" inputmode="decimal" name="final_price" value="<?= e(productMoneyInput($selectedProduct['final_price_cents'] ?? 0)) ?>"></label>
                                <label class="product-field"><span>Taxas da venda</span><span class="product-input-suffix"><input type="text" inputmode="decimal" name="sales_fee_percent" value="<?= e(productDecimalInput($selectedProduct['sales_fee_percent'] ?? 0, 2)) ?>"><b>%</b></span></label>
                            </div>
                        </div>
                    </section>

                    <div class="product-save-row">
                        <span>Os cálculos mudam enquanto você simula. Salve para compartilhar com o workspace.</span>
                        <button type="submit" class="btn btn-pill">Salvar produto</button>
                    </div>
                </form>

                <section class="product-results" aria-label="Resultado da precificação">
                    <article class="product-result-card is-price"><span>Preço sugerido</span><strong data-result-price><?= e(dueAmountLabelFromCents($productSummary['selling_price_cents'] ?? 0)) ?></strong><small data-result-viability><?= !empty($productSummary['is_viable']) ? 'Operação positiva' : 'Revise os custos' ?></small></article>
                    <article class="product-result-card"><span>Custo unitário</span><strong data-result-cost><?= e(dueAmountLabelFromCents($productSummary['base_unit_cents'] ?? 0)) ?></strong><small>Materiais + operação</small></article>
                    <article class="product-result-card"><span>Lucro por unidade</span><strong data-result-profit class="<?= ((int) ($productSummary['profit_unit_cents'] ?? 0)) < 0 ? 'is-negative' : '' ?>"><?= e(dueAmountLabelFromSignedCents($productSummary['profit_unit_cents'] ?? 0)) ?></strong><small data-result-margin><?= e(number_format((float) ($productSummary['effective_margin_percent'] ?? 0), 1, ',', '.')) ?>% de margem real</small></article>
                    <article class="product-result-card"><span>Resultado do lote</span><strong data-result-batch-profit class="<?= ((int) ($productSummary['profit_batch_cents'] ?? 0)) < 0 ? 'is-negative' : '' ?>"><?= e(dueAmountLabelFromSignedCents($productSummary['profit_batch_cents'] ?? 0)) ?></strong><small data-result-batch-revenue><?= e(dueAmountLabelFromCents($productSummary['revenue_batch_cents'] ?? 0)) ?> em vendas</small></article>
                </section>

                <section class="product-materials-section">
                    <div class="product-section-title is-wide">
                        <div><span>03</span><h3>Materiais e insumos</h3></div>
                        <button type="button" class="btn btn-mini" data-toggle-product-material>+ Material</button>
                    </div>

                    <form method="post" class="product-material-form" data-product-material-create hidden>
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="create_workspace_product_material">
                        <input type="hidden" name="product_id" value="<?= e((string) $selectedProductId) ?>">
                        <input type="hidden" name="material_image_data_url" value="" data-product-image-value>
                        <label class="product-material-image-picker">
                            <span data-product-image-preview>+</span>
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-product-image-input hidden>
                            <small>Imagem</small>
                        </label>
                        <div class="product-material-form-fields">
                            <label class="product-field is-grow"><span>Material</span><input type="text" name="material_name" maxlength="120" placeholder="Ex.: fio encerado" required></label>
                            <label class="product-field"><span>Fornecedor</span><input type="text" name="supplier" maxlength="120" placeholder="Opcional"></label>
                            <label class="product-field"><span>Uso por produto</span><input type="text" inputmode="decimal" name="quantity_per_unit" value="1" required></label>
                            <label class="product-field"><span>Unidade</span><input type="text" name="unit_label" value="un" maxlength="30" required></label>
                            <label class="product-field"><span>Qtd. no pacote</span><input type="text" inputmode="decimal" name="package_quantity" value="1" required></label>
                            <label class="product-field"><span>Preço do pacote</span><input type="text" inputmode="decimal" name="package_cost" value="0,00" required></label>
                            <label class="product-field"><span>Frete por pacote</span><input type="text" inputmode="decimal" name="package_shipping" value="0,00"></label>
                            <label class="product-field"><span>Perda estimada</span><span class="product-input-suffix"><input type="text" inputmode="decimal" name="loss_percent" value="0"><b>%</b></span></label>
                            <label class="product-field"><span>Já em estoque</span><input type="text" inputmode="decimal" name="stock_quantity" value="0"></label>
                        </div>
                        <div class="product-material-form-actions"><button type="button" class="btn btn-mini btn-ghost" data-toggle-product-material>Cancelar</button><button type="submit" class="btn btn-mini">Adicionar material</button></div>
                    </form>

                    <div class="product-material-list">
                        <?php if (!$selectedProductMaterials): ?>
                            <div class="products-inline-empty"><strong>Nenhum material ainda.</strong><span>Adicione o que é consumido para fabricar uma unidade.</span></div>
                        <?php endif; ?>
                        <?php foreach ($selectedProductMaterials as $material): ?>
                            <?php
                            $materialId = (int) ($material['id'] ?? 0);
                            $materialImage = productImageDataUrl($material['image_data_url'] ?? '');
                            $materialSummaryRow = null;
                            foreach ((array) ($productSummary['purchase_rows'] ?? []) as $candidateRow) {
                                if ((int) ($candidateRow['id'] ?? 0) === $materialId) { $materialSummaryRow = $candidateRow; break; }
                            }
                            ?>
                            <details class="product-material-card">
                                <summary>
                                    <span class="product-material-thumb"><?php if ($materialImage !== ''): ?><img src="<?= e($materialImage) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) ($material['name'] ?? 'M'), 0, 1))) ?></span><?php endif; ?></span>
                                    <span class="product-material-name"><strong><?= e((string) ($material['name'] ?? 'Material')) ?></strong><small><?= e(productDecimalInput($material['quantity_per_unit'] ?? 0, 4)) ?> <?= e((string) ($material['unit_label'] ?? 'un')) ?> por produto<?= trim((string) ($material['supplier'] ?? '')) !== '' ? ' · ' . e((string) $material['supplier']) : '' ?></small></span>
                                    <span class="product-material-cost"><strong><?= e(dueAmountLabelFromCents((int) round((float) ($materialSummaryRow['unit_cost_cents'] ?? 0)))) ?></strong><small>por produto</small></span>
                                    <span class="product-material-chevron" aria-hidden="true">⌄</span>
                                </summary>
                                <div class="product-material-editor">
                                    <form method="post" class="product-material-form is-edit">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="update_workspace_product_material">
                                        <input type="hidden" name="product_id" value="<?= e((string) $selectedProductId) ?>">
                                        <input type="hidden" name="material_id" value="<?= e((string) $materialId) ?>">
                                        <input type="hidden" name="material_image_data_url" value="<?= e($materialImage) ?>" data-product-image-value>
                                        <label class="product-material-image-picker"><span data-product-image-preview><?php if ($materialImage !== ''): ?><img src="<?= e($materialImage) ?>" alt=""><?php else: ?>+<?php endif; ?></span><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-product-image-input hidden><small>Imagem</small></label>
                                        <div class="product-material-form-fields">
                                            <label class="product-field is-grow"><span>Material</span><input type="text" name="material_name" value="<?= e((string) ($material['name'] ?? '')) ?>" required></label>
                                            <label class="product-field"><span>Fornecedor</span><input type="text" name="supplier" value="<?= e((string) ($material['supplier'] ?? '')) ?>"></label>
                                            <label class="product-field"><span>Uso por produto</span><input type="text" inputmode="decimal" name="quantity_per_unit" value="<?= e(productDecimalInput($material['quantity_per_unit'] ?? 1, 4)) ?>" required></label>
                                            <label class="product-field"><span>Unidade</span><input type="text" name="unit_label" value="<?= e((string) ($material['unit_label'] ?? 'un')) ?>" required></label>
                                            <label class="product-field"><span>Qtd. no pacote</span><input type="text" inputmode="decimal" name="package_quantity" value="<?= e(productDecimalInput($material['package_quantity'] ?? 1, 4)) ?>" required></label>
                                            <label class="product-field"><span>Preço do pacote</span><input type="text" inputmode="decimal" name="package_cost" value="<?= e(productMoneyInput($material['package_cost_cents'] ?? 0)) ?>" required></label>
                                            <label class="product-field"><span>Frete por pacote</span><input type="text" inputmode="decimal" name="package_shipping" value="<?= e(productMoneyInput($material['package_shipping_cents'] ?? 0)) ?>"></label>
                                            <label class="product-field"><span>Perda estimada</span><span class="product-input-suffix"><input type="text" inputmode="decimal" name="loss_percent" value="<?= e(productDecimalInput($material['loss_percent'] ?? 0, 2)) ?>"><b>%</b></span></label>
                                            <label class="product-field"><span>Já em estoque</span><input type="text" inputmode="decimal" name="stock_quantity" value="<?= e(productDecimalInput($material['stock_quantity'] ?? 0, 4)) ?>"></label>
                                        </div>
                                        <div class="product-material-form-actions"><button type="submit" class="btn btn-mini">Salvar material</button></div>
                                    </form>
                                    <form method="post" class="product-material-delete" data-confirm="Remover este material do produto?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_workspace_product_material"><input type="hidden" name="product_id" value="<?= e((string) $selectedProductId) ?>"><input type="hidden" name="material_id" value="<?= e((string) $materialId) ?>"><button type="submit">Remover material</button>
                                    </form>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="product-purchase-section">
                    <div class="product-section-title is-wide"><div><span>04</span><h3>Plano para produzir <?= e((string) ($productSummary['batch_size'] ?? 1)) ?> unidades</h3></div><strong><?= e(dueAmountLabelFromCents($productSummary['purchase_now_cents'] ?? 0)) ?> para começar</strong></div>
                    <div class="product-purchase-list">
                        <?php if (!$selectedProductMaterials): ?><p>Os itens de compra aparecerão quando você adicionar materiais.</p><?php endif; ?>
                        <?php foreach ((array) ($productSummary['purchase_rows'] ?? []) as $purchaseRow): ?>
                            <div class="product-purchase-row<?= ((int) ($purchaseRow['packages_to_buy'] ?? 0)) === 0 ? ' is-ready' : '' ?>">
                                <span><strong><?= e((string) ($purchaseRow['name'] ?? 'Material')) ?></strong><small>Precisa de <?= e(productDecimalInput($purchaseRow['gross_required'] ?? 0, 2)) ?> <?= e((string) ($purchaseRow['unit_label'] ?? 'un')) ?></small></span>
                                <?php if ((int) ($purchaseRow['packages_to_buy'] ?? 0) > 0): ?>
                                    <span><strong><?= e((string) ((int) $purchaseRow['packages_to_buy'])) ?> pacote(s)</strong><small><?= e(dueAmountLabelFromCents($purchaseRow['purchase_cost_cents'] ?? 0)) ?></small></span>
                                <?php else: ?>
                                    <span class="product-stock-ready"><strong>Em estoque</strong><small>Nenhuma compra agora</small></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <form method="post" class="product-delete-form" data-confirm="Excluir este produto e todos os seus materiais? Esta ação não pode ser desfeita.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_workspace_product"><input type="hidden" name="product_id" value="<?= e((string) $selectedProductId) ?>"><button type="submit">Excluir produto</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-backdrop" data-product-create-modal hidden>
        <div class="modal-scrim" data-close-product-create></div>
        <section class="modal-card create-group-modal product-create-modal" role="dialog" aria-modal="true" aria-labelledby="product-create-title">
            <header class="modal-head"><div><h2 id="product-create-title">Novo produto</h2><p>Comece pelo nome; os custos entram na próxima etapa.</p></div><button type="button" class="modal-close-button" data-close-product-create aria-label="Fechar"><span aria-hidden="true">×</span></button></header>
            <form method="post" class="form-stack modal-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create_workspace_product">
                <label><span>Nome do produto</span><input type="text" name="name" maxlength="120" placeholder="Ex.: Pulseira Around" required data-product-create-name></label>
                <div class="modal-actions"><button type="button" class="btn btn-mini btn-ghost" data-close-product-create>Cancelar</button><button type="submit" class="btn btn-pill">Criar produto</button></div>
            </form>
        </section>
    </div>
</section>
