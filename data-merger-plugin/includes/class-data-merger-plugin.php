<?php

class Data_Merger_Plugin {

    public static function activate() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        // Table definitions (primary keys at end of CREATE TABLE)
        $tables = [
            $wpdb->prefix . 'pup_updates' => "CREATE TABLE {$wpdb->prefix}pup_updates (
                id INT AUTO_INCREMENT,
                sku VARCHAR(32) UNIQUE,
                catalogue_code VARCHAR(64),
                category VARCHAR(64),
                sub_category VARCHAR(64),
                product_name VARCHAR(128),
                rrp DECIMAL(10,2),
                standard_invoice_cost DECIMAL(10,2),
                invoice_cost_4plus DECIMAL(10,2),
                next_day_cost DECIMAL(10,2),
                install_cost_7_10 DECIMAL(10,2),
                prebuild_cost_3_5 DECIMAL(10,2),
                prebuild_cost_next_day DECIMAL(10,2),
                new_status VARCHAR(16),
                status VARCHAR(16),
                promotion_available VARCHAR(16),
                promo_code VARCHAR(32),
                promo_cost DECIMAL(10,2),
                promo_min_order_spend VARCHAR(32),
                promo_surcharge VARCHAR(32),
                promo_cost_plus_surcharge DECIMAL(10,2),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'netsuite_product_dump' => "CREATE TABLE {$wpdb->prefix}netsuite_product_dump (
                id INT AUTO_INCREMENT,
                internal_id VARCHAR(32) UNIQUE,
                disabled_in_magento VARCHAR(8),
                name VARCHAR(128),
                sku_suffix VARCHAR(128),
                display_name VARCHAR(128),
                preferred_supplier VARCHAR(128),
                online_price DECIMAL(10,2),
                supplier_price DECIMAL(10,2),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'merged_products' => "CREATE TABLE {$wpdb->prefix}merged_products (
                id INT AUTO_INCREMENT,
                netsuite_id VARCHAR(32),
                sku VARCHAR(32),
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'data_merger_missing' => "CREATE TABLE {$wpdb->prefix}data_merger_missing (
                id INT AUTO_INCREMENT,
                source VARCHAR(32),
                key_field VARCHAR(64),
                name_field VARCHAR(128),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'data_merger_delivery_costs' => "CREATE TABLE {$wpdb->prefix}data_merger_delivery_costs (
                id INT AUTO_INCREMENT,
                furniture_type VARCHAR(64) NOT NULL,
                delivery_option VARCHAR(64) NOT NULL,
                cost DECIMAL(10,2) NOT NULL,
                UNIQUE KEY unique_row (furniture_type, delivery_option),
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'data_merger_discontinued_skus' => "CREATE TABLE {$wpdb->prefix}data_merger_discontinued_skus (
                id INT AUTO_INCREMENT,
                discontinued_sku VARCHAR(128) NOT NULL UNIQUE,
                internal_id VARCHAR(32),
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'child_sku_price' => "CREATE TABLE {$wpdb->prefix}child_sku_price (
                id INT AUTO_INCREMENT,
                parent_sku VARCHAR(128),
                child_sku VARCHAR(128) NOT NULL,
                delivery_option VARCHAR(64),
                delivery_price DECIMAL(10,2),
                rrp DECIMAL(10,2),
                standard_invoice_cost DECIMAL(10,2),
                new_status VARCHAR(16),
                status VARCHAR(16),
                promo_cost DECIMAL(10,2),
                has_promo TINYINT(1) DEFAULT 0,
                cost DECIMAL(10,2),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
        ];

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_csv_download']);
        add_action('admin_init', [$this, 'maybe_export_magento_specials_csv']);
    }

    public function register_admin_menu() {
        add_menu_page(
            'Data Merger',
            'Data Merger',
            'manage_options',
            'data-merger-admin',
            [$this, 'admin_page'],
            'dashicons-database',
            26
        );
        add_submenu_page(
            'data-merger-admin',
            'PUP Updates',
            'PUP Updates',
            'manage_options',
            'data-merger-pup',
            [$this, 'view_pup_table']
        );
        add_submenu_page(
            'data-merger-admin',
            'NetSuite Dump',
            'NetSuite Dump',
            'manage_options',
            'data-merger-netsuite',
            [$this, 'view_netsuite_table']
        );
        add_submenu_page(
            'data-merger-admin',
            'Merged Products',
            'Merged Products',
            'manage_options',
            'data-merger-merged',
            [$this, 'view_merged_table']
        );
        add_submenu_page(
            'data-merger-admin',
            'Unmatched Records',
            'Unmatched Records',
            'manage_options',
            'data-merger-missing',
            [$this, 'view_missing_table']
        );
        add_submenu_page(
            'data-merger-admin',
            'Delivery Costs',
            'Delivery Costs',
            'manage_options',
            'data-merger-delivery-costs',
            'data_merger_delivery_costs_admin_page'
        );
        add_submenu_page(
            'data-merger-admin',
            'Discontinued SKUs',
            'Discontinued SKUs',
            'manage_options',
            'data-merger-discontinued',
            [$this, 'discontinued_skus_page']
        );
        add_submenu_page(
            'data-merger-admin',
            'Export Merged',
            'Export Merged',
            'manage_options',
            'data-merger-export',
            [$this, 'export_merged_page']
        );
        add_submenu_page(
            'data-merger-admin',
            'Export Magento Specials',
            'Export Magento Specials',
            'manage_options',
            'data-merger-magento-specials',
            [$this, 'export_magento_specials_page']
        );
    }

    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>Data Merger Admin</h1>';
        echo '<p>Use the submenu to manage data tables and merge data.</p>';
        echo '</div>';
    }

    public function view_pup_table() {
        $title = 'PUP Updates Table';
        $desc = 'This table contains the latest PUP product updates uploaded via CSV. Each row represents a product update record.';
        $this->output_csv_and_table('pup_updates', $title, $desc);
    }

    public function view_netsuite_table() {
        $title = 'NetSuite Product Dump Table';
        $desc = 'This table contains the latest NetSuite product data uploaded via CSV. Each row represents a product record from NetSuite.';
        $this->output_csv_and_table('netsuite_product_dump', $title, $desc);
    }

    public function view_merged_table() {
        $title = 'Merged Products Table';
        $desc = 'This table contains products merged from the NetSuite and PUP tables, showing only records matched by SKU.';
        echo '<div class="wrap">';
        echo $this->get_download_button('merged_products', 'Download Merged Products CSV');
        echo '<form method="post"><button name="run_merge" class="button button-primary">Run Merge Script</button></form>';
        if (isset($_POST['run_merge'])) {
            $this->run_merge_script();
            echo '<div class="updated notice"><p>Merged data created in merged_products table. Unmatched records recorded.</p></div>';
        }
        $this->render_table('merged_products', $title, $desc);
        echo '</div>';
    }

    public function view_missing_table() {
        $title = 'Unmatched Records Table';
        $desc = 'This table contains records from either upload (PUP or NetSuite) that could not be matched during the merge operation.';
        echo '<div class="wrap">';
        echo $this->get_download_button('data_merger_missing', 'Download Unmatched Records CSV');
        $this->render_table('data_merger_missing', $title, $desc);
        echo '</div>';
    }

    private function output_csv_and_table($table, $title, $desc) {
        echo '<div class="wrap">';
        $this->handle_csv_upload($table);
        $this->render_table($table, $title, $desc);
        echo '</div>';
    }

    private function get_download_button($table, $label) {
        return '<form method="get" style="display:inline;">'
            . '<input type="hidden" name="data_merger_download_csv" value="' . esc_attr($table) . '">'
            . '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('data_merger_download_csv_' . $table)) . '">'
            . '<button type="submit" class="button">' . esc_html($label) . '</button>'
            . '</form>';
    }

    public function handle_csv_download() {
        // Export Merged custom export with multipliers
        if (isset($_POST['data_merger_export_merged_csv'])) {
            if (!current_user_can('manage_options')) return;

            $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 1.10;
            $invoice_multiplier = isset($_POST['invoice_multiplier']) ? floatval($_POST['invoice_multiplier']) : 1.25;

            global $wpdb;

            // Query merged products and join with pup_updates and netsuite_product_dump for required columns
            $sql = "
                SELECT
                    n.internal_id AS netsuite_internal_id,
                    p.rrp,
                    p.standard_invoice_cost,
                    d.cost AS delivery_price,
                    p.status
                FROM {$wpdb->prefix}merged_products m
                JOIN {$wpdb->prefix}pup_updates p ON m.sku = p.sku
                JOIN {$wpdb->prefix}netsuite_product_dump n ON m.netsuite_id = n.internal_id
                LEFT JOIN {$wpdb->prefix}data_merger_delivery_costs d ON p.category = d.furniture_type AND d.delivery_option = 'Standard'
            ";
            $rows = $wpdb->get_results($sql, ARRAY_A);

            $filename = "netsuite-price-update-" . date("dmY") . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            $header = ['netsuite_internal_id', 'rrp', 'cost', 'online_price', 'status'];
            fputcsv($out, $header);

            foreach ($rows as $row) {
                $cost = is_numeric($row['standard_invoice_cost']) ? round($row['standard_invoice_cost'], 2) : '';
                $delivery_price = is_numeric($row['delivery_price']) ? $row['delivery_price'] : 0;
                $invoice_cost = is_numeric($row['standard_invoice_cost']) ? $row['standard_invoice_cost'] : 0;

                $delivery_price_increased = round($delivery_price * $delivery_multiplier, 2);
                $invoice_cost_increased = round($invoice_cost * $invoice_multiplier, 2);
                $online_price = round($delivery_price_increased + $invoice_cost_increased, 2);

                fputcsv($out, [
                    $row['netsuite_internal_id'],
                    is_numeric($row['rrp']) ? round($row['rrp'], 2) : '',
                    $cost,
                    $online_price,
                    $row['status']
                ]);
            }
            fclose($out);
            exit;
        }

        // Existing GET download handler for merged_products and data_merger_missing
        if (
            isset($_GET['data_merger_download_csv']) &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce($_GET['_wpnonce'], 'data_merger_download_csv_' . $_GET['data_merger_download_csv']) &&
            current_user_can('manage_options')
        ) {
            global $wpdb;
            $table = sanitize_text_field($_GET['data_merger_download_csv']);
            $table_name = $wpdb->prefix . $table;
            $rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            if (empty($rows)) {
                wp_die('No data found to export.');
            }
            $filename = $table . '_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }

        // Discontinued unmatched CSV download
        if (
            isset($_GET['data_merger_download_unmatched_csv']) &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce($_GET['_wpnonce'], 'data_merger_download_unmatched_csv') &&
            current_user_can('manage_options')
        ) {
            $unmatched = get_option('data_merger_discontinued_unmatched', []);
            $filename = 'unmatched_discontinued_skus_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['SKU']);
            foreach ($unmatched as $sku) {
                fputcsv($output, [$sku]);
            }
            fclose($output);
            exit;
        }

        // Discontinued main CSV download
        if (
            isset($_GET['data_merger_download_discontinued_csv']) &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce($_GET['_wpnonce'], 'data_merger_download_discontinued_csv') &&
            current_user_can('manage_options')
        ) {
            global $wpdb;
            $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}data_merger_discontinued_skus", ARRAY_A);
            if (empty($rows)) {
                wp_die('No data found to export.');
            }
            $filename = 'discontinued_skus_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
    }

    public function export_merged_page() {
        // Fix for missing method error!
        $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 1.10;
        $invoice_multiplier = isset($_POST['invoice_multiplier']) ? floatval($_POST['invoice_multiplier']) : 1.25;
        ?>
        <div class="wrap">
            <h1>Export Merged PUP/NetSuite Table</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="delivery_multiplier">Delivery Price Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="delivery_multiplier" id="delivery_multiplier" value="<?php echo esc_attr($delivery_multiplier); ?>" />
                            <span class="description">e.g. 1.10 for +10%</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_multiplier">Standard Invoice Cost Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="invoice_multiplier" id="invoice_multiplier" value="<?php echo esc_attr($invoice_multiplier); ?>" />
                            <span class="description">e.g. 1.25 for +25%</span>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" name="data_merger_export_merged_csv" class="button button-primary">Download CSV</button>
                </p>
            </form>
        </div>
        <?php
    }

    private function handle_csv_upload($table) {
        echo '<h2>Upload CSV</h2>';
        echo '<form method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv"/>
            <input type="submit" name="upload_csv" value="Upload CSV" class="button button-primary"/>
        </form>';

        if (isset($_POST['upload_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
            $result = $this->import_csv($_FILES['csv_file']['tmp_name'], $table);
            if ($result['success']) {
                echo '<div class="updated notice"><p>CSV Imported successfully.</p></div>';
            } else {
                echo '<div class="error notice"><p>Error importing CSV: ' . esc_html($result['error']) . '</p></div>';
            }
        }
    }

    // Superset import_csv: supports detailed mapping for pup_updates and netsuite_product_dump, generic for others
    private function import_csv($file, $table) {
        global $wpdb;
        $handle = fopen($file, "r");
        if (!$handle) return ['success' => false, 'error' => 'Cannot open CSV file.'];

        $columns = fgetcsv($handle);
        if (!$columns) return ['success' => false, 'error' => 'CSV has no header row.'];

        if (isset($columns[0])) {
            $columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $columns[0]);
        }

        $csv_to_db_map_pup = [
            'SKU' => 'sku',
            'Catalogue Code' => 'catalogue_code',
            'Category' => 'category',
            'Sub-Category' => 'sub_category',
            'Product Name' => 'product_name',
            'RRP' => 'rrp',
            'Standard Invoice Cost' => 'standard_invoice_cost',
            'Invoice Cost (4+ Items)' => 'invoice_cost_4plus',
            'Next Day Cost (Not available if blank)' => 'next_day_cost',
            '7-10 Day Install Cost' => 'install_cost_7_10',
            '3-5 Day Prebuild Cost' => 'prebuild_cost_3_5',
            'Next Day Prebuild Cost' => 'prebuild_cost_next_day',
            'NEW' => 'new_status',
            'Status' => 'status',
            'Promotion Available' => 'promotion_available',
            'Promo Code' => 'promo_code',
            'Promo Cost' => 'promo_cost',
            'Promo Minimum Order Spend' => 'promo_min_order_spend',
            'Promo Surcharge (Orders Under Minimum)' => 'promo_surcharge',
            'Promo Cost plus Surcharge' => 'promo_cost_plus_surcharge',
        ];

        $csv_to_db_map_netsuite = [
            'Internal ID' => 'internal_id',
            'Disabled In Magento' => 'disabled_in_magento',
            'Name' => 'name',
            'Display Name' => 'display_name',
            'Preferred Supplier' => 'preferred_supplier',
            'Online Price' => 'online_price',
            'Supplier Price' => 'supplier_price',
        ];

        if ($table === 'pup_updates') {
            $csv_to_db_map = $csv_to_db_map_pup;
            $primary_key = 'sku';
        } elseif ($table === 'netsuite_product_dump') {
            $csv_to_db_map = $csv_to_db_map_netsuite;
            $primary_key = 'internal_id';
        } else {
            $csv_to_db_map = [];
            $primary_key = false;
        }

        $row_count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            foreach ($columns as $i => $col) {
                $col = trim($col);
                if (isset($csv_to_db_map[$col])) {
                    $dbcol = $csv_to_db_map[$col];
                    $data[$dbcol] = isset($row[$i]) ? $row[$i] : '';
                } elseif (empty($csv_to_db_map)) {
                    // Generic: use lower-case column name
                    $dbcol = strtolower($col);
                    $data[$dbcol] = isset($row[$i]) ? $row[$i] : '';
                }
            }

            // Populate sku_suffix for netsuite_product_dump
            if ($table === 'netsuite_product_dump' && isset($data['name'])) {
                if (strpos($data['name'], ':') !== false) {
                    $parts = explode(':', $data['name'], 2);
                    $data['sku_suffix'] = trim($parts[1]);
                } else {
                    $data['sku_suffix'] = trim($data['name']);
                }
            }

            if ($primary_key && !empty($data[$primary_key])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}$table WHERE $primary_key = %s",
                    $data[$primary_key]
                ));
                if ($existing) {
                    $wpdb->update(
                        $wpdb->prefix . $table,
                        $data,
                        [$primary_key => $data[$primary_key]]
                    );
                } else {
                    $wpdb->insert($wpdb->prefix . $table, $data);
                }
                $row_count++;
            } elseif (!$primary_key && !empty($data)) {
                $wpdb->insert($wpdb->prefix . $table, $data);
                $row_count++;
            }
        }
        fclose($handle);
        if ($row_count == 0) {
            return ['success' => false, 'error' => 'No valid records found in CSV.'];
        }
        return ['success' => true];
    }

    private function render_table($table, $title = '', $desc = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $results = $wpdb->get_results("SELECT * FROM $table_name LIMIT 100", ARRAY_A);

        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        $last_import = '';
        if (in_array($table, ['pup_updates', 'netsuite_product_dump'])) {
            $last_import = $wpdb->get_var("SELECT MAX(updated_at) FROM $table_name");
        } elseif ($table === 'data_merger_missing') {
            $last_import = $wpdb->get_var("SELECT MAX(created_at) FROM $table_name");
        } elseif ($table === 'merged_products') {
            $last_import = $wpdb->get_var("SELECT MAX(id) FROM $table_name");
            if ($last_import) {
                $last_import = 'Last record ID: ' . $last_import;
            }
        }

        echo "<h1>$title</h1>";
        if ($desc) echo "<p>$desc</p>";
        echo '<ul style="margin-bottom:20px;">';
        echo "<li><strong>Total rows:</strong> " . intval($row_count) . "</li>";
        if ($last_import) {
            echo "<li><strong>Last import/update:</strong> $last_import</li>";
        }
        echo '</ul>';

        if (empty($results)) {
            echo "<p>No data found.</p>";
            return;
        }
        echo '<table class="widefat"><thead><tr>';
        foreach ($results[0] as $col => $val) {
            echo "<th>$col</th>";
        }
        echo '</tr></thead><tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            foreach ($row as $val) {
                echo '<td>' . esc_html($val) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function run_merge_script() {
        global $wpdb;
        $netsuite_table = $wpdb->prefix . 'netsuite_product_dump';
        $pup_table = $wpdb->prefix . 'pup_updates';
        $merged_table = $wpdb->prefix . 'merged_products';
        $missing_table = $wpdb->prefix . 'data_merger_missing';

        $wpdb->query("DELETE FROM $merged_table");
        $wpdb->query("DELETE FROM $missing_table");

        // Use sku_suffix for matching
        $rows = $wpdb->get_results("
            SELECT 
                n.internal_id AS netsuite_id,
                p.sku
            FROM $netsuite_table n
            JOIN $pup_table p
            ON n.sku_suffix = p.sku
        ", ARRAY_A);

        foreach ($rows as $row) {
            $wpdb->insert($merged_table, $row);
        }

        $pup_unmatched = $wpdb->get_results("
            SELECT p.sku, p.product_name
            FROM $pup_table p
            LEFT JOIN $netsuite_table n
            ON n.sku_suffix = p.sku
            WHERE n.id IS NULL
        ", ARRAY_A);

        foreach ($pup_unmatched as $row) {
            $wpdb->insert($missing_table, [
                'source' => 'pup',
                'key_field' => $row['sku'],
                'name_field' => $row['product_name']
            ]);
        }

        $netsuite_unmatched = $wpdb->get_results("
            SELECT n.internal_id, n.name
            FROM $netsuite_table n
            LEFT JOIN $pup_table p
            ON n.sku_suffix = p.sku
            WHERE p.id IS NULL
        ", ARRAY_A);

        foreach ($netsuite_unmatched as $row) {
            $wpdb->insert($missing_table, [
                'source' => 'netsuite',
                'key_field' => $row['internal_id'],
                'name_field' => $row['name']
            ]);
        }
    }

    // ----- Export Magento Specials Page -----
    public function export_magento_specials_page() {
        $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 1.10;
        $promo_invoice_multiplier = isset($_POST['promo_invoice_multiplier']) ? floatval($_POST['promo_invoice_multiplier']) : 1.25;
        ?>
        <div class="wrap">
            <h1>Export Magento Specials CSV</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="delivery_multiplier">Delivery Price Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="delivery_multiplier" id="delivery_multiplier" value="<?php echo esc_attr($delivery_multiplier); ?>" />
                            <span class="description">e.g. 1.10 for +10%</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="promo_invoice_multiplier">Promo Invoice Cost Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="promo_invoice_multiplier" id="promo_invoice_multiplier" value="<?php echo esc_attr($promo_invoice_multiplier); ?>" />
                            <span class="description">e.g. 1.25 for +25%</span>
                        </td>
                    </tr>
                </table>
                <?php wp_nonce_field('data_merger_export_magento_specials', '_wpnonce'); ?>
                <p><button type="submit" name="data_merger_export_magento_specials_submit" class="button button-primary">Download CSV for Magento</button></p>
            </form>
        </div>
        <?php
    }

    // Handles export for Magento Specials with dynamic multipliers (superset logic)
    public function maybe_export_magento_specials_csv() {
        if (
            isset($_POST['data_merger_export_magento_specials_submit']) &&
            wp_verify_nonce($_POST['_wpnonce'] ?? '', 'data_merger_export_magento_specials') &&
            current_user_can('manage_options')
        ) {
            global $wpdb;

            $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 1.10;
            $promo_invoice_multiplier = isset($_POST['promo_invoice_multiplier']) ? floatval($_POST['promo_invoice_multiplier']) : 1.25;

            $from_date = date('Y-m-d');
            $to_date   = date('Y-m-d', strtotime('+90 days'));
            $filename = 'magento_special_prices_' . date('Ymd_His') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['sku', 'special_price', 'special_price_from_date', 'special_price_to_date']);

            $results = $wpdb->get_results("
                SELECT child_sku AS sku, promo_cost, delivery_price
                FROM {$wpdb->prefix}child_sku_price
                WHERE has_promo = 1
            ", ARRAY_A);

            foreach ($results as $row) {
                $promo_cost = is_numeric($row['promo_cost']) ? $row['promo_cost'] : 0;
                $delivery = is_numeric($row['delivery_price']) ? $row['delivery_price'] : 0;

                // special_price = (promo_cost * promo_invoice_multiplier) + (delivery * delivery_multiplier)
                $special_price = round(($promo_cost * $promo_invoice_multiplier) + ($delivery * $delivery_multiplier), 2);

                fputcsv($output, [
                    $row['sku'],
                    $special_price,
                    $from_date,
                    $to_date
                ]);
            }
            fclose($output);
            exit;
        }
        // Also support legacy GET export for compatibility
        if (
            isset($_GET['data_merger_export_magento_specials']) &&
            wp_verify_nonce($_GET['_wpnonce'] ?? '', 'data_merger_export_magento_specials') &&
            current_user_can('manage_options')
        ) {
            global $wpdb;

            $from_date = date('Y-m-d');
            $to_date   = date('Y-m-d', strtotime('+90 days'));

            $filename = 'magento_special_prices_' . date('Ymd_His') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['sku', 'special_price', 'special_price_from_date', 'special_price_to_date']);

            $results = $wpdb->get_results("
                SELECT child_sku AS sku, promo_cost AS special_price
                FROM {$wpdb->prefix}child_sku_price
                WHERE has_promo = 1
            ", ARRAY_A);

            foreach ($results as $row) {
                fputcsv($output, [
                    $row['sku'],
                    $row['special_price'],
                    $from_date,
                    $to_date
                ]);
            }
            fclose($output);
            exit;
        }
    }

    // ---- Discontinued SKUs section ----
    public function discontinued_skus_page() {
        ?>
        <div class="wrap">
            <h1>Discontinued SKUs - Netsuite Disable Export</h1>
            <form method="post" enctype="multipart/form-data" style="margin-bottom:20px;">
                <input type="file" name="discontinued_csv" accept=".csv" required />
                <button type="submit" name="upload_discontinued_csv" class="button button-primary">Upload SKUs</button>
            </form>
            <form method="post" style="margin-bottom:20px;display:inline;">
                <button type="submit" name="run_merge_discontinued" class="button">Run Merge</button>
            </form>
            <form method="get" style="display:inline;">
                <input type="hidden" name="data_merger_download_discontinued_csv" value="1">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('data_merger_download_discontinued_csv')); ?>">
                <button type="submit" class="button">Download CSV</button>
            </form>
            <form method="get" style="display:inline;">
                <button type="submit" class="button">Refresh Table</button>
            </form>
        <?php

        // Upload CSV
        if (isset($_POST['upload_discontinued_csv']) && !empty($_FILES['discontinued_csv']['tmp_name'])) {
            $this->handle_discontinued_csv_upload($_FILES['discontinued_csv']['tmp_name']);
        }

        // Merge Button
        if (isset($_POST['run_merge_discontinued'])) {
            $this->run_merge_discontinued();
        }

        // Show summary and download link for unmatched SKUs from last merge, if any
        $unmatched = get_option('data_merger_discontinued_unmatched', []);
        if (!empty($unmatched)) {
            $total = count($unmatched);
            $preview = array_slice($unmatched, 0, 10);
            echo '<div class="notice notice-warning"><p><strong>Unmatched SKUs from last merge: ' . $total . '</strong>';
            if ($total > 0) {
                echo '<br>First 10 SKUs: ' . implode(', ', array_map('esc_html', $preview));
            }
            $dl_url = add_query_arg([
                'data_merger_download_unmatched_csv' => 1,
                '_wpnonce' => wp_create_nonce('data_merger_download_unmatched_csv')
            ]);
            echo '<br><a href="' . esc_url($dl_url) . '" class="button">Download Full Unmatched List (CSV)</a>';
            echo '</p></div>';
        }

        // View Table
        $this->render_discontinued_table();

        echo '</div>';
    }

    private function handle_discontinued_csv_upload($csv_path) {
        global $wpdb;
        $skus = [];
        $skus_in_file = [];
        $duplicates_in_file = [];
        $already_in_table = [];
        $blanks = 0;

        if (($handle = fopen($csv_path, "r")) !== false) {
            $header = fgetcsv($handle);
            $header = array_map(function($h) {
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
                return strtolower(trim($h));
            }, $header);
            $sku_col = array_search('sku', $header);
            if ($sku_col === false) {
                echo '<div class="error notice"><p>No column called SKU found in header row.</p></div>';
                return;
            }
            while (($row = fgetcsv($handle)) !== false) {
                $sku = trim($row[$sku_col]);
                if ($sku === '') {
                    $blanks++;
                    continue;
                }
                if (in_array($sku, $skus_in_file)) {
                    $duplicates_in_file[] = $sku;
                    continue;
                }
                $skus_in_file[] = $sku;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}data_merger_discontinued_skus WHERE discontinued_sku = %s", $sku
                ));
                if ($exists) {
                    $already_in_table[] = $sku;
                } else {
                    $skus[] = $sku;
                }
            }
            fclose($handle);
        }

        foreach ($skus as $sku) {
            $wpdb->insert("{$wpdb->prefix}data_merger_discontinued_skus", ['discontinued_sku' => $sku]);
        }

        $msg = '<div class="updated notice"><p>'
            . count($skus) . ' new SKUs added.<br>'
            . count($already_in_table) . ' SKUs already existed in the table.<br>'
            . count($duplicates_in_file) . ' duplicate SKUs in upload file were ignored.<br>'
            . ($blanks ? $blanks . ' blank/malformed lines skipped.<br>' : '')
            . '</p></div>';
        echo $msg;
    }

    private function run_merge_discontinued() {
        global $wpdb;
        $table = $wpdb->prefix . 'data_merger_discontinued_skus';
        $netsuite = $wpdb->prefix . 'netsuite_product_dump';

        $rows = $wpdb->get_results("SELECT id, discontinued_sku FROM $table WHERE internal_id IS NULL OR internal_id = ''");
        $unmatched = [];
        foreach ($rows as $row) {
            $sku = trim($row->discontinued_sku);
            $internal_id = $wpdb->get_var($wpdb->prepare(
                "SELECT internal_id FROM $netsuite WHERE TRIM(display_name) = %s OR TRIM(name) = %s LIMIT 1",
                $sku, $sku
            ));
            if ($internal_id) {
                $wpdb->update($table, ['internal_id' => $internal_id], ['id' => $row->id]);
            } else {
                $unmatched[] = $sku;
            }
        }

        update_option('data_merger_discontinued_unmatched', $unmatched);

        if (!empty($unmatched)) {
            $total = count($unmatched);
            $preview = array_slice($unmatched, 0, 10);
            echo '<div class="notice notice-warning"><p><strong>Unmatched SKUs: ' . $total . '</strong>';
            if ($total > 0) {
                echo '<br>First 10: ' . implode(', ', array_map('esc_html', $preview));
            }
            $dl_url = add_query_arg([
                'data_merger_download_unmatched_csv' => 1,
                '_wpnonce' => wp_create_nonce('data_merger_download_unmatched_csv')
            ]);
            echo '<br><a href="' . esc_url($dl_url) . '" class="button">Download Full Unmatched List (CSV)</a>';
            echo '</p></div>';
        } else {
            echo '<div class="updated notice"><p>Merge complete. All SKUs matched and updated.</p></div>';
        }
    }

    private function render_discontinued_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'data_merger_discontinued_skus';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 50", ARRAY_A);

        echo '<h2>Latest 50 Discontinued SKUs</h2>';
        if (empty($rows)) {
            echo "<p>No data found.</p>";
            return;
        }
        echo '<table class="widefat"><thead><tr><th>ID</th><th>SKU</th><th>Internal_ID</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['id']) . '</td>';
            echo '<td>' . esc_html($row['discontinued_sku']) . '</td>';
            echo '<td>' . esc_html($row['internal_id']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}