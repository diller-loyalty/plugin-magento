<?php

namespace Diller\LoyaltyProgram\Block\Frontend;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

class LayoutProcessor implements LayoutProcessorInterface{
    /**
     * @var Data
     */
    protected Data $_loyaltyHelper;

    public function __construct(Data $loyaltyHelper) {
        $this->_loyaltyHelper = $loyaltyHelper;
    }

    public function process($jsLayout){
        $loyaltyDetails = $this->_loyaltyHelper->getLoyaltyDetails();
        //TODO: add privacy policy url from API V2
        $attributeCode = 'diller_consent';
        $fieldConfiguration = [
            'component' => 'Magento_Ui/js/form/element/single-checkbox',
            'label' => $loyaltyDetails['storeName'] . ' consent',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'customEntry' => null,
                'template' => 'ui/form/components/button/simple',
                'elementTmpl' => 'ui/form/element/checkbox',
                'label' => "I want to join ".$loyaltyDetails['storeName']."'s loyalty program and receive benefits, offers and other marketing communications electronically, including email, SMS and the like.",
            ],
            'dataScope' => 'shippingAddress.custom_attributes' . '.' . $attributeCode,
            'provider' => 'checkoutProvider',
            'sortOrder' => 1000,
            'validation' => [
                'required-entry' => false
            ],
            'visible' => true
        ];

        $jsLayout['components']['checkout']['children']
        ['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['shipping-address-fieldset']
        ['children'][$attributeCode] = $fieldConfiguration;
        return $jsLayout;
    }
}
