define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_CheckoutAgreements/js/model/agreements-assigner',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/url-builder',
    'mage/url',
    'Magento_Checkout/js/model/error-processor',
    'uiRegistry'
], function (
    $,
    wrapper,
    agreementsAssigner,
    quote,
    customer,
    urlBuilder,
    urlFormatter,
    errorProcessor,
    registry
) {
    'use strict';

    return function (placeOrderAction) {

        /** Override default place order action and add agreement_ids to request */
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            agreementsAssigner(paymentData);
            var isCustomer = customer.isLoggedIn();
            var quoteId = quote.getQuoteId();

            var url = urlFormatter.build('loyaltyprogram/quote/save');

            var diller_consent_element = document.querySelector('[name="diller_consent"]');
            if (diller_consent_element !== null) {
                var diller_consent = document.querySelector('[name="diller_consent"]').checked ?? false;
                var diller_order_history_consent = document.querySelector('[name="diller_order_history_consent"]').checked ?? 0;

                console.log('diller_consent: ' + diller_consent)
                console.log('diller_order_history_consent: ' + diller_order_history_consent);

                if (diller_consent) {
                    var payload = {
                        'cartId': quoteId,
                        'diller_consent': diller_consent ? 1 : 0,
                        'diller_order_history_consent': diller_order_history_consent ? 1 : 0,
                        'is_customer': isCustomer
                    };

                    var result = true;

                    $.ajax({
                        url: url,
                        data: payload,
                        dataType: 'text',
                        type: 'POST',
                    }).done(
                        function (response) {
                            result = true;
                        }
                    ).fail(
                        function (response) {
                            result = false;
                            errorProcessor.process(response);
                        }
                    );
                }
            }
            return originalAction(paymentData, messageContainer);
        });
    };
});
