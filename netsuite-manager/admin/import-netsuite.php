<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>📥 Import NetSuite Product Dump</h1>
    <p class="description">Upload the latest NetSuite product CSV export. This will replace all existing NetSuite data.</p>
    
    <?php // Initialize variable to avoid undefined warning
$import_result = null;
	if ($import_result !== null): ?>
        <?php if ($import_result['success']): ?>
            <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
                <p>
                    <strong>✅ Import Successful!</strong><br>
                    Inserted: <strong><?php echo number_format($import_result['inserted']); ?></strong> records
                    <?php if (!empty($import_result['errors'])): ?>
                        <br>⚠️ Errors: <?php echo count($import_result['errors']); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-error is-dismissible" style="margin-top: 20px;">
                <p>
                    <strong>❌ Import Failed</strong><br>
                    Error: <?php echo esc_html($import_result['error']); ?>
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Current Status</h2>
        <ul class="npm-stat-list">
            <li>
                <span class="npm-stat-label">Total Products:</span>
                <strong><?php echo number_format($stats['count']); ?></strong>
            </li>
            <li>
                <span class="npm-stat-label">Last Import:</span>
                <strong><?php echo $stats['last_import'] ? date('Y-m-d H:i', strtotime($stats['last_import'])) : 'Never'; ?></strong>
            </li>
        </ul>
    </div>
    
    <!-- WARNING: Scientific Notation -->
    <div class="npm-card npm-card-warning" style="max-width: 700px; margin-top: 20px;">
        <h2>⚠️ Important: Check CSV Format Before Importing</h2>
        <p><strong>Check the CSV file for format errors in columns "Name" and "Parent"</strong></p>
        
        <div style="background: #fff; padding: 15px; border-left: 4px solid #d63638; margin: 15px 0;">
            <h3 style="margin-top: 0; color: #d63638;">❌ Scientific Notation Error Example:</h3>
            <pre style="background: #f5f5f5; padding: 10px; font-family: monospace;">Name: 6.6634E+12  ← WRONG (scientific notation)</pre>
            
            <p style="margin: 10px 0;">
                <strong>Problem:</strong> Scientific notation loses precision. Once <code>6663395246218</code> becomes 
                <code>6.66E+12</code> in the CSV, it's permanently rounded to <code>666000000000</code> and 
                <strong>cannot be recovered during import</strong>.
            </p>
        </div>
        
        <h3>✅ How to Fix:</h3>
        <ol style="line-height: 1.8;">
            <li><strong>Before opening CSV in Excel:</strong>
                <ul style="margin-top: 5px;">
                    <li>Use <strong>Data → From Text/CSV</strong> (not double-click)</li>
                    <li>Set "Name" and "Parent" columns as <strong>Text</strong> format</li>
                </ul>
            </li>
            <li><strong>If already opened:</strong>
                <ul style="margin-top: 5px;">
                    <li>Re-export from NetSuite</li>
                    <li>Don't double-click the CSV file</li>
                    <li>Import properly using method above</li>
                </ul>
            </li>
            <li><strong>Alternative:</strong> Use Google Sheets (handles large numbers better)</li>
        </ol>
        
        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 10px; margin-top: 15px;">
            <strong>💡 Tip:</strong> Open your CSV in a text editor (Notepad, TextEdit) first to verify the "Name" column 
            shows full numbers like <code>6663395246218</code>, not <code>6.66E+12</code>.
        </div>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Upload CSV</h2>
        
        <form method="post" enctype="multipart/form-data" action="">
            <?php wp_nonce_field('npm_import_netsuite'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="netsuite_csv">NetSuite CSV File</label>
                    </th>
                    <td>
                        <input type="file" 
                               name="netsuite_csv" 
                               id="netsuite_csv" 
                               accept=".csv" 
                               required 
                               style="width: 100%; max-width: 400px;">
                        <p class="description">Select the NetSuite product dump CSV file</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="npm_import_netsuite" 
                       class="button button-primary" 
                       value="📥 Import NetSuite CSV">
                <span style="margin-left: 10px; color: #d63638;">
                    ⚠️ This will replace all existing NetSuite data
                </span>
            </p>
        </form>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>ℹ️ CSV Format Requirements</h2>
        <ul style="line-height: 1.8;">
            <li><strong>Required columns:</strong> Internal ID, Name</li>
            <li><strong>Optional columns:</strong> Disabled In Magento, Delivery & Install, Manufacturer, Class, Parent, Purchase Price, Online Customer Price, RRP, Matrix Item, Store Display Name, Store Description, Detailed Description</li>
            <li><strong>SKU Suffix:</strong> Will be auto-extracted from the "Name" column</li>
            <li><strong>File format:</strong> UTF-8 encoded CSV</li>
            <li><strong>⚠️ Name/Parent columns:</strong> Must be full numbers, not scientific notation (6.66E+12)</li>
        </ul>
        
        <h3>Example CSV Structure:</h3>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">Internal ID,Name,Parent,Manufacturer,RRP
184766,6663395246218,,Dynamic Office Seating,1052
184767,HA01185,6663395246218,Dynamic Office Seating,1052
184815,HA01185DI,6663395246218,Dynamic Office Seating,1052</pre>
        
        <p style="margin-top: 10px; font-size: 13px; color: #646970;">
            ✅ Notice how Name and Parent show full numbers, not scientific notation
        </p>
    </div>
    
    <!-- Debug Information -->
    <div class="npm-card" style="max-width: 700px; margin-top: 20px; background: #f0f0f1;">
        <h2>🔍 Debug Information</h2>
        
        <?php
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        ?>
        
        <ul style="font-family: monospace; font-size: 12px; line-height: 1.8;">
            <li>
                <strong>WordPress Prefix:</strong> 
                <code><?php echo esc_html($wpdb->prefix); ?></code>
            </li>
            <li>
                <strong>Full Table Name:</strong> 
                <code><?php echo esc_html($table_name); ?></code>
            </li>
            <li>
                <strong>Table exists:</strong> 
                <?php echo $table_exists ? '✅ Yes' : '❌ No'; ?>
            </li>
            <li>
                <strong>Current record count:</strong> 
                <strong><?php echo number_format($stats['count']); ?></strong>
            </li>
            <li>
                <strong>Upload max filesize:</strong> 
                <?php echo ini_get('upload_max_filesize'); ?>
            </li>
            <li>
                <strong>Post max size:</strong> 
                <?php echo ini_get('post_max_size'); ?>
            </li>
            <li>
                <strong>Max execution time:</strong> 
                <?php echo ini_get('max_execution_time'); ?>s
            </li>
            <li>
                <strong>Memory limit:</strong> 
                <?php echo ini_get('memory_limit'); ?>
            </li>
        </ul>
        
        <?php if ($table_exists && $stats['count'] > 0): ?>
        <h3>Recent Imports (Last 5):</h3>
        <?php
        $recent = $wpdb->get_results(
            "SELECT internal_id, name, sku_suffix, imported_at 
             FROM {$table_name}
             ORDER BY imported_at DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        if (!empty($recent)):
        ?>
        <table class="widefat" style="margin-top: 10px; font-size: 12px;">
            <thead>
                <tr>
                    <th>Internal ID</th>
                    <th>Name</th>
                    <th>SKU Suffix</th>
                    <th>Imported</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['internal_id']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td><?php echo esc_html($row['sku_suffix']); ?></td>
                    <td><?php echo esc_html($row['imported_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php else: ?>
        <p style="color: #999; margin-top: 15px;">No records imported yet. Try uploading a CSV file above.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.npm-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.npm-card h2 {
    margin-top: 0;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 12px;
    margin-bottom: 15px;
}

.npm-card-warning {
    border-left: 4px solid #dba617;
    background: #fff3cd;
}

.npm-card-warning h2 {
    color: #996800;
}

.npm-card-warning code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #d63638;
    font-weight: 600;
}

.npm-stat-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.npm-stat-list li {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.npm-stat-list li:last-child {
    border-bottom: none;
}

.npm-stat-label {
    color: #646970;
    font-size: 14px;
}
</style>