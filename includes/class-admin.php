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
        add_action('wp_ajax_ocd_ai_refresh_models', [self::class, 'ajax_refresh_models']); // тут self::class будет правильный!


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
        if (
            isset($_POST['ocd_ai_settings_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_settings_nonce'], 'ocd_ai_save_settings')
        ) {
            $settings = get_option('ocd_ai_settings', []);
            $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key'] ?? '');
            $settings['openai_system_content'] = sanitize_textarea_field($_POST['openai_system_content'] ?? '');
            update_option('ocd_ai_settings', $settings);

            add_settings_error('ocd_ai_messages', 'ocd_ai_saved', 'Settings saved.', 'updated');
        }

        if (
            isset($_POST['ocd_ai_regenerate_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_regenerate_nonce'], 'ocd_ai_regenerate_models')
        ) {
            $dataset = UserDataBuilder::buildKbDataset();
            $jsonl = "";
            foreach ($dataset as $item) {
                $jsonl .= json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
            }
            error_log("[AI-Debug] JSONL preview:\n" . $jsonl);

            add_settings_error('ocd_ai_messages', 'preview_only', 'JSONL dataset generated. Check debug.log.', 'updated');
//            return;

            $job_id = OpenAiService::trainModel($dataset);

            if ($job_id) {
                $settings = get_option('ocd_ai_settings', []);
                $settings['last_model_generation_log'] = "Training started: $job_id | " . current_time('mysql');
                update_option('ocd_ai_settings', $settings);
                add_settings_error('ocd_ai_messages', 'regenerated', 'Model regeneration started.', 'updated');
            } else {
                add_settings_error('ocd_ai_messages', 'regeneration_failed', 'Failed to start model training.', 'error');
            }
        }

        if (
            isset($_POST['ocd_ai_regenerate_inputs_nonce']) &&
            wp_verify_nonce($_POST['ocd_ai_regenerate_inputs_nonce'], 'ocd_ai_regenerate_inputs')
        ) {
            UserDataBuilder::rebuildAll();
            add_settings_error('ocd_ai_messages', 'inputs_synced', 'All user inputs were rebuilt.', 'updated');
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

    public static function ajax_refresh_models()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission'], 403);
        }
        check_ajax_referer('ocd_ai_refresh_models', 'nonce');

        $summary = OpenAiService::refreshPendingModels(['training', 'failed']);

        if (!is_array($summary)) {
            OpenAiService::aiLog('Model refresh returned unexpected result', $summary);
            wp_send_json_error(['message' => 'Model refresh failed or returned no data']);
            return;
        }

        OpenAiService::aiLog('Model refresh summary', $summary);

        wp_send_json_success([
            'message' => "Checked {$summary['total']} jobs"
                . " | ready: {$summary['ready']}"
                . " | running: {$summary['running']}"
                . " | failed: {$summary['failed']}"
        ]);
    }



}
