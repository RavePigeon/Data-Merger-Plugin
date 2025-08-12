<?php

class Data_Merger_Plugin {

    public static function activate() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

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
                id INT AUTO_INCREMENT PRIMARY KEY,
                discontinued_sku VARCHAR(128) NOT NULL UNIQUE,
                internal_id VARCHAR(32)
            ) $charset_collate;",
        ];

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_csv_download']);
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
            'Export Merged',
            'Export Merged',
            'manage_options',
            'data-merger-export',
            [$this, 'export_merged_page']
        );
        add_submenu_page(
            'data-merger-admin',
            'Discontinued SKUs',
            'Discontinued SKUs',
            'manage_options',
            'data-merger-discontinued',
            [$this, 'discontinued_skus_page']
        );
    }

    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>Data Merger Admin</h1>';
        echo '<p>Use the submenu to manage data tables and merge data.</p>';
        echo '</div>';
    }

    // --- ADMIN PAGE STUBS FOR OTHER MENUS (replace with your actual logic if you have it) ---

    public function view_pup_table() {
        echo '<div class="wrap"><h1>PUP Updates Table</h1><p>(Table output here.)</p></div>';
    }
    public function view_netsuite_table() {
        echo '<div class="wrap"><h1>NetSuite Product Dump Table</h1><p>(Table output here.)</p></div>';
    }
    public function view_merged_table() {
        echo '<div class="wrap"><h1>Merged Products Table</h1><p>(Table output here.)</p></div>';
    }
    public function view_missing_table() {
        echo '<div class="wrap"><h1>Unmatched Records Table</h1><p>(Table output here.)</p></div>';
    }
    public function export_merged_page() {
        echo '<div class="wrap"><h1>Export Merged</h1><p>(Export logic here.)</p></div>';
    }

    // --- END STUBS ---

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
                "SELECT internal_id FROM $netsuite WHERE TRIM(sku_suffix) = %s LIMIT 1",
                $sku
            ));
            if ($internal_id) {
                $wpdb->update($table, ['internal_id' => $internal_id], ['id' => $row->id]);
            } else {
                $unmatched[] = $sku;
            }
        }

        // Store unmatched SKUs in an option for display and download
        update_option('data_merger_discontinued_unmatched', $unmatched);

        // Output summary notice if running directly
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

    public function handle_csv_download() {
        // Download discontinued skus
        if (isset($_GET['data_merger_download_discontinued_csv'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'data_merger_download_discontinued_csv')) return;
            if (!current_user_can('manage_options')) return;

            global $wpdb;
            $table = $wpdb->prefix . 'data_merger_discontinued_skus';
            $rows = $wpdb->get_results("SELECT internal_id FROM $table WHERE internal_id IS NOT NULL AND internal_id != ''", ARRAY_A);

            // Clean output buffer to avoid admin HTML in CSV
            while (ob_get_level()) ob_end_clean();

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="obsolete_netsuite_disable_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Internal_ID', 'Disabled in Magento']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['internal_id'], 'Yes']);
            }
            fclose($out);
            exit;
        }

        // Download unmatched discontinued skus
        if (isset($_GET['data_merger_download_unmatched_csv'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'data_merger_download_unmatched_csv')) return;
            if (!current_user_can('manage_options')) return;

            $unmatched = get_option('data_merger_discontinued_unmatched', []);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="unmatched_discontinued_skus_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Unmatched SKU']);
            foreach ($unmatched as $sku) {
                fputcsv($out, [$sku]);
            }
            fclose($out);
            exit;
        }
    }

}