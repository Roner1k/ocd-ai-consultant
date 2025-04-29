<<<<<<< Updated upstream
=======
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

    /**
     * Пишет строку в wp-debug.log с пометкой [AI].
     */

    private static function aiLog( string $msg, $ctx = null ): void {
        if ( $ctx !== null ) {
            $msg .= ' | ' . substr( json_encode( $ctx, JSON_UNESCAPED_UNICODE ), 0, 8000 );
        }
        error_log( '[AI] ' . $msg );
    }

    public static function trainModel( int $user_id, array $dataset ) {
        self::init();

        // 1. dataset → .jsonl
        $jsonl = '';
        foreach ( $dataset as $i => $item ) {
            $jsonl .= json_encode( $item, JSON_UNESCAPED_UNICODE ) . "\n";
        }
        self::aiLog( "Dataset for user #{$user_id} (rows: " . count($dataset) . ')',
            array_slice( $dataset, 0, 3 ) ); // первые 3 строки для проверки

        // 2. temp-file
        $tmp = tmpfile();
        fwrite( $tmp, $jsonl );
        $tmp_path = stream_get_meta_data( $tmp )['uri'];
        self::aiLog( 'Temp file created', $tmp_path );

        // 3. upload
        try {
            $upload = self::$client->files()->upload([
                'purpose' => 'fine-tune',
                'file'    => fopen( $tmp_path, 'r' ),
            ]);
            self::aiLog( 'File uploaded', $upload );
        } catch ( \Throwable $e ) {
            self::aiLog( 'Upload failed', $e->getMessage() );
            return false;
        }
        $file_id = $upload->id ?? null;

        // 4. fine-tune job
        try {
            $job = self::$client->fineTuning()->createJob([
                'training_file' => $file_id,
//                'model'         => 'gpt-3.5-turbo-1106',
                'model'         => 'gpt-4o-mini-2024-07-18',
                // 'suffix'      => "user_{$user_id}",
            ]);
            self::aiLog( 'Fine-tune job created', $job );
        } catch ( \Throwable $e ) {
            self::aiLog( 'Fine-tune request failed', $e->getMessage() );
            return false;
        }
        $job_id = $job->id ?? null;

        if ( ! $job_id ) {
            self::aiLog( 'Fine-tune job returned empty ID', $job );
            return false;
        }

        // 5. save to DB (как было)
        global $wpdb;
        $wpdb->replace(
            "{$wpdb->prefix}ocd_ai_user_models",
            [
                'user_id'        => $user_id,
                'model_id'       => $job_id,
                'status'         => 'pending',
                'last_trained_at'=> current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ]
        );
        self::aiLog( "Job {$job_id} stored in DB" );

        // 6. log option
        $settings = get_option( 'ocd_ai_settings', [] );
        $settings['last_model_generation_log'] = "[{$job_id}] user #{$user_id} " . current_time( 'mysql' );
        update_option( 'ocd_ai_settings', $settings );

        return $job_id;
    }



    public static function getModelStatus( string $job_id ): string {
        self::init();

        try {
            $job = self::$client->fineTuning()->retrieveJob( $job_id );

            $status = $job->status;
            $data   = [
                'status'   => $status,
                'trained_tokens' => $job->trainedTokens,
                'error'    => $job->error,
            ];

            // если fine-tune завершился успешно → сохраняем итоговый model-id
            if ( $status === 'succeeded' && $job->fineTunedModel ) {
                global $wpdb;
                $wpdb->update(
                    "{$wpdb->prefix}ocd_ai_user_models",
                    [
                        'status'   => 'ready',
                        'model_id' => $job->fineTunedModel,
                        'updated_at' => current_time( 'mysql' ),
                    ],
                    [ 'model_id' => $job_id ]
                );
                $data['fine_tuned_model'] = $job->fineTunedModel;
            } elseif ( in_array( $status, [ 'failed', 'cancelled' ], true ) ) {
                // фиксируем неуспешное завершение
                global $wpdb;
                $wpdb->update(
                    "{$wpdb->prefix}ocd_ai_user_models",
                    [
                        'status'     => $status,
                        'updated_at' => current_time( 'mysql' ),
                    ],
                    [ 'model_id' => $job_id ]
                );
            }

            self::aiLog( "Job {$job_id} details", $data );
            return $status;
        } catch ( \Throwable $e ) {
            self::aiLog( "Status check failed for {$job_id}", $e->getMessage() );
            return 'error';
        }
    }

    public static function refreshPendingModels(): void {
        self::init();

        global $wpdb;
        $table  = "{$wpdb->prefix}ocd_ai_user_models";
        $jobs   = $wpdb->get_results(
            "SELECT user_id, model_id FROM $table WHERE status IN ('pending','running')"
        );
        self::aiLog( 'Refresh start', [ 'total' => count( $jobs ) ] );

        foreach ( $jobs as $j ) {
            $status = self::getModelStatus( $j->model_id );   // вернёт строку
            self::aiLog( 'Status checked', [
                'user'   => $j->user_id,
                'job_id' => $j->model_id,
                'status' => $status,
            ] );
        }
        self::aiLog( 'Refresh end' );
    }



}
>>>>>>> Stashed changes
