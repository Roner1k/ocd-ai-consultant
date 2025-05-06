<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class DbManager
{
    public static function createTables()
    {
        self::createModelTrainingLogTable();
        self::createModelsTable();
        self::createUserAiInputTable();
        self::createUserChatTable();
    }


    /** Logs every fine-tuning request for a user
     *
     * */
    private static function createModelTrainingLogTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_model_training_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "
    CREATE TABLE IF NOT EXISTS `$table` (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        model_id VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL,
        trigger_source VARCHAR(20) NOT NULL,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (user_id),
        INDEX (created_at)
    ) $charset;
    ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }


    /**
     * Stores the current state of each user's custom AI model.
     * Includes training status, scheduled retraining, and selected chat language.
     * One row per user.
     */
    private static function createModelsTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_models';
        $charset = $wpdb->get_charset_collate();

        $sql = "
        CREATE TABLE IF NOT EXISTS `$table` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            model_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            last_trained_at DATETIME NULL,
            training_scheduled_at DATETIME NULL,
            error_log TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }


    /** Contains the full message history for the user's AI chat.
     * Each message is tagged as either 'user' or 'assistant'.
     * Used to preserve context and replay recent interactions.
     */
    private static function createUserChatTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_chat';
        $charset = $wpdb->get_charset_collate();

        $sql = "
        CREATE TABLE IF NOT EXISTS `$table` (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('user','assistant') NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (user_id),
            INDEX (created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Stores all Gravity Forms responses for each user.
     * This is the primary training dataset for building personalized models.
     * Populated automatically via gform_after_submission hook.
     */
    private static function createUserAiInputTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_ai_input';
        $charset = $wpdb->get_charset_collate();

        $sql = "
    CREATE TABLE IF NOT EXISTS `$table` (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        user_id BIGINT UNSIGNED,
        email VARCHAR(255),
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        source_form VARCHAR(100),
        source_entry BIGINT,
        input_hash VARCHAR(32) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY input_hash (input_hash),
        INDEX (user_id),
        INDEX (email)
    ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }


}
