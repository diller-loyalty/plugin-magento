<?php

namespace Diller\LoyaltyProgram\Plugin\Checkout;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

class DillerConsent implements LayoutProcessorInterface{
    /**
     * @var Data
     */
    protected Data $_loyaltyHelper;

    public function __construct(Data $loyaltyHelper) {
        $this->_loyaltyHelper = $loyaltyHelper;
    }

    public function process($jsLayout){
        $loyaltyDetails = $this->_loyaltyHelper->getLoyaltyDetails();

        if(array_contains($_SESSION['customer_base'], 'customer_id')){
            $member = $this->_loyaltyHelper->searchMemberByCustomerId($_SESSION['customer_base']['customer_id']);
        }

        $attributeCode = 'diller_consent';
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['before-form']['children'][$attributeCode] = [
            'component' => 'Magento_Ui/js/form/element/single-checkbox',
            'config' => [
                'customScope' => 'shippingAddress',
                'customEntry' => null,
                'template' => 'ui/form/components/button/simple',
                'elementTmpl' => 'ui/form/element/checkbox',
                'label' => "I want to join ".$loyaltyDetails['storeName']."'s loyalty program and receive benefits, offers and other marketing communications electronically, including email, SMS and the like.",
                'id' => $attributeCode,
                'value' => (isset($member) && $member->getConsent()->getGdprAccepted()) ? true : ''
            ],
            'dataScope' => 'shippingAddress.' . $attributeCode,
            'label' => $loyaltyDetails['storeName'] . ' consent',
            'provider' => 'checkoutProvider',
            'visible' => true,
            'validation' => [],
            'sortOrder' => 1000,
            'id' => $attributeCode
        ];


        $attributeCode = 'diller_order_history_consent';
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['before-form']['children'][$attributeCode] = [
            'component' => 'Magento_Ui/js/form/element/single-checkbox',
            'config' => [
                'customScope' => 'shippingAddress',
                'customEntry' => null,
                'template' => 'ui/form/components/button/simple',
                'elementTmpl' => 'ui/form/element/checkbox',
                'label' => "I want to get offers and benefits that suit me based on my preferences and purchase history.",
                'id' => $attributeCode,
                'value' => (isset($member) && $member->getConsent()->getSaveOrderHistory()) ? true : ''
            ],
            'dataScope' => 'shippingAddress.' . $attributeCode,
            'label' => $loyaltyDetails['storeName'] . ' consent',
            'provider' => 'checkoutProvider',
            'visible' => true,
            'validation' => [],
            'sortOrder' => 1001,
            'id' => $attributeCode
        ];

        return $jsLayout;
    }
}
