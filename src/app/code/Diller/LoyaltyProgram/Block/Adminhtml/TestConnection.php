<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

/**
 * Class TestConnection
 * @package Diller\LoyaltyProgram\Block\Adminhtml
 */

class TestConnection extends Field {
    protected $_template = 'Diller_LoyaltyProgram::testconnection.phtml';
    private $scopeConfig;

    public function __construct(

        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element) {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element) {
        return $this->_toHtml();
    }

    public function testConnection() {
        $store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE);
        $api_key = $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE);

        $dillerAPI = new \DillerAPI\DillerAPI($store_uid, $api_key);
        $store = json_decode($dillerAPI->Stores->get($store_uid));
        if($store->id === $store_uid){
            return true;
        }
        return false;
    }

    public function getButtonHtml() {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'testConnectionBtn',
                'label' => __('Test')
            ]
        );
        return $button->toHtml();
    }
}
