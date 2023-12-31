let config = {
    map: {
        '*': {
            intl: 'Diller_LoyaltyProgram/node_modules/intl-tel-input/build/js/intlTelInput',
            countryselect: 'Diller_LoyaltyProgram/node_modules/country-select-js/build/js/countrySelect.min'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/action/place-order': {
                'Diller_LoyaltyProgram/js/order/place-order-mixin': true
            },
        }
    }
};
