<?php

namespace Diller\LoyaltyProgram\Helper;

use DillerAPI\DillerAPI;
use DillerAPI\ApiException;
use DillerAPI\Model\CouponReservationRequest;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

use libphonenumber\PhoneNumberUtil;
use Exception;

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
     * @var String
     */
    private $store_uid;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(Context $context, ScopeConfigInterface $scopeConfig, CustomerRepositoryInterface $customerRepository) {
        $this->scopeConfig = $scopeConfig;
        $this->customerRepository = $customerRepository;

        $configs = clone \DillerAPI\Configuration::getDefaultConfiguration();
        // to set module to production mode
        // $configs->setHost("https://api.dillerapp.com");

        $configs->setUserAgent("DillerLoyaltyPlugin/Magento v1.0.0");

        $this->store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE) ?? '';

        if($this->store_uid){
            $api_key = $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE);
            try {
                $this->dillerAPI = new \DillerAPI\DillerAPI($this->store_uid, $api_key);
            }
            catch (ApiException $ex){
            }
        }
        parent::__construct($context);
    }

    // ------------------------------------------------------------------------------
    // --------------------------------------> STORE
    // ------------------------------------------------------------------------------
    public function getLoyaltyDetails() {
        try {
            return $this->dillerAPI->Stores->get($this->store_uid);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function getStoreMembershipLevels() {
        try {
            return $this->dillerAPI->MembershipLevel->getStoreMembershipLevel($this->store_uid);
        }
        catch (Exception $ex){
            return false;
        }
    }

    public function getStoreSegments() {
        try {
            return $this->dillerAPI->Stores->getSegments($this->store_uid);
        }
        catch (Exception $ex){
            return false;
        }
    }

    public function getStoreDepartments() {
        try {
            return $this->dillerAPI->Stores->getDepartments($this->store_uid);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function getStoreCoupons() {
        try {
            return $this->dillerAPI->Coupons->getStoreCoupons($this->store_uid);
        }
        catch (Exception $ex){
            return false;
        }
    }

    public function getSelectedDepartment() {
        try {
            return $this->scopeConfig->getValue('dillerloyalty/settings/department', ScopeInterface::SCOPE_STORE);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function getSelectedOrderStatus(){
        try {
            return $this->scopeConfig->getValue('dillerloyalty/settings/transaction_status', ScopeInterface::SCOPE_STORE);
        }
        catch (Exception $ex){
            return false;
        }
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
        try {
            return $this->dillerAPI->Members->getMemberByFilter($this->store_uid, $email, $phone);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function getMemberCoupons($member_id){
        try {
            return $this->dillerAPI->Coupons->getMemberCoupons($this->store_uid, $member_id);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function validateMemberCoupon($member_id, $coupon){
        try {
            return $this->dillerAPI->Coupons->validateCoupon($this->store_uid, $member_id, $coupon);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function reserveMemberCoupon($member_id, $coupon, $order_id){
        try {
            $reservationRequest = new CouponReservationRequest(array("channel" => "Magento", "externalTransactionId" => $order_id));
            return $this->dillerAPI->Coupons->reserveCoupon($this->store_uid, $member_id, $coupon, $reservationRequest);
        }
        catch (Exception $ex){
            return false;
        }
    }

    public function registerMember($data){
        try {
            return $this->dillerAPI->Members->registerMember($this->store_uid, $data);
        }
        catch (Exception $ex){
            return false;
        }
    }
    public function updateMember($member_id, $data){
        try {
            return $this->dillerAPI->Members->updateMember($this->store_uid, $member_id, $data);
        }
        catch (Exception $ex){
            return false;
        }
    }

    public function createTransaction($member_id, $data){
        try {
            return $this->dillerAPI->Transactions->createTransaction($this->store_uid, $member_id, $data);
        }
        catch (Exception $ex){
            return false;
        }
    }

    // ----------------------------------------------------> Magento customer related methods

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function searchMemberByCustomerId($id){
        $customer = false;
        try {
            $customer = $this->customerRepository->getById($id);
        }
        catch (NoSuchEntityException $ex){
        }

        if($customer){
            // search member with diller_member_id customer attribute
            if($attribute = $customer->getCustomAttribute('diller_member_id')){
                if($member = $this->getMemberById($attribute->getValue())){
                    return $member;
                }
            }

            if($customer_phone_number = $this->getCustomerPhoneNumber($id)){
                $result = $this->getMember('', $customer_phone_number['country_code'].$customer_phone_number['national_number']);
                if(!empty($result)){
                    return $result[0];
                }
            };

            // search member by customer email
            $result = $this->getMember($customer->getEmail());
            if(!empty($result)){
                return $result[0];
            }
        }
        return false;
    }

    public function getCustomerPhoneNumber($id){
        if($customer = $this->customerRepository->getById($id)) {
            if ($addresses = $customer->getAddresses()) {
                foreach ($addresses as $customer_address) {
                    if($phone_number = $this->getPhoneNumberFromAddress($customer_address)){
                        return $phone_number;
                    }
                }
            }
        }
        return false;
    }

    public function getPhoneNumberFromAddress($address){
        if(empty($address->getTelephone())){
            return false;
        }

        // Get address phone number
        $customerPhone = preg_replace("/[^0-9+]/", "", $address->getTelephone() ?? "");
        $country_code = $country_code ?? $address->getCountryId() ?? "NO";

        // Check if phone is in international format
        if (preg_match("/^(\+|00)/", $customerPhone)) {
            $country_code = "";
            $customerPhone = preg_replace("/^00/", "+", $customerPhone);
        }

        try {
            if (($phone_number_proto = PhoneNumberUtil::getInstance()->parse($customerPhone, $country_code)) && PhoneNumberUtil::getInstance()->isValidNumber($phone_number_proto)) {
                $phone_country_code = '+' . $phone_number_proto->getCountryCode();
                $phone_national_number = $phone_number_proto->getNationalNumber();

                return array(
                    "country_code" => $phone_country_code,
                    "national_number" => $phone_national_number);
            }
        } catch (Exception $ex) {
            return false;
        }
        return false;
    }
}
