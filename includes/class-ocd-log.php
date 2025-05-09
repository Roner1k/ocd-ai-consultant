<?php

namespace Ocd\AiConsultant;


defined('ABSPATH') || exit;

class OcdLog
{

    public static function aiLog(string $msg, $ctx = null): void
    {
        if ($ctx !== null) {
            $msg .= ' | ' . substr(json_encode($ctx, JSON_UNESCAPED_UNICODE), 0, 8000);
        }
        error_log('[AI] ' . $msg);
    }



    public static function logModelTraining(array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_model_training_log';

        return (bool)$wpdb->insert($table, [
            'job_id' => $data['job_id'],
            'status' => $data['status'],
            'created_at' => current_time('mysql'),
            'import_log' => $data['import_log'] ?? null,
            'training_preview' => $data['training_preview'] ?? null,
        ], ['%s', '%s', '%s', '%s', '%s']);
    }
}
