jQuery(function($){
    $('#ocd-ai-list-models-btn').on('click', function(){
        const $btn = $(this).prop('disabled', true);
        const $result = $('#ocd-ai-list-models-result');
        $result.hide().text('Working...').show();
        // Получаем nonce из скрытого input формы
        var nonce = $('#ocd-ai-list-models-form input[name=ocd_ai_chat_nonce]').val();
        $.post(ocdAiRefresh.ajaxUrl, {
            action: 'ocd_ai_list_models',
            nonce: nonce
        }, function(resp){
            $btn.prop('disabled', false);
            if(resp.success && resp.data.models){
                var html = '<ul>';
                resp.data.models.forEach(function(model){
                    html += '<li>' + (model.id ? model.id : JSON.stringify(model)) + (model.status ? ' ('+model.status+')' : '') + '</li>';
                });
                html += '</ul>';
                $result.html(html).css('color','inherit').show();
            } else {
                $result.html('<div class="error">Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') + '</div>').css('color','red').show();
            }
        }).fail(function(){
            $btn.prop('disabled', false);
            $result.html('<div class="error">Request failed</div>').css('color','red').show();
        });
    });
});
