<?php

namespace Ocd\AiConsultant;
use Ocd\AiConsultant\OcdLog;


defined('ABSPATH') || exit;

class Ajax
{
    public static function register()
    {
        add_action('wp_ajax_ocd_ai_check_model_status', [self::class, 'checkModelStatus']);
        add_action('wp_ajax_ocd_ai_chat_send_message', [self::class, 'sendMessage']);
        add_action('wp_ajax_ocd_ai_chat_load_history', [self::class, 'loadChatHistory']);
        add_action('wp_ajax_ocd_ai_refresh_models', [self::class, 'refreshModels']);
    }

    public static function checkModelStatus()
    {
        check_ajax_referer('ocd_ai_chat_nonce', 'nonce');

        $model_id = sanitize_text_field($_POST['model_id'] ?? '');

        if (!$model_id) {
            wp_send_json_error(['message' => 'Model ID is missing']);
        }

        try {
            $status = OpenAiService::getModelStatus($model_id);

            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT updated_at, model_type FROM {$wpdb->prefix}ocd_ai_models WHERE model_id = %s",
                $model_id
            ));

            OcdLog::aiLog('AJAX model check', [
                'model_id' => $model_id,
                'status' => $status,
                'model_type' => $row->model_type ?? '—',
                'last_update' => $row->updated_at ?? '—',
            ]);

            wp_send_json_success([
                'status' => $status,
                'last_trained' => $row->updated_at ?? '',
                'model_type' => $row->model_type ?? 'unknown',
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function sendMessage()
    {
        check_ajax_referer('ocd_ai_chat_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');

        if (!$prompt) {
            wp_send_json_error(['message' => 'Empty message']);
        }

        OcdLog::aiLog('[AJAX] sendMessage', [
            'user_id' => get_current_user_id(),
            'prompt' => $_POST['prompt'] ?? '(missing)',
        ]);

        try {
            $result = OpenAiService::sendMessage($user_id, $prompt);

            if (isset($result['error'])) {
                wp_send_json_error(['message' => $result['error']]);
            }

            wp_send_json_success([
                'reply' => $result['response'],
                'debug' => $result['debug'] ?? null,

            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function loadChatHistory()
    {
        check_ajax_referer('ocd_ai_chat_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_chat';

        // Берем последние 30 сообщений и сортируем по возрастанию ID
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT role, message, created_at 
         FROM (
             SELECT * FROM $table 
             WHERE user_id = %d 
             ORDER BY id DESC 
             LIMIT 30
         ) AS recent 
         ORDER BY id ASC",
            $user_id
        ));

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'role' => $row->role,
                'message' => $row->message,
                'created_at' => $row->created_at,
            ];
        }

        wp_send_json_success(['history' => $history]);
    }


    public static function refreshModels()
    {
        check_ajax_referer('ocd_ai_chat_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        try {
            $summary = OpenAiService::refreshPendingModels(['training', 'failed']);
            OcdLog::aiLog('Manual refreshModels triggered via AJAX', $summary);

            wp_send_json_success(['message' => 'Model statuses refreshed']);
        } catch (\Throwable $e) {
            OcdLog::aiLog('refreshModels error', $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
