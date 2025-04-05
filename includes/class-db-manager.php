<?php
namespace Ocd\AiConsultant;

//  plugin prefix: ocd_ai_

defined('ABSPATH') || exit;

class DbManager
{
    /**
     * Entry point to create all plugin tables.
     * Add calls to more tables as needed.
     */
    public static function createTables()
    {
//        self::createKnowledgeBaseTable();
        self::createModelLogTable();


        // Add more here as needed
    }

    /**
     * Creates the table for imported Excel data (Q&A or other).
     * Structure is intentionally generic: col1, col2, col3...
     * Import will rewrite cols  like excel file
     */
//    private static function createKnowledgeBaseTable()
//    {
//        global $wpdb;
//
//        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//
//        $charset_collate = $wpdb->get_charset_collate();
//        $table_name = $wpdb->prefix . 'ocd_ai_knowledge_base';
//
//        $sql = "
//            CREATE TABLE IF NOT EXISTS `$table_name` (
//                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
//                col1 TEXT NULL,
//                col2 TEXT NULL,
//                PRIMARY KEY (id)
//            ) $charset_collate;
//        ";
//
//        dbDelta($sql);
//    }

    private static function createModelLogTable()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ocd_ai_model_log';

        $sql = "
        CREATE TABLE IF NOT EXISTS `$table_name` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            error TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (user_id)
        ) $charset_collate;
    ";

        dbDelta($sql);
    }



}
