<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap npm-dashboard">
    <h1>🔧 NetSuite Product Manager - Dashboard</h1>
    <p class="description">Manage NetSuite product catalog with PUP pricing updates and discontinued product tracking.</p>
    
    <div class="npm-stats-grid">
        <!-- Source Data Card -->
        <div class="npm-card">
            <h2>📥 Source Data</h2>
            <ul class="npm-stat-list">
                <li>
                    <span class="npm-stat-label">NetSuite Products:</span>
                    <strong><?php echo number_format($stats['netsuite_products']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">PUP Products:</span>
                    <strong><?php echo number_format($stats['pup_products']); ?></strong>
                </li>
                <li class="<?php echo $stats['unmatched'] > 0 ? 'npm-stat-warning' : ''; ?>">
                    <span class="npm-stat-label">Unmatched (New in PUP):</span>
                    <strong><?php echo number_format($stats['unmatched']); ?></strong>
                    <?php if ($stats['unmatched'] > 0): ?>
                        <span class="npm-badge npm-badge-warning">⚠️ Needs Review</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
        
        <!-- Generated Products Card -->
        <div class="npm-card">
            <h2>🔀 Generated Products</h2>
            <ul class="npm-stat-list">
                <li>
                    <span class="npm-stat-label">Total Child SKUs:</span>
                    <strong><?php echo number_format($stats['child_products']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Parent SKUs:</span>
                    <strong><?php echo number_format($stats['parent_skus']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Discontinued:</span>
                    <strong class="npm-text-danger"><?php echo number_format($stats['discontinued']); ?></strong>
                </li>
            </ul>
        </div>
        
        <!-- Export Status Card -->
        <div class="npm-card">
            <h2>📤 Export Status</h2>
            <ul class="npm-stat-list">
                <li>
                    <span class="npm-stat-label">Export Queue:</span>
                    <strong><?php echo number_format($export_stats['total']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Pending Export:</span>
                    <strong class="npm-text-success"><?php echo number_format($export_stats['pending']); ?></strong>
                </li>
                <li>
                    <span class="npm-stat-label">Last Export:</span>
                    <strong><?php echo $export_stats['last_export'] ? date('Y-m-d H:i', strtotime($export_stats['last_export'])) : 'Never'; ?></strong>
                </li>
            </ul>
        </div>
        
        <!-- Actions Card -->
        <div class="npm-card npm-card-primary">
            <h2>⚡ Quick Actions</h2>
            <div class="npm-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=npm-import-netsuite'); ?>" class="button button-secondary">
                    📥 Import NetSuite
                </a>
                <a href="<?php echo admin_url('admin.php?page=npm-import-pup'); ?>" class="button button-secondary">
                    💰 Import PUP
                </a>
                <a href="<?php echo admin_url('admin.php?page=npm-export'); ?>" class="button button-primary">
                    🚀 Generate Export
                </a>
            </div>
        </div>
    </div>
    
    <!-- Workflow Steps -->
    <div class="npm-card" style="margin-top: 30px;">
        <h2>📋 Standard Workflow</h2>
        <ol class="npm-workflow-steps">
            <li class="<?php echo $stats['netsuite_products'] > 0 ? 'npm-step-complete' : ''; ?>">
                <strong>Import NetSuite CSV</strong>
                <span class="npm-step-status">
                    <?php echo $stats['netsuite_products'] > 0 ? '✅ Complete' : '⏳ Pending'; ?>
                </span>
                <p>Upload the latest NetSuite product dump</p>
            </li>
            <li class="<?php echo $stats['pup_products'] > 0 ? 'npm-step-complete' : ''; ?>">
                <strong>Import PUP CSV</strong>
                <span class="npm-step-status">
                    <?php echo $stats['pup_products'] > 0 ? '✅ Complete' : '⏳ Pending'; ?>
                </span>
                <p>Upload the monthly PUP pricing file</p>
            </li>
            <li class="<?php echo $stats['discontinued'] > 0 ? 'npm-step-complete' : ''; ?>">
                <strong>Upload Discontinued SKUs</strong>
                <span class="npm-step-status">
                    <?php echo $stats['discontinued'] > 0 ? '✅ ' . $stats['discontinued'] . ' uploaded' : '⏳ Optional'; ?>
                </span>
                <p>Upload CSV with discontinued product SKUs</p>
            </li>
            <li class="<?php echo $stats['child_products'] > 0 ? 'npm-step-complete' : ''; ?>">
                <strong>Generate Export</strong>
                <span class="npm-step-status">
                    <?php echo $stats['child_products'] > 0 ? '✅ ' . number_format($stats['child_products']) . ' generated' : '⏳ Pending'; ?>
                </span>
                <p>Create child SKU variations and populate export queue</p>
            </li>
            <li class="<?php echo $export_stats['exported'] > 0 ? 'npm-step-complete' : ''; ?>">
                <strong>Download & Import to NetSuite</strong>
                <span class="npm-step-status">
                    <?php echo $export_stats['exported'] > 0 ? '✅ Last: ' . date('Y-m-d', strtotime($export_stats['last_export'])) : '⏳ Pending'; ?>
                </span>
                <p>Download CSV and import back to NetSuite</p>
            </li>
        </ol>
    </div>
    
    <?php if ($stats['unmatched'] > 0): ?>
    <!-- Unmatched Products Warning -->
    <div class="npm-card npm-card-warning" style="margin-top: 20px;">
        <h2>⚠️ Action Required: Unmatched Products</h2>
        <p>
            <strong><?php echo number_format($stats['unmatched']); ?> products</strong> exist in PUP but not in NetSuite.
            These are likely new products that need to be created in NetSuite first.
        </p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=npm-unmatched'); ?>" class="button">
                View Unmatched Products
            </a>
        </p>
    </div>
    <?php endif; ?>
</div>

<style>
.npm-dashboard {
    max-width: 1400px;
}

.npm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.npm-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.npm-card-primary {
    border-left: 4px solid #2271b1;
}

.npm-card-warning {
    border-left: 4px solid #d63638;
    background: #fff3cd;
}

.npm-card h2 {
    margin-top: 0;
    font-size: 16px;
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

.npm-stat-warning {
    background: #fff3cd;
    padding: 12px !important;
    border-radius: 4px;
}

.npm-text-danger {
    color: #d63638;
}

.npm-text-success {
    color: #00a32a;
}

.npm-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.npm-badge-warning {
    background: #d63638;
    color: #fff;
}

.npm-action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.npm-action-buttons .button {
    justify-content: center;
}

.npm-workflow-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: step-counter;
}

.npm-workflow-steps li {
    position: relative;
    padding: 20px 20px 20px 60px;
    border-left: 2px solid #dcdcde;
    counter-increment: step-counter;
}

.npm-workflow-steps li:last-child {
    border-left: none;
}

.npm-workflow-steps li::before {
    content: counter(step-counter);
    position: absolute;
    left: -15px;
    top: 20px;
    width: 30px;
    height: 30px;
    background: #fff;
    border: 2px solid #dcdcde;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

.npm-workflow-steps li.npm-step-complete::before {
    background: #00a32a;
    border-color: #00a32a;
    color: #fff;
    content: "✓";
}

.npm-step-status {
    display: block;
    color: #646970;
    font-size: 13px;
    margin-top: 5px;
}

.npm-workflow-steps li p {
    margin: 5px 0 0 0;
    color: #646970;
    font-size: 13px;
}
</style>