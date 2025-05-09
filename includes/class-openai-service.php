<?php

namespace Ocd\AiConsultant;

use OpenAI;
use Ocd\AiConsultant\OcdLog;

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

    public static function getActiveModelId(): ?string
    {
        global $wpdb;
        $table = "{$wpdb->prefix}ocd_ai_models";

        $model_id = $wpdb->get_var("SELECT model_id FROM $table WHERE model_type = 'active' AND status = 'ready' ORDER BY created_at DESC LIMIT 1");

        if (!$model_id) {
            $model_id = $wpdb->get_var("SELECT model_id FROM $table WHERE status = 'ready' ORDER BY created_at DESC LIMIT 1");
        }

        return $model_id ?: null;
    }

    public static function getTrainingModelId(): ?string
    {
        global $wpdb;
        return $wpdb->get_var("SELECT model_id FROM {$wpdb->prefix}ocd_ai_models WHERE status = 'training' ORDER BY updated_at DESC LIMIT 1");
    }


    public static function createModelRecord(string $job_id): void
    {
        global $wpdb;
        $settings = get_option('ocd_ai_settings', []);
        $import_log = $settings['last_import_log'] ?? '';

        $wpdb->insert("{$wpdb->prefix}ocd_ai_models", [
            'job_id' => $job_id,
            'model_id' => '',
            'import_log' => $import_log,
            'status' => 'training',
            'model_type' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        OcdLog::aiLog("Model record created", ['job_id' => $job_id, 'import_log' => $import_log]);
        Cron::scheduleIfTrainingExists();
    }

    public static function markModelAsActive(string $job_id, string $final_model_id): void
    {
        global $wpdb;

        $now = current_time('mysql');

        // Найти модель по job_id
        $model = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ocd_ai_models WHERE job_id = %s",
            $job_id
        ));

        if (!$model) {
            OcdLog::aiLog("markModelAsActive: model with job_id not found", $job_id);
            return;
        }

        // Обновляем модель на готовую, сохраняем job_id для консистентности
        $wpdb->update("{$wpdb->prefix}ocd_ai_models", [
            'model_id'   => $final_model_id,
            'job_id'     => $job_id,
            'status'     => 'ready',
            'model_type' => 'active',
            'updated_at' => $now,
        ], ['id' => $model->id]);

        OcdLog::aiLog("Model marked as active", $final_model_id);

        // Найти предыдущую active модель (если есть)
        $prev_active = $wpdb->get_row("SELECT id, model_id FROM {$wpdb->prefix}ocd_ai_models WHERE model_type = 'active' AND model_id != '" . esc_sql($final_model_id) . "' ORDER BY updated_at DESC LIMIT 1");

        if ($prev_active) {
            $wpdb->update("{$wpdb->prefix}ocd_ai_models", [
                'model_type' => 'backup',
                'updated_at' => $now,
            ], ['id' => $prev_active->id]);

            OcdLog::aiLog("Previous active model → backup", $prev_active->model_id);
        }

        // Архивируем все остальные
        $keep_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}ocd_ai_models WHERE model_type IN ('active', 'backup')");

        if (!empty($keep_ids)) {
            $in_clause = implode(',', array_map('intval', $keep_ids));
            $wpdb->query("UPDATE {$wpdb->prefix}ocd_ai_models SET model_type = 'archived', updated_at = '$now' WHERE id NOT IN ($in_clause) AND model_type IN ('active', 'backup')");

            OcdLog::aiLog("Other models archived", ['kept' => $keep_ids]);
        }
    }




    public static function deactivateOldActiveModels(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}ocd_ai_models WHERE model_type IN ('active','backup') ORDER BY updated_at DESC");
        if (count($rows) > 2) {
            $ids_to_archive = array_slice(array_column($rows, 'id'), 2);
            $in_clause = implode(',', array_map('intval', $ids_to_archive));
            $wpdb->query("UPDATE {$wpdb->prefix}ocd_ai_models SET model_type = 'archived' WHERE id IN ($in_clause)");
            OcdLog::aiLog("Archived old models", $ids_to_archive);
        }
    }

    public static function cleanupOldModels(): void
    {
        global $wpdb;
        $keep_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}ocd_ai_models WHERE model_type IN ('active','backup')");
        if (count($keep_ids) < 2) return;
        $in_clause = implode(',', array_map('intval', $keep_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}ocd_ai_models WHERE id NOT IN ($in_clause)");
        OcdLog::aiLog("Deleted archived models", $in_clause);
    }

    public static function sendMessage(int $user_id, string $prompt)
    {
        self::init();
        $model_id = self::getActiveModelId();
        if (!$model_id) return ['error' => 'Model not available'];

        $settings = get_option('ocd_ai_settings', []);
        $system = trim($settings['openai_system_content'] ?? '');

        $history = self::getUserMessageHistory($user_id);
        $messages = [];

        if ($system) {
            $messages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        $messages = array_merge($messages, $history, [
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

//    public static function aiLog(string $msg, $ctx = null): void
//    {
//        if ($ctx !== null) {
//            $msg .= ' | ' . substr(json_encode($ctx, JSON_UNESCAPED_UNICODE), 0, 8000);
//        }
//        error_log('[AI] ' . $msg);
//    }

    public static function trainModel(array $dataset): string|false
    {
        self::init();

        $jsonl = "";
        foreach ($dataset as $item) {
            $jsonl .= json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
        }
        OcdLog::aiLog("Dataset preview", array_slice($dataset, 0, 3));

        $tmp = tmpfile();
        fwrite($tmp, $jsonl);
        $tmp_path = stream_get_meta_data($tmp)['uri'];

        try {
            $upload = self::$client->files()->upload([
                'purpose' => 'fine-tune',
                'file' => fopen($tmp_path, 'r'),
            ]);
        } catch (\Throwable $e) {
            OcdLog::aiLog('Upload failed', $e->getMessage());
            return false;
        }

        try {
            $job = self::$client->fineTuning()->createJob([
                'training_file' => $upload->id,
                'model' => 'gpt-4o-mini-2024-07-18',
            ]);
        } catch (\Throwable $e) {
            OcdLog::aiLog('Fine-tune request failed', $e->getMessage());
            return false;
        }

        $job_id = $job->id ?? null;
        if (!$job_id) return false;

        self::createModelRecord($job_id);

        OcdLog::logModelTraining([
            'job_id' => $job_id,
            'status' => $job->status ?? 'unknown',
            'import_log' => json_encode(get_option('ocd_ai_settings')['last_import_log'] ?? []),
            'training_preview' => implode("\n", array_map(fn($item) => json_encode($item, JSON_UNESCAPED_UNICODE), array_slice($dataset, 0, 10))),

        ]);


        $settings = get_option('ocd_ai_settings', []);
        $settings['last_model_generation_log'] = "[{$job_id}] Global model: " . current_time('mysql');
        update_option('ocd_ai_settings', $settings);

        return $job_id;
    }

    public static function getModelStatus(string $job_id): array
    {
        self::init();

        try {
            $job = self::$client->fineTuning()->retrieveJob($job_id);

            $status = $job['status'] ?? 'unknown';
            $fine_tuned_model = $job['fine_tuned_model'] ?? null;
            $error = $job['error'] ?? null;

            OcdLog::aiLog('Status check', [
                'job_id' => $job_id,
                'status' => $status,
                'fine_tuned_model' => $fine_tuned_model,
                'error' => $error,
            ]);

            // Обновления статуса в БД, как раньше...

            $normalized_status = match ($status) {
                'succeeded' => 'ready',
                'running', 'queued' => 'training',
                default => $status,  // сохраняем оригинал
            };

            return [
                'status' => $normalized_status,
                'fine_tuned_model' => $fine_tuned_model,
                'error' => $error,
                'job_id' => $job_id,
                'raw_status' => $status, // если нужно
            ];

        } catch (\Throwable $e) {
            OcdLog::aiLog("Status check failed for {$job_id}", $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'job_id' => $job_id,
            ];
        }
    }


    public static function refreshPendingModels(): array
    {
        OcdLog::aiLog('[DEBUG] Refresh started');
        self::init();
        global $wpdb;

        $table = "{$wpdb->prefix}ocd_ai_models";
        $jobs = $wpdb->get_results("SELECT id, job_id FROM $table WHERE status = 'training'");

        $summary = [
            'total' => count($jobs),
            'ready' => 0,
            'running' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($jobs as $j) {
            $job_id = $j->job_id;
            $status_data = self::getModelStatus($job_id);

            OcdLog::aiLog('Status check', $status_data);

            $summary['details'][] = [
                'job_id' => $job_id,
                'status' => $status_data['status']
            ];

            switch ($status_data['raw_status']) {
                case 'succeeded':
                    self::markModelAsActive($job_id, $status_data['fine_tuned_model']);
                    $summary['ready']++;
                    break;

                case 'running':
                case 'pending':
                    $summary['running']++;
                    break;

                case 'failed':
                case 'cancelled':
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT status, model_type FROM $table WHERE job_id = %s",
                        $job_id
                    ), ARRAY_A);

                    $fields = [
                        'status'     => 'failed',
                        'model_type' => 'archived',
                        'error_log'  => $status_data['error'] ?? '',
                    ];

                    if (!$existing || $existing['status'] !== 'failed' || $existing['model_type'] !== 'archived') {
                        $fields['updated_at'] = current_time('mysql');
                    }

                    $wpdb->update($table, $fields, ['job_id' => $job_id]);
                    $summary['failed']++;
                    break;
            }
        }

        $ready_models = $wpdb->get_results("SELECT id, model_id FROM $table WHERE status = 'ready' ORDER BY created_at DESC");

        foreach ($ready_models as $index => $model) {
            $role = $index === 0 ? 'active' : ($index === 1 ? 'backup' : 'archived');

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT model_type FROM $table WHERE model_id = %s",
                $model->model_id
            ));

            $fields = ['model_type' => $role];
            if ($existing !== $role) {
                $fields['updated_at'] = current_time('mysql');
            }

            $wpdb->update($table, $fields, ['model_id' => $model->model_id]);
        }

        OcdLog::aiLog('Model refresh summary', $summary);

        $settings = get_option('ocd_ai_settings', []);
        $settings['last_model_refresh_log'] = json_encode([
            'last_action_date' => current_time('mysql'),
            'trigger'          => defined('DOING_CRON') && DOING_CRON ? 'cron' : 'manual',
        ], JSON_UNESCAPED_UNICODE);
        update_option('ocd_ai_settings', $settings);

        // ✅ Ключевой момент — перезапланировать крон, если остались training
        Cron::scheduleIfTrainingExists();

        return $summary;
    }


    public static function listAvailableModels(): array
    {
        self::init();

        try {
            $response = self::$client->models()->list();
            $all = $response->data ?? [];

            // Логируем полный список моделей (в том числе базовые)
            $all_ids = array_map(fn($m) => $m->id, $all);
            OcdLog::aiLog("All OpenAI models fetched", $all_ids);

            // Отбираем только кастомные (ft:)
            $fine_tuned = array_filter($all, fn($m) => str_starts_with($m->id, 'ft:'));

            $result = [];
            foreach ($fine_tuned as $model) {
                $result[] = [
                    'id' => $model->id,
                    'created' => property_exists($model, 'created') && $model->created
                        ? date('Y-m-d H:i:s', $model->created)
                        : '',
                    'object' => $model->object ?? '',
                    'root' => $model->root ?? '',
                    'parent' => $model->parent ?? '',
                    'permissions' => json_encode($model->permissions ?? []),
                ];
            }

            OcdLog::aiLog("Fine-tuned models", $result);

            return $result;
        } catch (\Throwable $e) {
            OcdLog::aiLog("Error in listAvailableModels", $e->getMessage());
            return [];
        }
    }

    public static function deleteOrphanedFtModels(): void
    {
        self::init();
        global $wpdb;

        $existing_ids = $wpdb->get_col("SELECT model_id FROM {$wpdb->prefix}ocd_ai_models");

        $all_models = self::$client->models()->list();
        $deleted = [];

        foreach ($all_models->data as $model) {
            if (str_starts_with($model->id, 'ft:') && !in_array($model->id, $existing_ids, true)) {
                try {
                    self::$client->models()->delete($model->id);
                    $deleted[] = $model->id;
                } catch (\Throwable $e) {
                    OcdLog::aiLog("Failed to delete orphaned model {$model->id}", $e->getMessage());
                }
            }
        }

        if (!empty($deleted)) {
            OcdLog::aiLog("Deleted orphaned FT models", $deleted);
        } else {
            OcdLog::aiLog("No orphaned FT models found");
        }
    }


}
