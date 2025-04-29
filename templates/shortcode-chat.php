<?php
defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?: $current_user->user_login;
$preferred_language = get_user_meta($current_user->ID, 'ocd_ai_language', true) ?: 'English'; // если заранее не сохранили язык
?>

<div id="ocd-ai-chat-container"
     class="ocd-ai-chat-wrapper"
     data-chat-status="<?php echo esc_attr($chat_status); ?>"
     data-model-status="<?php echo esc_attr($model_info['status']); ?>"
     data-model-id="<?php echo esc_attr($model_info['model_id']); ?>"
     data-last-trained="<?php echo esc_attr($model_info['last_trained_at']); ?>"
     data-chat-language="<?php echo esc_attr($model_info['chat_language']); ?>">

    <!-- Заголовок -->
    <div class="ocd-ai-chat-header">
        <div class="ocd-ai-chat-title">
            OCD AI CONSULTANT
        </div>
        <div class="ocd-ai-chat-language">
            <label for="ocd-ai-language-select" class="ocd-ai-language-label">Language:</label>
            <select name="ocd_ai_language" id="ocd-ai-language-select">
                <option value="en">English</option>
                <option value="fr">French</option>
                <option value="pl">Polish</option>
                <option value="it">Italian</option>
                <option value="de">German</option>
                <option value="es">Spanish</option>
                <option value="uk">Ukrainian</option>
                <option value="ru">Russian</option>
                <option value="zh">Chinese</option>
            </select>
        </div>
    </div>

    <!-- Subheader для дебага -->
    <div class="ocd-ai-chat-subheader">
        <p>Model Info:
            <strong id="ocd-ai-model-status">
                <?php
                echo 'Status: ' . esc_html($model_info['status']) .
                    ' | Model ID: ' . esc_html($model_info['model_id']) .
                    ' | Last trained: ' . esc_html($model_info['last_trained_at']);
                ?>
            </strong>
        </p>
    </div>


    <!-- История сообщений -->
    <div id="ocd-ai-chat-history" class="ocd-ai-chat-history">
        <!-- Chat history appears here -->
    </div>

    <!-- Поле ввода -->
    <div class="ocd-ai-chat-input-area">
        <textarea id="ocd-ai-user-message" placeholder="Type your message..." rows="3"></textarea>
        <button id="ocd-ai-send-message" type="button">Send</button>
    </div>

    <!-- Оверлей -->
    <div id="ocd-ai-chat-overlay" class="ocd-ai-chat-overlay" style="display: none;">
        <div class="ocd-ai-chat-overlay-message"></div>
    </div>

</div>

