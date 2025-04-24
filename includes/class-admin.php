<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class Admin
{

    public static function init()
    {
        add_action('admin_menu', [self::class, 'register_admin_pages']);
        add_action('admin_init', [self::class, 'handle_settings_form']);
        add_action('admin_init', [self::class, 'handle_import_form']);


    }


    public static function register_admin_pages()
    {
        add_menu_page(
            'OCD AI Settings',
            'OCD AI',
            'manage_options',
            'ocd-ai-settings',
            [self::class, 'render_settings_page'],
            'dashicons-screenoptions',
            80
        );

        add_submenu_page(
            'ocd-ai-settings',
            'Import Excel',
            'Import',
            'manage_options',
            'ocd-ai-import',
            [self::class, 'render_import_page']
        );

        add_submenu_page(
            'ocd-ai-settings',
            'View Data',
            'View Data',
            'manage_options',
            'ocd-ai-view',
            [self::class, 'render_view_page']
        );
    }


    public static function handle_settings_form()
    {
        // Save settings
        if (
            isset($_POST['ocd_ai_settings_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_settings_nonce'], 'ocd_ai_save_settings')
        ) {
            $settings = get_option('ocd_ai_settings', []);
            $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key'] ?? '');
            update_option('ocd_ai_settings', $settings);

            add_settings_error('ocd_ai_messages', 'ocd_ai_saved', 'Settings saved.', 'updated');
        }

        // Trigger model regeneration
        if (
            isset($_POST['ocd_ai_regenerate_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_regenerate_nonce'], 'ocd_ai_regenerate_models')
        ) {
            $settings = get_option('ocd_ai_settings', []);
            $settings['last_model_generation_log'] = current_time('mysql');
            update_option('ocd_ai_settings', $settings);

            add_settings_error('ocd_ai_messages', 'ocd_ai_regenerated', 'Model regeneration started (simulation).', 'updated');
        }

        if (isset($_POST['ocd_ai_regenerate_inputs_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_regenerate_inputs_nonce'], 'ocd_ai_regenerate_inputs')) {

            \Ocd\AiConsultant\UserDataBuilder::rebuildAll();
            add_settings_error('ocd_ai_messages', 'inputs_synced', 'All user inputs were rebuilt.', 'updated');
        }

        if (
            isset($_POST['generate_user_model']) &&
            isset($_POST['ocd_ai_generate_model_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_generate_model_nonce'], 'ocd_ai_generate_model_user')
        ) {
            $user_id = (int) $_POST['ai_user_id'];

            try {
                \Ocd\AiConsultant\UserDataBuilder::syncUserData($user_id);
                $dataset = \Ocd\AiConsultant\JsonDatasetBuilder::buildUserDataset($user_id);
                $model_id = \Ocd\AiConsultant\OpenAiService::trainModel($user_id, $dataset);

                if ($model_id) {
                    add_settings_error('ocd_ai_messages', 'model_generated', "Custom model created: $model_id", 'updated');
                } else {
                    add_settings_error('ocd_ai_messages', 'model_failed', 'Model training failed or returned empty ID.', 'error');
                }
            } catch (\Throwable $e) {
                add_settings_error('ocd_ai_messages', 'model_exception', 'Error: ' . $e->getMessage(), 'error');
            }
        }

    }


    public static function render_settings_page()
    {
        include plugin_dir_path(__FILE__) . '/../templates/page-settings.php';
    }

    public static function handle_import_form()
    {
        if (
            isset($_POST['ocd_ai_import_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_import_nonce'], 'ocd_ai_import_excel') &&
            current_user_can('manage_options') &&
            isset($_FILES['excel_file']) &&
            !empty($_FILES['excel_file']['tmp_name'])
        ) {
            $file = $_FILES['excel_file'];
            $result = \Ocd\AiConsultant\ExcelImporter::importFromUpload($file);

            if ($result['success']) {
                add_settings_error('ocd_ai_messages', 'ocd_ai_import_success', 'Import completed successfully.', 'updated');
            } else {
                add_settings_error('ocd_ai_messages', 'ocd_ai_import_error', $result['message'], 'error');
            }
        }
    }

    public static function render_import_page()
    {
        include plugin_dir_path(__FILE__) . '/../templates/page-import.php';
    }


    public static function render_view_page()
    {
        $tables = [
            'ocd_ai_knowledge_base' => 'KB Import Data',
            'ocd_ai_model_log' => 'Model Generation Log',
            // Add more here as needed
        ];

        $selected = sanitize_text_field($_GET['table'] ?? 'ocd_ai_knowledge_base');

        include plugin_dir_path(__FILE__) . '/../templates/page-view.php';
    }
}
