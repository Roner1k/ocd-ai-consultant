<?php
// Get plugin settings
$settings = get_option('ocd_ai_settings', []);
?>

<div class="wrap">
    <h1>Import Knowledge Base</h1>

    <?php settings_errors('ocd_ai_messages'); ?>

    <p><strong>Last import log:</strong><br>
        <code><?php echo esc_html($settings['last_import_log'] ?? 'No log yet'); ?></code>
    </p>

    <form method="post" enctype="multipart/form-data"
          onsubmit="return confirm('Are you sure you want to import this file? This will erase the previous knowledge base.');">
        <?php wp_nonce_field('ocd_ai_import_excel', 'ocd_ai_import_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="excel_file">Select Excel file (.xlsx)</label>
                </th>
                <td>
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required />
                </td>
            </tr>
        </table>

        <?php submit_button('Import Excel', 'primary'); ?>
    </form>
</div>
