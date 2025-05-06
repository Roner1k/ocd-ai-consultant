<?php

namespace Ocd\AiConsultant;

use OpenAI;

defined('ABSPATH') || exit;

class OpenAiService
{
    private static $client;

    public static function init()
    {
        if (self::$client) return;
        $settings = get_option('ocd_ai_settings', []);
        $api_key = $settings['openai_api_key'] ?? '';

        if (!$api_key) {
            throw new \Exception("OpenAI API key not set.");
        }

        self::$client = OpenAI::client($api_key);
    }

    public static function getActiveModelId(): ?string {
        global $wpdb;
        return $wpdb->get_var("SELECT model_id FROM {$wpdb->prefix}ocd_ai_models WHERE status = 'active' ORDER BY updated_at DESC LIMIT 1");
    }

    public static function getTrainingModelId(): ?string {
        global $wpdb;
        return $wpdb->get_var("SELECT model_id FROM {$wpdb->prefix}ocd_ai_models WHERE status = 'training' ORDER BY updated_at DESC LIMIT 1");
    }

    public static function createModelRecord(string $job_id): void {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}ocd_ai_models", [
            'model_id' => $job_id,
            'status' => 'training',
            'training_scheduled_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    public static function markModelAsActive(string $job_id, string $final_model_id): void {
        global $wpdb;

        $wpdb->update("{$wpdb->prefix}ocd_ai_models", [
            'status' => 'active',
            'model_id' => $final_model_id,
            'last_trained_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['model_id' => $job_id]);
    }

    public static function deactivateOldActiveModels(): void {
        global $wpdb;
        $active_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}ocd_ai_models WHERE status = 'active' ORDER BY updated_at DESC");
        if (count($active_ids) > 2) {
            $ids_to_archive = array_slice($active_ids, 2);
            $in_clause = implode(',', array_map('intval', $ids_to_archive));
            $wpdb->query("UPDATE {$wpdb->prefix}ocd_ai_models SET status = 'archived' WHERE id IN ($in_clause)");
        }
    }

    public static function cleanupOldModels(): void {
        global $wpdb;
        $table = "{$wpdb->prefix}ocd_ai_models";
        $ids_to_keep = $wpdb->get_col("SELECT id FROM $table ORDER BY updated_at DESC LIMIT 2");
        if (count($ids_to_keep) < 2) return;
        $in_clause = implode(',', array_map('intval', $ids_to_keep));
        $wpdb->query("DELETE FROM $table WHERE id NOT IN ($in_clause)");
    }

    public static function sendMessage(int $user_id, string $prompt)
    {
        self::init();
        $model_id = self::getActiveModelId();
        if (!$model_id) return ['error' => 'Model not available'];

        $history = self::getUserMessageHistory($user_id);
        $messages = array_merge($history, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        $response = self::$client->chat()->create([
            'model' => $model_id,
            'messages' => $messages,
        ]);

        $reply = $response->choices[0]->message->content;

        self::saveMessageToDb($user_id, 'user', $prompt);
        self::saveMessageToDb($user_id, 'assistant', $reply);

        return ['response' => $reply];
    }

    private static function getUserMessageHistory($user_id, $limit = 10)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_chat';

        $rows = $wpdb->get_results($wpdb->prepare("SELECT role, message FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit));
        $messages = [];
        foreach (array_reverse($rows) as $row) {
            $messages[] = [
                'role' => $row->role,
                'content' => $row->message,
            ];
        }
        return $messages;
    }

    private static function saveMessageToDb($user_id, $role, $message)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ocd_ai_user_chat', [
            'user_id' => $user_id,
            'role' => $role,
            'message' => $message,
            'created_at' => current_time('mysql'),
        ]);
    }

    public  static function aiLog(string $msg, $ctx = null): void {
        if ($ctx !== null) {
            $msg .= ' | ' . substr(json_encode($ctx, JSON_UNESCAPED_UNICODE), 0, 8000);
        }
        error_log('[AI] ' . $msg);
    }

    public static function trainModel(array $dataset): string|false {
        self::init();

        $jsonl = "";
        foreach ($dataset as $item) {
            $jsonl .= json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
        }
        self::aiLog("Dataset preview", array_slice($dataset, 0, 3));

        $tmp = tmpfile();
        fwrite($tmp, $jsonl);
        $tmp_path = stream_get_meta_data($tmp)['uri'];

        try {
            $upload = self::$client->files()->upload([
                'purpose' => 'fine-tune',
                'file' => fopen($tmp_path, 'r'),
            ]);
        } catch (\Throwable $e) {
            self::aiLog('Upload failed', $e->getMessage());
            return false;
        }

        try {
            $job = self::$client->fineTuning()->createJob([
                'training_file' => $upload->id,
                'model' => 'gpt-4o-mini-2024-07-18',
            ]);
        } catch (\Throwable $e) {
            self::aiLog('Fine-tune request failed', $e->getMessage());
            return false;
        }

        $job_id = $job->id ?? null;
        if (!$job_id) return false;

        self::createModelRecord($job_id);

        $settings = get_option('ocd_ai_settings', []);
        $settings['last_model_generation_log'] = "[{$job_id}] Global model: " . current_time('mysql');
        update_option('ocd_ai_settings', $settings);

        return $job_id;
    }

    public static function getModelStatus(string $job_id): string {
        self::init();

        try {
            $job = self::$client->fineTuning()->retrieveJob($job_id);
            $status = $job->status;
            $fine_tuned_model = $job->fineTunedModel;

            // Всегда логируем краткую информацию о текущем статусе
            self::aiLog("Status check", [
                'job_id'  => $job_id,
                'status'  => $status,
                'fine_tuned_model' => $fine_tuned_model ?? null,
                'error'   => $job->error ?? null,
            ]);

            // Обработка успешного завершения
            if ($status === 'succeeded' && $fine_tuned_model) {
                self::markModelAsActive($job_id, $fine_tuned_model);
            }
            // Обработка неудачи или отмены
            elseif (in_array($status, ['failed', 'cancelled'], true)) {
                global $wpdb;

                $error_text = is_object($job->error)
                    ? json_encode($job->error, JSON_UNESCAPED_UNICODE)
                    : (string) $job->error;

                $wpdb->update("{$wpdb->prefix}ocd_ai_models", [
                    'status'     => $status,
                    'error_log'  => $error_text,
                    'updated_at' => current_time('mysql')
                ], ['model_id' => $job_id]);
            }

            return $status;
        } catch (\Throwable $e) {
            self::aiLog("Status check failed for {$job_id}", $e->getMessage());
            return 'error';
        }
    }



    public static function refreshPendingModels(): array {
        self::init();
        global $wpdb;

        $table = "{$wpdb->prefix}ocd_ai_models";
        $jobs = $wpdb->get_results("SELECT model_id FROM $table WHERE status IN ('training')");

        $summary = [
            'total'  => count($jobs),
            'ready'  => 0,
            'running'=> 0,
            'failed' => 0,
        ];

        foreach ($jobs as $j) {
            $status = self::getModelStatus($j->model_id);
            if ($status === 'ready')    $summary['ready']++;
            elseif ($status === 'training') $summary['running']++;
            elseif (in_array($status, ['failed', 'cancelled'])) $summary['failed']++;
        }

        self::aiLog('Model refresh summary', $summary);
        return $summary;
    }

}
