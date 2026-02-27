<?php
if (!defined('ABSPATH')) {
    exit;
}

// Initialize result variable
$import_result = null;

// Process import when file is uploaded
if (isset($_FILES['discontinued_csv']) && !empty($_FILES['discontinued_csv']['name']) && $_FILES['discontinued_csv']['error'] === UPLOAD_ERR_OK) {
    
    $file_path = $_FILES['discontinued_csv']['tmp_name'];
    
    if (file_exists($file_path)) {
        require_once NPM_PLUGIN_DIR . 'includes/class-npm-importer.php';
        $importer = new NPM_Importer();
        $import_result = $importer->import_discontinued_csv($file_path);
    } else {
        $import_result = [
            'success' => false,
            'error' => 'Uploaded file not found'
        ];
    }
} elseif (isset($_FILES['discontinued_csv']) && $_FILES['discontinued_csv']['error'] !== UPLOAD_ERR_OK && $_FILES['discontinued_csv']['error'] !== UPLOAD_ERR_NO_FILE) {
    $import_result = [
        'success' => false,
        'error' => 'File upload error code: ' . $_FILES['discontinued_csv']['error']
    ];
}

// Refresh stats
global $wpdb;
$table_name = $wpdb->prefix . 'npm_discontinued_skus';
$stats = [
    'count' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
    'last_import' => $wpdb->get_var("SELECT MAX(imported_at) FROM {$table_name}")
];
?>

<div class="wrap">
    <h1>📥 Import Discontinued SKUs</h1>
    <p class="description">Upload a CSV file containing discontinued SKU numbers. This will replace all existing discontinued SKU data.</p>
    
    <?php if ($import_result !== null): ?>
        <?php if ($import_result['success']): ?>
            <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
                <p>
                    <strong>✅ Discontinued SKUs Import Successful!</strong><br>
                    Inserted: <strong><?php echo number_format($import_result['inserted']); ?></strong> SKUs
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
                <span class="npm-stat-label">Total Discontinued SKUs:</span>
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
            <?php wp_nonce_field('npm_import_discontinued'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="discontinued_csv">Discontinued SKUs CSV File</label>
                    </th>
                    <td>
                        <input type="file" 
                               name="discontinued_csv" 
                               id="discontinued_csv" 
                               accept=".csv" 
                               required 
                               style="width: 100%; max-width: 400px;">
                        <p class="description">Select the discontinued SKUs CSV file</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="npm_import_discontinued" 
                       class="button button-primary" 
                       value="📥 Import Discontinued SKUs">
                <span style="margin-left: 10px; color: #d63638;">
                    ⚠️ This will replace all existing discontinued SKU data
                </span>
            </p>
        </form>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>ℹ️ CSV Format Requirements</h2>
        <ul style="line-height: 1.8;">
            <li><strong>Required column:</strong> SKU (or any single column with SKU values)</li>
            <li><strong>File format:</strong> UTF-8 encoded CSV</li>
            <li><strong>Simple list:</strong> One SKU per row</li>
            <li><strong>Header optional:</strong> First row can be header or data</li>
        </ul>
        
        <h3>Example CSV Structure (Option 1 - With Header):</h3>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px;">SKU
AC000001
AC000025
HA01185DI</pre>
        
        <h3>Example CSV Structure (Option 2 - No Header):</h3>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px;">AC000001
AC000025
HA01185DI</pre>
        
        <p style="margin-top: 10px; color: #646970; font-size: 13px;">
            💡 <strong>Tip:</strong> The importer will automatically detect if the first row is a header (like "SKU") or actual data.
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
        </ul>
        
        <?php if ($table_exists && $stats['count'] > 0): ?>
        <h3>Recent Imports (Last 10 SKUs):</h3>
        <?php
        $recent = $wpdb->get_results(
            "SELECT sku, imported_at 
             FROM {$table_name}
             ORDER BY imported_at DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        if (!empty($recent)):
        ?>
        <table class="widefat" style="margin-top: 10px; font-size: 12px;">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Imported</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                <tr>
                    <td><code><?php echo esc_html($row['sku']); ?></code></td>
                    <td><?php echo esc_html($row['imported_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php else: ?>
        <p style="color: #999; margin-top: 15px;">No discontinued SKUs imported yet. Try uploading a CSV file above.</p>
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

code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
</style>