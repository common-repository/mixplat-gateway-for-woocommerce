jQuery(function ($) {
    if (typeof wc_checkout_params === 'undefined')
        return false;
    if (typeof Mixplat === 'undefined')
        return false;

    const $checkout_form = $('form.checkout');

    $checkout_form.on('checkout_place_order_mixplatpayment', function () {
        const $form = $(this);

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.checkout_url,
            data: $form.serialize(),
            dataType: 'json',
            success: function (result) {
                try {
                    if ('success' === result.result && result.data && $form.triggerHandler('checkout_place_order_success', result) !== false) {
                        let M = new Mixplat(result.data);
                        M.build();

                        M.setSuccessCallback(result.data.url_success);
                        M.setFailCallback(result.data.url_failure);
                    } else {
                        throw 'Invalid response';
                    }
                } catch (err) {
                    submit_error('<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                submit_error(
                    '<div class="woocommerce-error">' +
                    (errorThrown || wc_checkout_params.i18n_checkout_error) +
                    '</div>'
                );
            }
        });

        return false;
    });

    function submit_error(error_message) {
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        $checkout_form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
        $checkout_form.removeClass('processing').unblock();
        $checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
        $(document.body).trigger('checkout_error', [error_message]);
    }
});