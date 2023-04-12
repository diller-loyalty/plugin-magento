<?php

namespace Diller\LoyaltyProgram\Helper;

use Exception;
use DillerAPI\DillerAPI;
use DillerApi\Configuration;
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


        //$configs = clone Configuration::getDefaultConfiguration();
        // production host
        // $configs->setHost("https://api.dillerapp.com");
        //$configs->setUserAgent("DillerLoyaltyPlugin/Magento v1.0.0");

        $this->store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE);
        $this->dillerAPI = new DillerAPI($this->store_uid, $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE));

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

    public function getSelectedDepartment() {
        return $this->scopeConfig->getValue('dillerloyalty/settings/department', ScopeInterface::SCOPE_STORE);
    }
    public function getSelectedOrderStatus(){
        return $this->scopeConfig->getValue('dillerloyalty/settings/transaction_status', ScopeInterface::SCOPE_STORE);
    }


    // ------------------------------------------------------------------------------
    // --------------------------------------> MEMBER
    // ------------------------------------------------------------------------------
    public function getMemberById($id){
        try {
            return $this->dillerAPI->Members->getMemberById($this->store_uid, $id);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function getMember($email = '', $phone = ''){
        return $this->dillerAPI->Members->getMemberByFilter($this->store_uid, $email, $phone);
    }
    public function getMemberCoupons($member_id){
        return $this->dillerAPI->Coupons->getMemberCoupons($this->store_uid, $member_id);
    }

    public function registerMember($data){
        return $this->dillerAPI->Members->registerMember($this->store_uid, $data);
    }
    public function updateMember($member_id, $data){
        return $this->dillerAPI->Members->updateMember($this->store_uid, $member_id, $data);
    }

    public function createTransaction($member_id, $data){
        return $this->dillerAPI->Transactions->createTransaction($this->store_uid, $member_id, $data);
    }
}
