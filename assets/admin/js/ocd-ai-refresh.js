jQuery( function ( $ ) {
    $( '#ocd-ai-refresh' ).on( 'click', function () {
        const $btn  = $( this ).prop( 'disabled', true );
        const $msg  = $( '#ocd-ai-refresh-msg' ).hide().text( 'Working...' );

        $.post( ocdAiRefresh.ajaxUrl, {
            action : 'ocd_ai_refresh_models',
            nonce  : ocdAiRefresh.nonce
        }, function ( resp ) {
            $btn.prop( 'disabled', false );
            $msg.text( resp.data.message ).css( 'color', resp.success ? 'green' : 'red' ).show();
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $msg.text( 'Request failed.' ).css( 'color', 'red' ).show();
        } );
    } );
} );
