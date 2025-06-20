var OcdAiChatAjax = (function($) {
    return {
        sendMessage: function(prompt, callback) {
            const payload = {
                action: 'ocd_ai_chat_send_message',
                nonce: ocdAiChat.nonce,
                prompt: prompt
            };

            console.log('[OCD-AI] Sending message to server:', JSON.stringify(payload, null, 2));

            $.post(ocdAiChat.ajaxUrl, payload, function(resp) {
                if (resp.success) {
                    if (resp.data.debug) {
                        console.log('[OCD-AI] OpenAI Debug Payload:', resp.data.debug);
                    }

                    callback(resp.data.reply);
                } else {
                    callback('Error: ' + (resp.data.message || 'Unknown error'));
                }
            }).fail(function() {
                callback('Request failed.');
            });
        },


        checkModelStatus: function(modelId, callback) {
            $.post(ocdAiChat.ajaxUrl, {
                action: 'ocd_ai_check_model_status',
                nonce: ocdAiChat.nonce,
                model_id: modelId
            }, function(resp) {
                if (resp.success) {
                    callback(resp.data);
                } else {
                    callback(null);
                }
            });
        },

        loadChatHistory: function(callback) {
            $.post(ocdAiChat.ajaxUrl, {
                action: 'ocd_ai_chat_load_history',
                nonce: ocdAiChat.nonce,
            }, function(resp) {
                if (resp.success) {
                    callback(resp.data.history);
                } else {
                    callback([]);
                }
            }).fail(function() {
                callback([]);
            });
        },
        updateChatLanguage: function(language, callback) {
            $.post(ocdAiChat.ajaxUrl, {
                action: 'ocd_ai_update_language',
                nonce: ocdAiChat.nonce,
                language: language
            }, function(resp) {
                if (callback) callback(resp.success);
            }).fail(function() {
                if (callback) callback(false);
            });
        }


    };
})(jQuery);
