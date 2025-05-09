<?php
defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?: $current_user->user_login;

$status_text = 'Unknown';
switch ($model_info['status']) {
    case 'active':
        $status_text = 'Active model';
        break;
    case 'backup':
        $status_text = 'Using backup model';
        break;
    case 'training':
        $status_text = 'Model is training...';
        break;
    case 'error':
        $status_text = 'Error: no model available';
        break;
}
?>

<div id="ocd-ai-chat-container"
     class="ocd-ai-chat-wrapper"
     data-model-id="<?php echo esc_attr($model_info['model_id']); ?>">

    <!-- Заголовок -->
    <div class="ocd-ai-chat-header">
        <div class="ocd-ai-chat-title"><strong>OCD AI CONSULTANT</strong>

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
                echo 'Status: Using ' . esc_html($model_info['model_type']) . ' model | Model ID: ' . esc_html($model_info['model_id']);
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
