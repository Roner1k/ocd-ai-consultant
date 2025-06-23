jQuery(function ($) {
    var $container = $('#ocd-ai-chat-container');
    var $history = $container.find('#ocd-ai-chat-history');
    var $textarea = $container.find('#ocd-ai-user-message');
    var $sendBtn = $container.find('#ocd-ai-send-message');
    var $overlay = $container.find('.ocd-ai-chat-overlay');
    var $overlayMsg = $container.find('.ocd-ai-chat-overlay-message');
    var $modelStatus = $('#ocd-ai-model-status');
    var $loader = $('#ocd-chat-loader');

    var isSending = false;

    var chatStatus = $container.data('chat-status');
    var modelStatus = $container.data('model-status');
    var modelId = $container.data('model-id');

    function getChatLanguageCookie() {
        const match = document.cookie.match(/(?:^|; )ocd_ai_chat_lang=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setChatLanguageCookie(lang) {
        document.cookie = "ocd_ai_chat_lang=" + encodeURIComponent(lang) + "; path=/; max-age=" + (60 * 60 * 24 * 30);
    }

    var defaultGreetings = {
        en: "Hello! I'm your OCD AI Consultant. How can I assist you today?",
        fr: "Bonjour! Je suis votre consultant IA pour le TOC. Comment puis-je vous aider aujourd'hui?",
        pl: "Cześć! Jestem twoim konsultantem AI w sprawie OCD. W czym mogę pomóc?",
        it: "Ciao! Sono il tuo consulente AI per il DOC. Come posso aiutarti?",
        uk: "Привіт! Я ваш AI консультант з ОКР. Чим можу допомогти?"
    };

    var chatLanguage = getChatLanguageCookie() || $container.data('chat-language') || 'en';

    if (chatStatus === 'training') {
        $overlay.show();
        $overlayMsg.text('Your personal assistant is currently being trained. Please wait...');
        var interval = setInterval(function () {
            OcdAiChatAjax.checkModelStatus(modelId, function (data) {
                if (data && data.status === 'ready') {
                    clearInterval(interval);
                    $overlay.hide();
                    $textarea.prop('disabled', false);
                    $sendBtn.prop('disabled', false);
                    $modelStatus.text('Status: ready | Model ID: ' + modelId + ' | Last trained: ' + data.last_trained);
                }
            });
        }, 30000);
    }

    function appendMessage(role, text) {
        var messageClass = (role === 'user') ? 'user-message' : 'assistant-message';
        var iconHtml = '<div class="ocd-message-icon"></div>';
        var messageHtml = '<div class="' + messageClass + '">' + iconHtml + '<div>' + OcdAiChatUtils.escapeHtml(text) + '</div></div>';
        $history.append(messageHtml);
        $history.scrollTop($history.prop("scrollHeight"));
    }

    OcdAiChatAjax.loadChatHistory(function (history) {
        if (history.length > 0) {
            history.forEach(function (item) {
                appendMessage(item.role, item.message);
            });
        } else {
            appendMessage('assistant', defaultGreetings[chatLanguage] || defaultGreetings['en']);
        }
    });

    var $languageSelect = $container.find('#ocd-ai-language-select');
    if (chatLanguage && $languageSelect.length) {
        $languageSelect.val(chatLanguage);
    }

    $languageSelect.on('change', function () {
        var newLang = $(this).val();
        setChatLanguageCookie(newLang);
        chatLanguage = newLang;
        console.log('Language set to ' + newLang + ' and saved in cookie.');

        // Если история пуста или содержит только приветствие — обновить приветствие
        var $messages = $history.children();
        if ($messages.length === 1 && $messages.first().hasClass('assistant-message')) {
            $messages.first().find('div').last().text(defaultGreetings[newLang] || defaultGreetings['en']);
        }
    });

    $sendBtn.on('click', function () {
        if (isSending) return;

        var userMessage = $textarea.val().trim();
        if (!userMessage) return;

        appendMessage('user', userMessage);
        $textarea.val('');
        isSending = true;

        $sendBtn.prop('disabled', true).addClass('sending');
        $loader.show();

        OcdAiChatAjax.sendMessage(userMessage, function (response) {
            appendMessage('assistant', response);
            isSending = false;

            $sendBtn.prop('disabled', false).removeClass('sending');
            $loader.hide();
        });
    });

    $textarea.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !isSending) {
            e.preventDefault();
            $sendBtn.trigger('click');
        }
    });
});
