<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get stats
global $wpdb;
$child_table = $wpdb->prefix . 'npm_child_products';
$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$child_table}"),
    'last_generated' => $wpdb->get_var("SELECT MAX(created_at) FROM {$child_table}")
];
?>

<div class="wrap">
    <h1>📤 Export to NetSuite CSV</h1>
    <p class="description">Export generated child products to CSV format for NetSuite import.</p>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Current Status</h2>
        <ul class="npm-stat-list">
            <li>
                <span class="npm-stat-label">Total Child Products:</span>
                <strong><?php echo number_format($stats['total']); ?></strong>
            </li>
            <li>
                <span class="npm-stat-label">Last Generated:</span>
                <strong><?php echo $stats['last_generated'] ? date('Y-m-d H:i', strtotime($stats['last_generated'])) : 'Never'; ?></strong>
            </li>
        </ul>
    </div>
    
    <?php if ($stats['total'] == 0): ?>
        <div class="notice notice-warning" style="margin-top: 20px;">
            <p>
                <strong>⚠️ No child products found.</strong><br>
                Please generate child products first before exporting.
            </p>
        </div>
    <?php else: ?>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Export Options</h2>
        
        <form id="npm-export-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batch_size">Batch Size</label>
                    </th>
                    <td>
                        <select name="batch_size" id="batch_size">
                            <option value="100">100 products per batch (Recommended)</option>
                            <option value="250">250 products per batch</option>
                            <option value="500">500 products per batch</option>
                            <option value="1000">1000 products per batch</option>
                        </select>
                        <p class="description">Smaller batches are slower but more reliable on shared hosting.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" 
                        id="start-export" 
                        class="button button-primary button-hero"
                        style="height: auto; padding: 12px 24px; font-size: 16px;">
                    📤 Start Export
                </button>
            </p>
        </form>
    </div>
    
    <!-- Progress Section (hidden initially) -->
    <div id="export-progress" class="npm-card" style="max-width: 700px; margin-top: 20px; display: none;">
        <h2>Export Progress</h2>
        
        <div style="margin: 20px 0;">
            <div style="background: #f0f0f1; height: 30px; border-radius: 4px; overflow: hidden; position: relative;">
                <div id="progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                <div id="progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">0%</div>
            </div>
        </div>
        
        <div id="progress-details" style="font-family: monospace; font-size: 13px; line-height: 1.8;">
            <p><strong>Status:</strong> <span id="status-text">Initializing...</span></p>
            <p><strong>Processed:</strong> <span id="processed-count">0</span> / <span id="total-count">0</span></p>
            <p><strong>Current Batch:</strong> <span id="batch-info">-</span></p>
            <p><strong>Elapsed Time:</strong> <span id="elapsed-time">0s</span></p>
            <p><strong>Estimated Remaining:</strong> <span id="estimated-time">Calculating...</span></p>
        </div>
        
        <div id="export-complete" style="display: none; margin-top: 20px; padding: 20px; background: #d1f0d1; border: 2px solid #00a32a; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #00a32a;">✅ Export Complete!</h3>
            <p><strong>CSV file ready for download:</strong></p>
            <p>
                <a id="download-link" href="#" class="button button-primary button-large" download>
                    📥 Download NetSuite Export CSV
                </a>
            </p>
            <p style="margin-top: 15px; font-size: 13px; color: #646970;">
                File location: <code id="file-path"></code>
            </p>
        </div>
        
        <div id="export-error" style="display: none; margin-top: 20px; padding: 20px; background: #fef0f0; border: 2px solid #d63638; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #d63638;">❌ Export Failed</h3>
            <p id="error-message"></p>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    let startTime;
    let totalRecords = <?php echo $stats['total']; ?>;
    let processedRecords = 0;
    let batchSize = 100;
    let exportFilename = '';
    
    $('#start-export').on('click', function() {
        batchSize = parseInt($('#batch_size').val());
        startTime = Date.now();
        processedRecords = 0;
        
        $('#npm-export-form').hide();
        $('#export-progress').show();
        $('#export-complete').hide();
        $('#export-error').hide();
        
        // Initialize export
        initializeExport();
    });
    
    function initializeExport() {
        updateStatus('Initializing export...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'npm_initialize_export',
                nonce: '<?php echo wp_create_nonce('npm_export'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    exportFilename = response.data.filename;
                    updateStatus('Starting batch processing...');
                    processBatch(0);
                } else {
                    showError(response.data.message || 'Failed to initialize export');
                }
            },
            error: function() {
                showError('AJAX request failed');
            }
        });
    }
    
    function processBatch(offset) {
        updateStatus('Processing batch...');
        $('#batch-info').text('Batch starting at record ' + offset);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'npm_export_batch',
                nonce: '<?php echo wp_create_nonce('npm_export'); ?>',
                offset: offset,
                batch_size: batchSize,
                filename: exportFilename
            },
            success: function(response) {
                if (response.success) {
                    processedRecords += response.data.processed;
                    updateProgress();
                    
                    if (response.data.complete) {
                        // Export complete
                        completeExport(response.data.file_url, response.data.file_path);
                    } else {
                        // Process next batch
                        processBatch(offset + batchSize);
                    }
                } else {
                    showError(response.data.message || 'Batch processing failed');
                }
            },
            error: function(xhr, status, error) {
                showError('AJAX error: ' + error);
            }
        });
    }
    
    function updateProgress() {
        let percentage = Math.min(100, Math.round((processedRecords / totalRecords) * 100));
        
        $('#progress-bar').css('width', percentage + '%');
        $('#progress-text').text(percentage + '%');
        $('#processed-count').text(processedRecords.toLocaleString());
        $('#total-count').text(totalRecords.toLocaleString());
        
        // Update timing
        let elapsed = Math.round((Date.now() - startTime) / 1000);
        $('#elapsed-time').text(elapsed + 's');
        
        if (processedRecords > 0) {
            let rate = processedRecords / elapsed;
            let remaining = Math.round((totalRecords - processedRecords) / rate);
            $('#estimated-time').text(remaining + 's');
        }
        
        updateStatus('Processing... ' + processedRecords.toLocaleString() + ' / ' + totalRecords.toLocaleString());
    }
    
    function completeExport(fileUrl, filePath) {
        updateStatus('Complete!');
        $('#progress-bar').css('width', '100%');
        $('#progress-text').text('100%');
        
        $('#download-link').attr('href', fileUrl);
        $('#file-path').text(filePath);
        $('#export-complete').show();
    }
    
    function showError(message) {
        updateStatus('Error occurred');
        $('#error-message').text(message);
        $('#export-error').show();
    }
    
    function updateStatus(status) {
        $('#status-text').text(status);
    }
});
</script>

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