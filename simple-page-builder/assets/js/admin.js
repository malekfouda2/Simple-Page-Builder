jQuery(document).ready(function($) {
    
    $('#spb-generate-key-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Generating...');
        
        $.ajax({
            url: spbAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'spb_generate_api_key',
                nonce: spbAdmin.nonce,
                key_name: $('#key_name').val(),
                expiration_date: $('#expiration_date').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#spb-api-key-value').text(response.data.api_key);
                    $('#spb-new-key-display').slideDown();
                    
                    $form[0].reset();
                    
                    setTimeout(function() {
                        location.reload();
                    }, 5000);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    $('#spb-copy-key').on('click', function() {
        var apiKey = $('#spb-api-key-value').text();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(apiKey).then(function() {
                var $button = $('#spb-copy-key');
                var originalText = $button.text();
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            });
        } else {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(apiKey).select();
            document.execCommand('copy');
            $temp.remove();
            
            var $button = $('#spb-copy-key');
            var originalText = $button.text();
            $button.text('Copied!');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        }
    });
    
    $('.spb-revoke-key').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        var keyId = $button.data('key-id');
        
        $button.prop('disabled', true).text('Revoking...');
        
        $.ajax({
            url: spbAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'spb_revoke_api_key',
                nonce: spbAdmin.nonce,
                key_id: keyId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).text('Revoke');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Revoke');
            }
        });
    });
});
