<?php

namespace Ocd\AiConsultant;

use OpenAI;

defined('ABSPATH') || exit;

class OpenAiService
{
    private static $client;

    /**
     * Initializes OpenAI client with saved API key
     */
    public static function init()
    {
        if (self::$client) return; // already initialized

        $settings = get_option('ocd_ai_settings', []);
        $api_key = $settings['openai_api_key'] ?? '';

        if (!$api_key) {
            throw new \Exception("OpenAI API key not set.");
        }

        self::$client = OpenAI::client($api_key);
    }

    /**
     * Returns model ID for user (from DB)
     */
    public static function getModelId($user_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT model_id FROM {$wpdb->prefix}ocd_ai_user_models
            WHERE user_id = %d AND status = 'ready'
        ", $user_id));
    }

    /**
     * Sends a message to GPT (chat) with previous history
     */
    public static function sendMessage($user_id, $prompt)
    {
        self::init();
        $model_id = self::getModelId($user_id);

        if (!$model_id) {
            return ['error' => 'Model not available'];
        }

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

    /**
     * Gets recent message history
     */
    private static function getUserMessageHistory($user_id, $limit = 10)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_chat';

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT role, message FROM $table
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ", $user_id, $limit));

        $messages = [];
        foreach (array_reverse($rows) as $row) {
            $messages[] = [
                'role' => $row->role,
                'content' => $row->message,
            ];
        }

        return $messages;
    }

    /**
     * Saves message in chat history
     */
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

    public static function trainModel($user_id, array $dataset)
    {
        self::init();

        // 1. Превращаем массив в .jsonl-строку
        $jsonl = '';
        foreach ($dataset as $item) {
            $jsonl .= json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
        }

        // 2. Сохраняем во временный файл
        $tmp = tmpfile();
        fwrite($tmp, $jsonl);
        $meta = stream_get_meta_data($tmp);
        $tmp_path = $meta['uri'];

        error_log("Generated .jsonl for user #$user_id:\n" . $jsonl);

        // 3. Загружаем файл на OpenAI
        try {
            $upload = self::$client->files()->upload([
                'purpose' => 'fine-tune',
                'file' => fopen($tmp_path, 'r'),
            ]);
        } catch (\Exception $e) {
            error_log("Upload failed: " . $e->getMessage());
            return false;
        }

        $file_id = $upload->id;
        error_log("Uploaded training file ID: $file_id");

        // 4. Запускаем обучение
        try {
            $fineTune = self::$client->fineTuning()->create([
                'training_file' => $file_id,
                'model' => 'gpt-3.5-turbo',
            ]);
        } catch (\Exception $e) {
            error_log("Fine-tuning failed: " . $e->getMessage());
            return false;
        }

        $model_id = $fineTune->fine_tuned_model ?? null;
        if (!$model_id) {
            error_log("No model ID returned.");
            return false;
        }

        error_log("Fine-tuned model ID: $model_id");

        // 5. Сохраняем в таблицу
        global $wpdb;
        $wpdb->replace($wpdb->prefix . 'ocd_ai_user_models', [
            'user_id' => $user_id,
            'model_id' => $model_id,
            'status' => 'pending',
            'last_trained_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        // 6. Лог в wp_options
        $log = "Model [$model_id] created for user #$user_id at " . current_time('mysql');
        $settings = get_option('ocd_ai_settings', []);
        $settings['last_model_generation_log'] = $log;
        update_option('ocd_ai_settings', $settings);

        return $model_id;
    }

    public static function getModelStatus($model_id)
    {
        self::init();

        try {
            $result = self::$client->fineTuning()->retrieve($model_id);

            $status = $result->status ?? 'unknown';

            // Опционально: обновим статус в базе
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ocd_ai_user_models',
                [
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ],
                ['model_id' => $model_id]
            );

            return $status;
        } catch (\Exception $e) {
            error_log("Model status check failed for $model_id: " . $e->getMessage());
            return 'error';
        }
    }


}
