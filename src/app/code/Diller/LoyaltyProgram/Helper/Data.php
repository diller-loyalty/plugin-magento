<?php

namespace Diller\LoyaltyProgram\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Diller API helper
 *
 * @author      Diller AS <dillertechsupport@diller.no>
 */

class Data extends AbstractHelper{
    /**
     * @var DillerAPI
     */
    private $dillerAPI;

    /**
     * @var StoreUID
     */
    private $store_uid;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE);
        $this->dillerAPI = new \DillerAPI\DillerAPI($this->store_uid, $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE));

        parent::__construct($context);
    }

    // ------------------------------------------------------------------------------
    // --------------------------------------> STORE
    // ------------------------------------------------------------------------------
    public function getLoyaltyDetails() {
        return $this->dillerAPI->Stores->get($this->store_uid);
    }

    public function getStoreMembershipLevels() {
        return $this->dillerAPI->MembershipLevel->getStoreMembershipLevel($this->store_uid);
    }

    public function getStoreSegments() {
        return $this->dillerAPI->Stores->getSegments($this->store_uid);
    }

    public function getStoreDepartments() {
        return $this->dillerAPI->Stores->getDepartments($this->store_uid);
    }


    // ------------------------------------------------------------------------------
    // --------------------------------------> MEMBER
    // ------------------------------------------------------------------------------
    public function getMemberById($id){
        return $this->dillerAPI->Members->getMemberById($this->store_uid, $id);
    }
    public function getMember($email = '', $phone = ''){
        return $this->dillerAPI->Members->getMemberByFilter($this->store_uid, $email, $phone);
    }
    public function getMemberCoupons($member_id){
        return $this->dillerAPI->Coupons->getMemberCoupons($this->store_uid, $member_id);
    }
}
