<?php
// Admin view to add/edit/delete delivery costs for furniture/delivery option combinations

function data_merger_delivery_costs_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'data_merger_delivery_costs';

    // Handle add/update
    if (isset($_POST['save_delivery_cost'])) {
        $furniture_type = sanitize_text_field($_POST['furniture_type']);
        $delivery_option = sanitize_text_field($_POST['delivery_option']);
        $cost = floatval($_POST['cost']);

        // Insert or update
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE furniture_type = %s AND delivery_option = %s",
            $furniture_type, $delivery_option
        ));
        if ($existing) {
            $wpdb->update($table_name,
                ['cost' => $cost],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert($table_name, [
                'furniture_type' => $furniture_type,
                'delivery_option' => $delivery_option,
                'cost' => $cost
            ]);
        }
        echo '<div class="updated notice"><p>Saved!</p></div>';
    }

    // Handle delete
    if (isset($_POST['delete_delivery_cost']) && intval($_POST['delete_id'])) {
        $wpdb->delete($table_name, ['id' => intval($_POST['delete_id'])]);
        echo '<div class="updated notice"><p>Deleted!</p></div>';
    }

    // Get all rows
    $rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY furniture_type, delivery_option", ARRAY_A);

    ?>
    <div class="wrap">
        <h1>Edit Delivery Costs</h1>
        <form method="post" style="margin-bottom:30px;">
            <table>
                <tr>
                    <td><input type="text" name="furniture_type" placeholder="Furniture Type" required></td>
                    <td><input type="text" name="delivery_option" placeholder="Delivery Option" required></td>
                    <td><input type="number" step="0.01" min="0" name="cost" placeholder="Cost (£)" required></td>
                    <td><button type="submit" name="save_delivery_cost" class="button button-primary">Add/Update</button></td>
                </tr>
            </table>
        </form>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Furniture Type</th>
                    <th>Delivery Option</th>
                    <th>Cost (£)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['furniture_type']); ?></td>
                    <td><?php echo esc_html($row['delivery_option']); ?></td>
                    <td><?php echo number_format($row['cost'], 2); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?php echo intval($row['id']); ?>">
                            <button type="submit" name="delete_delivery_cost" class="button button-secondary" onclick="return confirm('Delete this row?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}