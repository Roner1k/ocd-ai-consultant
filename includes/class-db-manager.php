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
    public static function createModelTrainingLogTable(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'ocd_ai_model_training_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "
    CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        job_id VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        import_log TEXT NULL,
        training_preview LONGTEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY job_id (job_id(191))
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
        job_id VARCHAR(255) NULL,                
        model_id VARCHAR(255) NOT NULL,          
        import_log TEXT NULL,                 
        status VARCHAR(50) NOT NULL,
        model_type ENUM('active','backup','archived') DEFAULT 'archived',
        error_log TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
