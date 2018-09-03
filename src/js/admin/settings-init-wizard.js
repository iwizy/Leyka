// Campaign decoration custom setting:
jQuery(document).ready(function($){
    
    if( !$('#leyka-settings-form-cd-campaign_decoration').length) {
        return;
    }
    
    var campaignAttachmentId = 0;
    var $decorationControlsWrap = $('#campaign-decoration');
    var $previewIframe = $decorationControlsWrap.find('#leyka-preview-frame iframe');
    var $loading = $decorationControlsWrap.find('#campaign-decoration-loading');
    var campaignId = $decorationControlsWrap.find('#leyka-decor-campaign-id').val();
    
    function disableForm() {
        $decorationControlsWrap.find('#campaign_photo-upload-button').prop('disabled', true);
        $decorationControlsWrap.find('#leyka_campaign_template-field').prop('disabled', true);
    }
    
    function enableForm() {
        $decorationControlsWrap.find('#campaign_photo-upload-button').prop('disabled', false);
        $decorationControlsWrap.find('#leyka_campaign_template-field').prop('disabled', false);
    }
    
    function showLoading() {
        $loading.show();
    }
    
    function hideLoading() {
        $loading.hide();
    }
    
    function reloadPreviewFrame() {
        //$previewIframe.get(0).contentWindow.location.reload(true);
        var previewLocation = $previewIframe.get(0).contentWindow.location;
        var href = previewLocation.href;
        href = href.replace(/&rand=.*/, '');
        href += '&rand=' + Math.random();
        previewLocation.href = href;
    }

    $('#campaign_photo-upload-button').click(function(){
        
        var frame = wp.media({
            title: 'Выбор фотографии кампании',
            multiple: false
        });
        
        frame.on( 'select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            //alert( attachment.id );
            
            if(attachment.id == campaignAttachmentId) {
                return;
            }
            
            disableForm();
            showLoading();
            
            $('#leyka-campaign_thumnail').val(attachment.id);
            
            $.post(leyka.ajaxurl, {
                action: 'leyka_set_campaign_photo',
                attachment_id: attachment.id,
                campaign_id: campaignId,
                nonce: $decorationControlsWrap.find('#set-campaign-photo-nonce').val()
            }, null, 'json')
                .done(function(json) {
        
                    if(typeof json.status !== 'undefined' && json.status === 'error') {
                        alert('Ошибка!');
                        return;
                    }
                    
                    reloadPreviewFrame();
                })
                .fail(function() {
                    alert('Ошибка!');
                })
                .always(function() {
                    hideLoading();
                    enableForm();
                });            
            
            
        });

        frame.open();        
    });
    
    $('#leyka_campaign_template-field').on('change', function(){
        
        disableForm();
        showLoading();
        
        var template = $(this).val();
        $('#leyka-campaign_template').val(template);
        
        $.post(leyka.ajaxurl, {
            action: 'leyka_set_campaign_template',
            campaign_id: campaignId,
            template: template,
            nonce: $decorationControlsWrap.find('#set-campaign-template-nonce').val()
        }, null, 'json')
            .done(function(json) {
    
                if(typeof json.status !== 'undefined' && json.status === 'error') {
                    alert('Ошибка!');
                    return;
                }
                
                reloadPreviewFrame();
            })
            .fail(function() {
                alert('Ошибка!');
            })
            .always(function() {
                hideLoading();
                enableForm();
            });            
            
    });

});

// Edit permalink:
jQuery(document).ready(function($){

    var $edit_permalink_wrap = $('.custom_campaign_completed'),
        $loading = $edit_permalink_wrap.find('.edit-permalink-loading').hide();

    $edit_permalink_wrap.find('.action-permalinks-on').on('click.leyka', function(e){

        e.preventDefault();

        var $this = $(this);

        $loading.show();
        $this.hide();

        $.post(leyka.ajaxurl, {
            action: 'leyka_permalinks_on',
            nonce: $this.data('nonce')
        })
        //     .done(function(json) {
        //
        //     if(typeof json.status !== 'undefined' && json.status === 'error') {
        //         alert('Ошибка!');
        //     }
        //
        // }).fail(function() {
        //     alert('Ошибка!');
        // }).always(function() {
        //     $loading.hide();
        //     console.log('OK!');
        //     // enableForm();
        // });

    });

    // $('.settings-block.custom_campaign_completed')

});