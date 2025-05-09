<?php

namespace Ocd\AiConsultant;

use Ocd\AiConsultant\OcdLog;


defined('ABSPATH') || exit;

class Cron
{

    public static function register(): void
    {
        add_action('ocd_ai_check_training_models', [self::class, 'checkTrainingModels']);
    }

    /**
     * Handler: updates the status of models in the “training” status
 */
    public static function checkTrainingModels(): void
    {
        $summary = OpenAiService::refreshPendingModels();
        OcdLog::aiLog('Cron refresh executed', $summary);

        // ✅ Теперь универсально: если остались модели в training — запланировать следующее событие
        self::scheduleIfTrainingExists();
    }

    /**
     * Schedules a single startup after 10 minutes
     */
    public static function scheduleSingleEvent(): void
    {
        if (!wp_next_scheduled('ocd_ai_check_training_models')) {
            wp_schedule_single_event(time() + 600, 'ocd_ai_check_training_models');
        }
    }

    /**
     * Schedules an event if there is at least one model in training
     */
    public static function scheduleIfTrainingExists(): void
    {
        global $wpdb;
        $has_training = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ocd_ai_models WHERE status = 'training'");

        if ((int)$has_training > 0) {
            self::scheduleSingleEvent();
        }
    }
}
