<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class Shortcodes
{
    public static function register()
    {
        add_shortcode('ocd_ai_chat', [self::class, 'renderChat']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets()
    {
        if (!is_singular()) return;

        global $post;
        if (has_shortcode($post->post_content, 'ocd_ai_chat')) {
            $plugin_url = plugin_dir_url(__FILE__) . '../assets/public/';

            wp_enqueue_style('ocd-ai-chat-style', $plugin_url . 'css/ocd-ai-chat-style.css', [], '1.0.0');
            wp_enqueue_script('ocd-ai-chat-utils', $plugin_url . 'js/ocd-ai-chat-utils.js', [], '1.0.0', true);
            wp_enqueue_script('ocd-ai-chat-ajax', $plugin_url . 'js/ocd-ai-chat-ajax.js', ['jquery', 'ocd-ai-chat-utils'], '1.0.0', true);
            wp_enqueue_script('ocd-ai-chat-main', $plugin_url . 'js/ocd-ai-chat-main.js', ['jquery', 'ocd-ai-chat-ajax'], '1.0.0', true);

            wp_localize_script('ocd-ai-chat-main', 'ocdAiChat', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ocd_ai_chat_nonce'),
            ]);
        }
    }

    public static function renderChat($atts = [], $content = null)
    {
        ob_start();

        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) {
            echo '<p>Please log in to access the AI chat.</p>';
            return ob_get_clean();
        }

        $has_active_subscription = true; // TODO: заменить на реальную проверку

        $chat_status = 'ready';
        $model_info = [
            'status' => 'ready',
            'model_id' => '',
            'last_trained_at' => '',
        ];

        if (!$has_active_subscription) {
            $chat_status = 'no_subscription';
        } else {
            $model_id = OpenAiService::getActiveModelId();
            if (!$model_id) {
                $chat_status = 'error';
            } else {
                $model_info['model_id'] = $model_id;
            }
        }

        include plugin_dir_path(__FILE__) . '/../templates/shortcode-chat.php';
        return ob_get_clean();
    }
}
