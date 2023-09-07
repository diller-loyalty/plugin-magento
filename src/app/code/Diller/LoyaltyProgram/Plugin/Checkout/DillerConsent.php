<?php

namespace Diller\LoyaltyProgram\Plugin\Checkout;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;

class DillerConsent implements LayoutProcessorInterface{
    /**
     * @var Data
     */
    protected Data $_loyaltyHelper;

    protected Escaper $escaper;

    public function __construct(Data $loyaltyHelper) {
        $this->_loyaltyHelper = $loyaltyHelper;
        $this->escaper = ObjectManager::getInstance()->get(Escaper::class);
    }

    public function process($jsLayout){
        $loyaltyDetails = $this->_loyaltyHelper->getLoyaltyDetails();
        $is_member = false;

        if(array_key_exists('customer_base', $_SESSION)){
            if(array_key_exists('customer_id', $_SESSION['customer_base'])){
                $is_member = ($member = $this->_loyaltyHelper->searchMemberByCustomerId($_SESSION['customer_base']['customer_id']));
            }
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
                'label' => sprintf($this->escaper->escapeHtml(__("I want to join %s's loyalty program and receive benefits, offers and other marketing communications electronically, including email, SMS and the like.")), $loyaltyDetails['storeName']),
                'id' => $attributeCode,
                'value' => ($is_member && $member->getConsent()->getGdprAccepted()) ? true : ''
            ],
            'dataScope' => 'shippingAddress.' . $attributeCode,
            'label' => $loyaltyDetails['storeName'] . ' consent',
            'provider' => 'checkoutProvider',
            'visible' => !$is_member,
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
                'label' => $this->escaper->escapeHtml(__("I want to get offers and benefits that suit me based on my preferences and purchase history.")),
                'id' => $attributeCode,
                'value' => ($is_member && $member->getConsent()->getSaveOrderHistory()) ? true : ''
            ],
            'dataScope' => 'shippingAddress.' . $attributeCode,
            'label' => $loyaltyDetails['storeName'] . ' consent',
            'provider' => 'checkoutProvider',
            'visible' => !$is_member,
            'validation' => [],
            'sortOrder' => 1001,
            'id' => $attributeCode
        ];

        return $jsLayout;
    }
}
