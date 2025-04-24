<?php
// Get current settings from wp_options
$settings = get_option('ocd_ai_settings', [
    'openai_api_key' => '',
    'last_import_log' => '',
    'last_model_generation_log' => '',
]);
?>

<div class="wrap">
    <h1>OCD AI Plugin Settings</h1>

    <?php settings_errors('ocd_ai_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('ocd_ai_save_settings', 'ocd_ai_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openai_api_key">OpenAI API Key</label>
                </th>
                <td>
                    <input type="text" name="openai_api_key" id="openai_api_key"
                           value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text" />
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>

    <hr>

    <h2>Regenerate Custom AI Models</h2>
    <p>This action will use the imported Knowledge Base and retrain AI models for all users with AI access.</p>

    <p><strong>Last import log:</strong><br>
        <code><?php echo esc_html($settings['last_import_log'] ?? 'No log yet'); ?></code>
    </p>

    <p><strong>Last manual regeneration:</strong><br>
        <code><?php echo esc_html($settings['last_model_generation_log'] ?? '—'); ?></code>
    </p>

    <form method="post"
          onsubmit="return confirm('Are you sure you want to regenerate all custom AI models? This process may take time and increase system load.');">
        <?php wp_nonce_field('ocd_ai_regenerate_models', 'ocd_ai_regenerate_nonce'); ?>
        <?php submit_button('Regenerate Models', 'delete'); ?>
    </form>

    <form method="post" onsubmit="return confirm('This will rebuild the entire AI input table from all form entries. Continue?');">
        <?php wp_nonce_field('ocd_ai_regenerate_inputs', 'ocd_ai_regenerate_inputs_nonce'); ?>
        <?php submit_button('Regenerate User Inputs', 'primary'); ?>
    </form>

</div>
