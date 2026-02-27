<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>📤 Export to NetSuite</h1>
    <p class="description">Generate child SKU variations and export to NetSuite-ready CSV format.</p>
    
    <!-- Export Stats -->
    <div class="npm-stats-grid" style="max-width: 900px;">
        <div class="npm-card">
            <h2>📊 Export Queue Status</h2>
            <ul class="npm-stat-list">
                <li>
                    <span class="npm-stat-label">Total Products:</span>
                    <strong><?php echo number_format($export_stats['total']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Pending Export:</span>
                    <strong class="npm-text-success"><?php echo number_format($export_stats['pending']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Discontinued:</span>
                    <strong class="npm-text-danger"><?php echo number_format($export_stats['discontinued']); ?></strong>
                </li>
            </ul>
        </div>
        
        <div class="npm-card">
            <h2>🔗 NetSuite Linking</h2>
            <ul class="npm-stat-list">
                <li>
                    <span class="npm-stat-label">With NetSuite ID:</span>
                    <strong><?php echo number_format($export_stats['with_netsuite_id']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Missing NetSuite ID:</span>
                    <strong class="npm-text-warning"><?php echo number_format($export_stats['total'] - $export_stats['with_netsuite_id']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Last Export:</span>
                    <strong><?php echo $export_stats['last_export'] ? date('Y-m-d H:i', strtotime($export_stats['last_export'])) : 'Never'; ?></strong>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Generate Export -->
    <div class="npm-card" style="max-width: 900px; margin-top: 20px;">
        <h2>🚀 Generate Export</h2>
        <p>This will:</p>
        <ol style="line-height: 1.8;">
            <li>Create base SKUs from PUP data with default delivery methods</li>
            <li>Generate child SKU variations (ND, DI, B, BE) using supplier dataset delivery costs</li>
            <li>Link NetSuite internal IDs to all SKUs</li>
            <li>Mark discontinued products (base + all variants)</li>
            <li>Populate the export queue for CSV download</li>
        </ol>
        
        <form method="post">
            <?php wp_nonce_field('npm_generate_export'); ?>
            <p class="submit">
                <button type="submit" name="npm_generate_export" class="button button-primary button-hero">
                    ⚡ Generate Export Queue
                </button>
                <span style="margin-left: 15px; color: #646970;">
                    ⏱️ This may take 1-2 minutes for large datasets
                </span>
            </p>
        </form>
    </div>
    
    <!-- Validation Results -->
    <?php if ($export_stats['total'] > 0): ?>
    <div class="npm-card <?php echo !$validation['valid'] ? 'npm-card-error' : 'npm-card-success'; ?>" style="max-width: 900px; margin-top: 20px;">
        <h2><?php echo $validation['valid'] ? '✅ Export Ready' : '❌ Export Validation Failed'; ?></h2>
        
        <?php if (!empty($validation['errors'])): ?>
        <div style="background: #fff; padding: 15px; border-left: 4px solid #d63638; margin-bottom: 15px;">
            <h3 style="margin-top: 0; color: #d63638;">Errors (must be fixed):</h3>
            <ul style="margin: 0; color: #d63638;">
                <?php foreach ($validation['errors'] as $error): ?>
                <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($validation['warnings'])): ?>
        <div style="background: #fff; padding: 15px; border-left: 4px solid #dba617; margin-bottom: 15px;">
            <h3 style="margin-top: 0; color: #996800;">Warnings (review recommended):</h3>
            <ul style="margin: 0; color: #996800;">
                <?php foreach ($validation['warnings'] as $warning): ?>
                <li><?php echo esc_html($warning); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($validation['valid']): ?>
        <p style="color: #00a32a; font-weight: 600; margin: 0;">
            ✅ All validations passed. Export is ready for download.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Download Exports -->
    <?php if ($export_stats['total'] > 0 && $validation['valid']): ?>
    <div class="npm-card" style="max-width: 900px; margin-top: 20px;">
        <h2>📥 Download Exports</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- NetSuite Update -->
            <div style="border: 1px solid #dcdcde; padding: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">📋 NetSuite Update CSV</h3>
                <p>Complete product update file for NetSuite import.</p>
                <ul style="font-size: 13px; color: #646970; line-height: 1.6;">
                    <li>All child SKU variations</li>
                    <li>Updated pricing from PUP</li>
                    <li>Discontinued flags</li>
                    <li>NetSuite internal IDs</li>
                </ul>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?npm_download=netsuite_update'), 'npm_download_netsuite_update'); ?>" 
                   class="button button-primary button-large" 
                   style="width: 100%; text-align: center;">
                    📥 Download NetSuite CSV
                </a>
                <p style="margin-top: 10px; font-size: 12px; color: #646970;">
                    <?php echo number_format($export_stats['total']); ?> products
                </p>
            </div>
            
            <!-- Magento Specials -->
            <div style="border: 1px solid #dcdcde; padding: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">🏷️ Magento Specials CSV</h3>
                <p>Promotional pricing for Magento import.</p>
                
                <form method="get" style="margin-top: 15px;">
                    <input type="hidden" name="npm_download" value="magento_specials">
                    <?php wp_nonce_field('npm_download_magento_specials', '_wpnonce', false); ?>
                    
                    <label style="display: block; margin-bottom: 10px;">
                        <strong>Delivery Multiplier:</strong>
                        <input type="number" step="0.01" name="delivery_mult" value="1.10" style="width: 80px; margin-left: 5px;">
                        <span style="font-size: 12px; color: #646970;">(e.g., 1.10 = +10%)</span>
                    </label>
                    
                    <label style="display: block; margin-bottom: 15px;">
                        <strong>Invoice Multiplier:</strong>
                        <input type="number" step="0.01" name="invoice_mult" value="1.25" style="width: 80px; margin-left: 5px;">
                        <span style="font-size: 12px; color: #646970;">(e.g., 1.25 = +25%)</span>
                    </label>
                    
                    <button type="submit" class="button button-secondary button-large" style="width: 100%;">
                        📥 Download Magento CSV
                    </button>
                </form>
                
                <?php
                global $wpdb;
                $promo_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_child_products WHERE has_promo = 1");
                ?>
                <p style="margin-top: 10px; font-size: 12px; color: #646970;">
                    <?php echo number_format($promo_count); ?> products with promos
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Preview Sample -->
    <?php if ($export_stats['total'] > 0): ?>
    <div class="npm-card" style="margin-top: 20px;">
        <h2>👀 Export Preview (First 10 Records)</h2>
        <?php
        global $wpdb;
        $preview = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}npm_export_queue ORDER BY parent, product_code LIMIT 10",
            ARRAY_A
        );
        ?>
        
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="min-width: 1200px;">
                <thead>
                    <tr>
                        <th style="width: 100px;">NetSuite ID</th>
                        <th style="width: 120px;">SKU</th>
                        <th style="width: 100px;">Parent</th>
                        <th style="width: 80px;">RRP</th>
                        <th style="width: 80px;">Cost</th>
                        <th style="width: 80px;">Online</th>
                        <th style="width: 80px;">Promo</th>
                        <th style="width: 60px;">Disc.</th>
                        <th>Delivery Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $row): ?>
                    <tr>
                        <td><small><?php echo esc_html($row['netsuite_internal_id']); ?></small></td>
                        <td><code><?php echo esc_html($row['product_code']); ?></code></td>
                        <td><small><?php echo esc_html($row['parent']); ?></small></td>
                        <td>£<?php echo number_format($row['rrp'], 2); ?></td>
                        <td>£<?php echo number_format($row['cost_price'], 2); ?></td>
                        <td>£<?php echo number_format($row['online_price'], 2); ?></td>
                        <td><?php echo $row['promo_price'] ? '£' . number_format($row['promo_price'], 2) : '<span style="color:#999;">—</span>'; ?></td>
                        <td>
                            <?php if ($row['discontinued']): ?>
                            <span style="color: #d63638; font-weight: 600;">✖</span>
                            <?php else: ?>
                            <span style="color: #00a32a;">✓</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo esc_html($row['delivery_method']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.npm-card-success {
    border-left: 4px solid #00a32a;
    background: #f0f6fc;
}

.npm-card-error {
    border-left: 4px solid #d63638;
    background: #fcf0f1;
}

.npm-text-warning {
    color: #996800;
}
</style>