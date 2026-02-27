<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>? Import NetSuite Product Dump</h1>
    <p class="description">Upload the latest NetSuite product CSV export. This will replace all existing NetSuite data.</p>
    
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
        <h2>?? Important: Check CSV Format Before Importing</h2>
        <p><strong>Check the CSV file for format errors in columns "Name" and "Parent"</strong></p>
        
        <div style="background: #fff; padding: 15px; border-left: 4px solid #d63638; margin: 15px 0;">
            <h3 style="margin-top: 0; color: #d63638;">? Scientific Notation Error Example:</h3>
            <pre style="background: #f5f5f5; padding: 10px; font-family: monospace;">Name: 6.6634E+12  ? WRONG (scientific notation)</pre>
            
            <p style="margin: 10px 0;">
                <strong>Problem:</strong> Scientific notation loses precision. Once <code>6663395246218</code> becomes 
                <code>6.66E+12</code> in the CSV, it's permanently rounded to <code>666000000000</code> and 
                <strong>cannot be recovered during import</strong>.
            </p>
        </div>
        
        <h3>? How to Fix:</h3>
        <ol style="line-height: 1.8;">
            <li><strong>Before opening CSV in Excel:</strong>
                <ul style="margin-top: 5px;">
                    <li>Use <strong>Data ? From Text/CSV</strong> (not double-click)</li>
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
            <strong>? Tip:</strong> Open your CSV in a text editor (Notepad, TextEdit) first to verify the "Name" column 
            shows full numbers like <code>6663395246218</code>, not <code>6.66E+12</code>.
        </div>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>Upload CSV</h2>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('npm_import_netsuite'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="netsuite_csv">NetSuite CSV File</label>
                    </th>
                    <td>
                        <input type="file" name="netsuite_csv" id="netsuite_csv" accept=".csv" required>
                        <p class="description">Select the NetSuite product dump CSV file</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="npm_import_netsuite" class="button button-primary">
                    ? Import NetSuite CSV
                </button>
                <span style="margin-left: 10px; color: #d63638;">
                    ?? This will replace all existing NetSuite data
                </span>
            </p>
        </form>
    </div>
    
    <div class="npm-card" style="max-width: 700px; margin-top: 20px;">
        <h2>?? CSV Format Requirements</h2>
        <ul style="line-height: 1.8;">
            <li><strong>Required columns:</strong> Internal ID, Name</li>
            <li><strong>Optional columns:</strong> Disabled In Magento, Delivery & Install, Manufacturer, Class, Parent, Purchase Price, Online Customer Price, RRP, Matrix Item, Store Display Name, Store Description, Detailed Description</li>
            <li><strong>SKU Suffix:</strong> Will be auto-extracted from the "Name" column</li>
            <li><strong>File format:</strong> UTF-8 encoded CSV</li>
            <li><strong>?? Name/Parent columns:</strong> Must be full numbers, not scientific notation (6.66E+12)</li>
        </ul>
        
        <h3>Example CSV Structure:</h3>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">Internal ID,Name,Parent,Manufacturer,RRP
184766,6663395246218,,Dynamic Office Seating,1052
184767,HA01185,6663395246218,Dynamic Office Seating,1052
184815,HA01185DI,6663395246218,Dynamic Office Seating,1052</pre>
        
        <p style="margin-top: 10px; font-size: 13px; color: #646970;">
            ? Notice how Name and Parent show full numbers, not scientific notation
        </p>
    </div>
</div>

<style>
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

.npm-card-warning pre {
    overflow-x: auto;
    white-space: pre;
}
</style>