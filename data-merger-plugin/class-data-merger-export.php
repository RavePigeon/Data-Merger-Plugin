<?php
class Data_Merger_Export {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_csv_download']);
    }

    public function register_admin_menu() {
        add_submenu_page(
            'data-merger-admin',
            'Export Merged PUP/NetSuite',
            'Export Merged',
            'manage_options',
            'data-merger-export',
            [$this, 'export_page']
        );
    }

    public function export_page() {
        // Defaults
        $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 0.85;
        $invoice_multiplier = isset($_POST['invoice_multiplier']) ? floatval($_POST['invoice_multiplier']) : 0.75;
        ?>
        <div class="wrap">
            <h1>Export Merged PUP/NetSuite Table</h1>
		<p>This formula is derived from the idea that the cost price is a % of the selling price. If you want 25% profit margin on the selling price, it means your cost price must be 75% of your selling price. For this you would enter 0.75 as the Multiplier.</p>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="delivery_multiplier">Delivery Price Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="delivery_multiplier" id="delivery_multiplier" value="<?php echo esc_attr($delivery_multiplier); ?>" />
                            <span class="description">e.g. 0.85 for +15% profit margin</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_multiplier">Standard Invoice Cost Multiplier</label></th>
                        <td>
                            <input type="number" step="0.01" min="1" name="invoice_multiplier" id="invoice_multiplier" value="<?php echo esc_attr($invoice_multiplier); ?>" />
                            <span class="description">e.g. 0.75 for +25% profit margin</span>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" name="data_merger_download_csv" class="button button-primary">Download Magento Special Price CSV</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_csv_download() {
        if (!isset($_POST['data_merger_download_csv'])) return;

        // Get multipliers from POST or defaults
        $delivery_multiplier = isset($_POST['delivery_multiplier']) ? floatval($_POST['delivery_multiplier']) : 0.85;
        $invoice_multiplier = isset($_POST['invoice_multiplier']) ? floatval($_POST['invoice_multiplier']) : 0.75;

        global $wpdb;
        $table = $wpdb->prefix . 'merged_pup_netsuite';

        // Query all rows
        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

        // Filename with current date
        $filename = "netsuite-price-update-" . date("dmY") . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Header
        $header = ['netsuite_internal_id', 'rrp', 'cost', 'online_price', 'status'];
        fputcsv($out, $header);

        foreach ($rows as $row) {
            // Pull columns
            $netsuite_id = isset($row['netsuite_internal_id']) ? $row['netsuite_internal_id'] : '';
            $rrp = is_numeric($row['rrp']) ? round($row['rrp'], 2) : '';
            $cost = is_numeric($row['standard_invoice_cost']) ? round($row['standard_invoice_cost'], 2) : '';
            $status = isset($row['status']) ? $row['status'] : '';

            // Calculate online_price
            $delivery_price = is_numeric($row['delivery_price']) ? $row['delivery_price'] : 0;
            $invoice_cost = is_numeric($row['standard_invoice_cost']) ? $row['standard_invoice_cost'] : 0;

            $delivery_price_increased = round($delivery_price / $delivery_multiplier, 2);
            $invoice_cost_increased = round($invoice_cost / $invoice_multiplier, 2);
            $online_price = round($delivery_price_increased + $invoice_cost_increased, 2);

            fputcsv($out, [
                $netsuite_id,
                $rrp,
                $cost,
                $online_price,
                $status
            ]);
        }
        fclose($out);
        exit;
    }
}

// Instantiate the export class if in admin
if (is_admin()) {
    new Data_Merger_Export();
}