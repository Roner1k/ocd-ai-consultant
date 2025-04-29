<?php
/**
 * Plugin Name: OCD AI Consultant
 * Plugin URI: https://your-client-site.com/
 * Description: AI consultant plugin for OCD therapy with fine-tuned user models and Excel knowledge base.
 * Version: 1.0.0
 * Author: Next Level
 * Author URI: https://nextlevelwebsolutions.com/
 * License: proprietary
 */

defined('ABSPATH') || exit;

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Include all core plugin files
$includes = [
    'includes/class-admin.php',
    'includes/class-db-manager.php',
    'includes/class-excel-importer.php',
    'includes/class-user-data-builder.php',
<<<<<<< Updated upstream
=======
    'includes/class-json-dataset-builder.php',
    'includes/class-openai-service.php',
    'includes/class-shortcodes.php',
    'includes/class-ajax.php',
>>>>>>> Stashed changes
    // Add more here as needed
];

foreach ($includes as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

\Ocd\AiConsultant\Admin::init();
\Ocd\AiConsultant\Shortcodes::register();
\Ocd\AiConsultant\Ajax::register();



// Plugin activation hook
register_activation_hook(__FILE__, function () {
    // Load Composer
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    // Load needed classes
    $includes = [
        'includes/class-db-manager.php',
    ];

    foreach ($includes as $file) {
        $path = plugin_dir_path(__FILE__) . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // Create required DB tables
    \Ocd\AiConsultant\DbManager::createTables();

    // Create upload folder for Excel imports
    $upload_dir = wp_upload_dir();
    $import_dir = trailingslashit($upload_dir['basedir']) . 'ocd-ai-imports';

    if (!file_exists($import_dir)) {
        wp_mkdir_p($import_dir);
    }
    // Default plugin options (created once)
    $default_settings = [
        'openai_api_key' => '',
        'last_import_log' => '',
        'last_model_generation_log' => '',
    ];

// Plugin options stored in 'ocd_ai_settings':
// - openai_api_key: string, user's OpenAI API key
// - last_import_log: string, last imported Excel filename,(datetime), when the last import happened,string or null, error log for last import
// - last_model_generation_log: string, result of last AI model regeneration

    if (get_option('ocd_ai_settings') === false) {
        add_option('ocd_ai_settings', $default_settings);
    }


});


