<?php
if (!defined('ABSPATH')) {
    exit;
}

// Initialize result variable
$import_result = null;

// Process import when file is uploaded
if (isset($_FILES['pup_csv']) && !empty($_FILES['pup_csv']['name']) && $_FILES['pup_csv']['error'] === UPLOAD_ERR_OK) {
    
    $file_path = $_FILES['pup_csv']['tmp_name'];
    
    if (file_exists($file_path)) {
        require_once NPM_PLUGIN_DIR . 'includes/class-npm-importer.php';
        $importer = new NPM_Importer();
        $import_result = $importer->import_pup_csv($file_path);
    } else {
        $import_result = [
            'success' => false,
            'error' => 'Uploaded file not found'
        ];
    }
} elseif (isset($_FILES['pup_csv']) && $_FILES['pup_csv']['error'] !== UPLOAD_ERR_OK && $_FILES['pup_csv']['error'] !== UPLOAD_ERR_NO_FILE) {
    $import_result = [
        'success' => false,
        'error' => 'File upload error code: ' . $_FILES['pup_csv']['error']
    ];
}

// Refresh stats
global $wpdb;
$table_name = $wpdb->prefix . 'npm_pup_updates';
$stats = [
    'count' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
    'last_import' => $wpdb->get_var("SELECT MAX(imported_at) FROM {$table_name}")
];
?>

<div class="wrap">
    <h1>📥 Import PUP Product Updates</h1>
    <p class="description">Upload the latest PUP product CSV file. This will replace all existing PUP data.</p>
    
    <?php if ($import_result !== null): ?>
        <?php if ($import_result['success']): ?>
            <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
                <p>
                    <strong>✅ PUP Import Successful!</strong><br>
                    Inserted: <strong><?php echo number_format($import_result['inserted']); ?></strong> records
                    <?php if (!empty($import_result['errors'])): ?>
                        <br>⚠️ Errors: <?php echo count($import_result['errors']); ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; color: #2271b1;">View Errors</summary>
                            <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; max-height: 200px;"><?php
                                foreach (array_slice($import_result['errors'], 0, 20) as $error) {
                                    echo esc_html($error) . "\n";
                                }
                            ?></pre>
                        </details>
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
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Upload CSV</h2>
        
        <form method="post" enctype="multipart/form-data" action="">
            <?php wp_nonce_field('npm_import_pup'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pup_csv">PUP CSV File</label>
                    </th>
                    <td>
                        <input type="file" 
                               name="pup_csv" 
                               id="pup_csv" 
                               accept=".csv" 
                               required 
                               style="width: 100%; max-width: 400px;">
                        <p class="description">Select the PUP product updates CSV file</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="npm_import_pup" 
                       class="button button-primary" 
                       value="📥 Import PUP CSV">
                <span style="margin-left: 10px; color: #d63638;">
                    ⚠️ This will replace all existing PUP data
                </span>
            </p>
        </form>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>ℹ️ CSV Format Requirements</h2>
        <ul style="line-height: 1.8;">
            <li><strong>Required columns:</strong> SKU</li>
            <li><strong>Optional columns:</strong> Catalogue Code, Category, Sub-Category, Product Name, RRP, Standard Invoice Cost, Invoice Cost (4+ Items), Next Day Cost, 7-10 Day Install Cost, 3-5 Day Prebuild Cost, Next Day Prebuild Cost, NEW, Status, Promotion Available, Promo Code, Promo Cost, Promo Minimum Order Spend, Promo Surcharge, Promo Cost plus Surcharge</li>
            <li><strong>File format:</strong> UTF-8 encoded CSV</li>
            <li><strong>Expected records:</strong> ~20,000+ products</li>
        </ul>
        
        <h3>Example CSV Structure:</h3>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px;">SKU,Catalogue Code,Category,Sub-Category,Product Name,RRP,Standard Invoice Cost
AC000001,CHIROFOLDINGARMS,Seating,Accessory,Chiro Height Adjustable And Foldaway Arm,80,29.6
AC000002,ISOARMS,Seating,Accessory,ISO Black Shaped Arm Set,41,15.17</pre>
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
            "SELECT sku, product_name, category, rrp, status, imported_at 
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
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>RRP</th>
                    <th>Status</th>
                    <th>Imported</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['sku']); ?></td>
                    <td><?php echo esc_html($row['product_name']); ?></td>
                    <td><?php echo esc_html($row['category']); ?></td>
                    <td><?php echo esc_html($row['rrp']); ?></td>
                    <td><?php echo esc_html($row['status']); ?></td>
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

.npm-card h3 {
    font-size: 14px;
    margin-top: 20px;
    margin-bottom: 10px;
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

details {
    cursor: pointer;
}

details summary {
    color: #2271b1;
    text-decoration: underline;
}

details summary:hover {
    color: #135e96;
}
</style>