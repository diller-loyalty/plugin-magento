<?php

namespace Diller\LoyaltyProgram\Block;

use Diller\LoyaltyProgram\Block\Frontend\DillerAPI;
use Diller\LoyaltyProgram\Block\Frontend\ScopeConfigInterface;
use Diller\LoyaltyProgram\Block\Frontend\StoreUID;
use Magento\Customer\Helper\View;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Loyalty Customer and Member Info
 */
class LoyaltyDetails extends Template {

    /**
     * @var View
     */
    protected $_helperView;

    /**
     * @var DillerAPI
     */
    private $dillerAPI;

    /**
     * @var StoreUID
     */
    private $store_uid;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Constructor
     *
     * @param Context $context
     * @param View $helperView
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        View $helperView,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->_helperView = $helperView;
        $this->scopeConfig = $scopeConfig;

        $this->store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE);
        $this->dillerAPI = new \DillerAPI\DillerAPI($this->store_uid, $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE));

        parent::__construct($context, $data);
    }

    public function get() {
        return $this->dillerAPI->Stores->get($this->store_uid);
    }

    public function getSegments() {
        return $this->dillerAPI->Stores->getSegments($this->store_uid);
    }
    public function getDepartments() {
        return $this->dillerAPI->Stores->getDepartments($this->store_uid);
    }
}
