<?php
/**
 * Importer for PUP Child SKU Price CSV into mi4w_child_sku_price table.
 * 
 * - Maps columns from CSV to appropriate fields.
 * - Handles CSV upload and import via WP admin.
 * - Usage: Include or require this file in your plugin, and call pup_child_sku_price_importer_admin_menu() in admin_menu.
 */

add_action('admin_menu', 'pup_child_sku_price_importer_admin_menu');

function pup_child_sku_price_importer_admin_menu() {
    add_submenu_page(
        'data-merger-admin',
        'Import PUP Child SKU Price',
        'Import PUP Price',
        'manage_options',
        'pup-child-sku-price-importer',
        'pup_child_sku_price_importer_page'
    );
}

function pup_child_sku_price_importer_page() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $pup_table = $wpdb->prefix . 'child_sku_price';

    // Handle upload and import
    if (isset($_POST['pup_import_submit']) && check_admin_referer('pup_import_csv', 'pup_import_nonce')) {
        if (!empty($_FILES['pup_csv']['tmp_name'])) {
            $file = $_FILES['pup_csv']['tmp_name'];
            $handle = fopen($file, 'r');
            if ($handle !== false) {
                // Optionally: truncate table before import
                $wpdb->query("TRUNCATE TABLE $pup_table");

                $header = fgetcsv($handle, 0, ',');
                $header_map = array_flip($header);

                $row_count = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    // Map columns by header
                    $data = [];
                    foreach ($header_map as $col => $i) {
                        $data[$col] = isset($row[$i]) ? $row[$i] : '';
                    }
                    $insert = [
                        'parent_sku'           => isset($data['parent_sku']) ? $data['parent_sku'] : '',
                        'child_sku'            => isset($data['child_sku']) ? $data['child_sku'] : '',
                        'delivery_option'      => isset($data['delivery_option']) ? $data['delivery_option'] : '',
                        'delivery_price'       => isset($data['delivery_price']) ? $data['delivery_price'] : null,
                        'rrp'                  => isset($data['rrp']) ? $data['rrp'] : null,
                        'standard_invoice_cost'=> isset($data['standard_invoice_cost']) ? $data['standard_invoice_cost'] : null,
                        'new_status'           => isset($data['new_status']) ? $data['new_status'] : '',
                        'status'               => isset($data['status']) ? $data['status'] : '',
                    ];
                    $wpdb->insert($pup_table, $insert);
                    $row_count++;
                }
                fclose($handle);
                echo '<div class="updated"><p>Imported ' . esc_html($row_count) . ' rows successfully into <code>' . esc_html($pup_table) . '</code>.</p></div>';
            } else {
                echo '<div class="error"><p>Could not open the uploaded CSV file.</p></div>';
            }
        } else {
            echo '<div class="error"><p>No CSV file uploaded.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h2>Import PUP Child SKU Price CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('pup_import_csv', 'pup_import_nonce'); ?>
            <input type="file" name="pup_csv" accept=".csv" required>
            <input type="submit" name="pup_import_submit" class="button button-primary" value="Import CSV">
        </form>
        <p>Upload a CSV exported from your PUP system. The importer will populate the <code>child_sku_price</code> table for use in merging.</p>
    </div>
    <?php
}
?>