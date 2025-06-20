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
                    <input type="text" required  name="openai_api_key" id="openai_api_key"
                           value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text"/>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="openai_ft_system_content">Fine-tune system Prompt</label>
                </th>
                <td>
        <textarea name="openai_ft_system_content" required  id="openai_ft_system_content" class="large-text"
                  rows="4"><?php echo esc_textarea($settings['openai_ft_system_content'] ?? ''); ?></textarea>
                    <p class="description">Used during fine-tuning for training the model's behavior.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="openai_system_content">Messages System Prompt</label>
                </th>
                <td>
        <textarea name="openai_system_content" required id="openai_system_content" class="large-text"
                  rows="4"><?php echo esc_textarea($settings['openai_system_content'] ?? ''); ?></textarea>
                    <p class="description">Used during chat sessions with the model.</p>
                </td>
            </tr>

        </table>

        <?php submit_button('Save Settings'); ?>
    </form>

    <hr>

    <h2>Regenerate New AI Model</h2>
    <p>This action will use the imported Knowledge Base and retrain AI models for all users with AI access.</p>
    <p>Creation of a new model based on the imported data.</p>

    <p><strong>Last import log:</strong><br>
        <code><?php echo esc_html($settings['last_import_log'] ?? 'No log yet'); ?></code>
    </p>

    <p><strong>Last model generation:</strong><br>
        <code><?php echo esc_html($settings['last_model_generation_log'] ?? '—'); ?></code>
    </p>

    <form method="post"
          onsubmit="return confirm('Are you sure you want to regenerate all custom AI models? This process may take time and increase system load.');">
        <?php wp_nonce_field('ocd_ai_regenerate_models', 'ocd_ai_regenerate_nonce'); ?>
        <?php submit_button('Generate New Model', 'delete'); ?>
    </form>

    <hr>

    <h2>Check training models</h2>
    <p><strong>Last manual update of model statuses:</strong><br>
        <code><?php echo esc_html($settings['last_model_refresh_log'] ?? '—'); ?></code>
    </p>
    <button id="ocd-ai-refresh" class="button button-secondary">
        Refresh model statuses
    </button>
    <div id="ocd-ai-refresh-msg" style="display:none;margin-top:8px;"></div>
    <?php
    wp_enqueue_script(
        'ocd-ai-refresh',
        plugin_dir_url(dirname(__FILE__)) . 'assets/admin/js/ocd-ai-refresh.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'ocd-ai-list',
        plugin_dir_url(dirname(__FILE__)) . 'assets/admin/js/ocd-ai-list.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('ocd-ai-refresh', 'ocdAiRefresh', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ocd_ai_refresh_models'),
    ]);
    ?>

    <hr>

    <form id="ocd-ai-list-models-form" method="post" onsubmit="return false;">
        <?php wp_nonce_field('ocd_ai_chat_nonce', 'ocd_ai_chat_nonce'); ?>
        <button type="button" class="button secondary" id="ocd-ai-list-models-btn">List Available Models (Log)</button>
    </form>
    <div id="ocd-ai-list-models-result" style="margin-top:1em;"></div>

    <hr>

    <form method="post" onsubmit="return confirm('Delete all FT models not listed in your database?');">
        <?php wp_nonce_field('ocd_ai_delete_orphaned_models', 'ocd_ai_delete_orphaned_models_nonce'); ?>
        <?php submit_button('Clean Orphaned FT Models', 'secondary'); ?>
    </form>

</div>
