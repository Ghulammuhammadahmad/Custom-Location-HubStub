console.log('clhs-generatepage.js loaded');

(function ($) {
    $(document).on('click', '#clhs-generate-pages', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $result = $('#clhs-generate-pages-result');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Generating...');
        $result.removeClass('clhs-error clhs-success').text('');

        $.ajax({
            url: (window.clhsGeneratorAjax && window.clhsGeneratorAjax.ajax_url) || '',
            method: 'POST',
            dataType: 'text',
            data: {
                action: 'clhs_generate_pages'
            },
            xhrFields: {
                onprogress: function (e) {
                    if (!e.currentTarget.responseText) return;
                    $result.html(e.currentTarget.responseText);
                }
            },
            success: function (responseText) {
                if (responseText) {
                    $result.addClass('clhs-success').html(responseText);
                }
            },
            error: function (xhr, status) {
                $result.addClass('clhs-error').html('AJAX error: ' + status);
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
})(jQuery);