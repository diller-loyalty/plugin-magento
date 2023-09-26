<?php

namespace Diller\LoyaltyProgram\Helper;

use DillerAPI\DillerAPI;
use DillerAPI\ApiException;
use DillerAPI\Configuration;
use DillerAPI\Model\StoreResponse;
use DillerAPI\Model\StampReservationRequest;
use DillerAPI\Model\CouponReservationRequest;
use DillerAPI\Model\LoginOtpVerificationRequest;

use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Api\CouponRepositoryInterface;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;

use Magento\Customer\Model\Customer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\CustomerFactory;


use libphonenumber\PhoneNumberUtil;
use Exception;

/**
 * Diller API helper
 *
 * @author      Diller AS <dillertechsupport@diller.no>
 */

class Data extends AbstractHelper{
    private DillerAPI $dillerAPI;
    private String $store_uid;

    protected Customer $customer;
    protected CustomerFactory $customerFactory;
    protected CustomerRepositoryInterface $customerRepository;

    protected ProductRepositoryInterface $productRepository;

    protected RuleRepository $ruleRepository;
    protected ObjectManagerInterface $_objectManager;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Customer $customer
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProductRepositoryInterface $productRepository
     * @param RuleRepository $ruleRepository
     * @param ObjectManagerInterface $_objectManager
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Customer $customer,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        RuleRepository $ruleRepository,
        ObjectManagerInterface $_objectManager) {

        $this->scopeConfig = $scopeConfig;
        $this->customer = $customer;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->ruleRepository = $ruleRepository;
        $this->_objectManager = $_objectManager;

        $configs = clone Configuration::getDefaultConfiguration();
        // to set module to production mode
        $configs->setHost("https://api.diller.app");
        if($this->scopeConfig->getValue('dillerloyalty/settings/test_environment', ScopeInterface::SCOPE_STORE)){
            $configs->setHost("https://api.prerelease.dillerapp.com");
        }

        $configs->setUserAgent("DillerLoyaltyPlugin/Magento v1.0.0");

        $this->store_uid = $this->scopeConfig->getValue('dillerloyalty/settings/store_uid', ScopeInterface::SCOPE_STORE) ?? '';

        if($this->store_uid){
            $api_key = $this->scopeConfig->getValue('dillerloyalty/settings/api_key', ScopeInterface::SCOPE_STORE);
            try {
                $this->dillerAPI = new DillerAPI($this->store_uid, $api_key, $configs);
            }
            catch (Exception $ex){
                $this->store_uid = false;
            }
        }

        parent::__construct($context);
    }

    private function isConnected(): bool
    {
        if(!$this->store_uid) return false;
        return true;
    }

    public function isEnabled(): bool{
        if(!$this->store_uid) return false;
        return $this->scopeConfig->getValue('dillerloyalty/settings/loyalty_program_enabled', ScopeInterface::SCOPE_STORE) ?? 0;
    }

    public function areLoyaltyFieldsMandatory(): bool{
        return $this->scopeConfig->getValue('dillerloyalty/settings/loyalty_fields_mandatory', ScopeInterface::SCOPE_STORE) ?? 0;
    }

    public function reserveStampCards(): bool{
        return $this->scopeConfig->getValue('dillerloyalty/settings/reserve_stamp_cards', ScopeInterface::SCOPE_STORE) ?? 0;
    }

    // ------------------------------------------------------------------------------
    // --------------------------------------> STORE
    // ------------------------------------------------------------------------------
    public function getLoyaltyDetails(): bool|StoreResponse
    {
        if(!$this->isConnected()) return false;
        try {
            return $this->dillerAPI->Stores->get($this->store_uid);
        }
        catch (ApiException){
            return false;
        }
    }
    public function getStoreMembershipLevels() {
        try {
            return $this->dillerAPI->MembershipLevel->getStoreMembershipLevel($this->store_uid);
        }
        catch (Exception){
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
        catch (Exception){
            return false;
        }
    }

    public function getSelectedDepartment() {
        try {
            return $this->scopeConfig->getValue('dillerloyalty/settings/department', ScopeInterface::SCOPE_STORE);
        }
        catch (Exception){
            return false;
        }
    }
    public function getSelectedOrderStatus(){
        try {
            return $this->scopeConfig->getValue('dillerloyalty/settings/transaction_status', ScopeInterface::SCOPE_STORE);
        }
        catch (Exception){
            return false;
        }
    }


    // ------------------------------------------------------------------------------
    // --------------------------------------> MEMBER
    // ------------------------------------------------------------------------------
    public function getMemberById($id){
        if(!$this->isConnected()) return false;
        try {
            return $this->dillerAPI->Members->getMemberById($this->store_uid, $id);
        }
        catch (Exception){
            return false;
        }
    }
    public function getMember($email = '', $phone = ''){
        if(!$this->isConnected()) return false;
        try {
            return $this->dillerAPI->Members->getMemberByFilter($this->store_uid, $email, $phone);
        }
        catch (Exception){
            return false;
        }
    }
    public function searchMemberByActivationToken($token){
        if(!$this->isConnected()) return false;
        try {
            return $this->dillerAPI->Members->getMemberByFilter($this->store_uid, null, null, null, $token);
        }
        catch (Exception){
            return false;
        }
    }
    public function getMemberCoupons($member_id){
        try {
            return $this->dillerAPI->Coupons->getMemberCoupons($this->store_uid, $member_id);
        }
        catch (Exception){
            return false;
        }
    }
    public function validateMemberCoupon($member_id, $coupon){
        try {
            return $this->dillerAPI->Coupons->validateCoupon($this->store_uid, $member_id, $coupon);
        }
        catch (Exception){
            return false;
        }
    }
    public function reserveMemberCoupon($member_id, $coupon, $order_id){
        try {
            $reservationRequest = new CouponReservationRequest(array("channel" => "Magento", "externalTransactionId" => $order_id));
            return $this->dillerAPI->Coupons->reserveCoupon($this->store_uid, $member_id, $coupon, $reservationRequest);
        }
        catch (Exception){
            return false;
        }
    }

    public function getMemberStampCards($member_id){
        try {
            return $this->dillerAPI->StampCards->getMemberStampCards($this->store_uid, $member_id);
        }
        catch (Exception){
            return false;
        }
    }
    public function reserveMemberStampCard($member_id, $stamp_id, $order_id){
        try {
            $reservationRequest = new StampReservationRequest(array("channel" => "Magento", "externalTransactionId" => $order_id));
            return $this->dillerAPI->Coupons->reserveCoupon($this->store_uid, $member_id, $stamp_id, $reservationRequest);
        }
        catch (Exception){
            return false;
        }
    }

    public function registerMember($data){
        try {
            return $this->dillerAPI->Members->registerMember($this->store_uid, $data);
        }
        catch (Exception){
            return false;
        }
    }
    public function updateMember($member_id, $data){
        try {
            return $this->dillerAPI->Members->updateMember($this->store_uid, $member_id, $data);
        }
        catch (Exception){
            return false;
        }
    }
    public function deleteMember($member_id){
        try {
            return $this->dillerAPI->Members->deleteMember($this->store_uid, $member_id);
        }
        catch (Exception){
            return false;
        }
    }

    public function sendLoginOTP($member_id){
        try {
            return $this->dillerAPI->Members->loginOTP($this->store_uid, $member_id);
        }
        catch (Exception){
            return false;
        }
    }
    public function loginOTPVerification($member_id, $otp)
    {
        try {
            return $this->dillerAPI->Members->loginOtpVerification($this->store_uid, $member_id, new LoginOtpVerificationRequest(array("otpCode" => $otp)));
        }
        catch (Exception){
            return false;
        }
    }

    public function createTransaction($member_id, $data){
        try {
            return $this->dillerAPI->Transactions->createTransaction($this->store_uid, $member_id, $data);
        }
        catch (ApiException){
            return false;
        }
    }

    // ----------------------------------------------------> Magento specific related methods

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function searchMemberByCustomerId($id){
        $customer = false;
        try {
            $customer = $this->customerRepository->getById($id);
        }
        catch (NoSuchEntityException $ex){}

        if($customer){
            // search member with diller_member_id customer attribute
            if($attribute = $customer->getCustomAttribute('diller_member_id')){
                if($member = $this->getMemberById($attribute->getValue())){
                    if($member->getConsent()->getGdprAccepted()) return $member;
                }
            }

            if($customer_phone_number = $this->getCustomerPhoneNumber($id)){
                $result = $this->getMember('', $customer_phone_number['country_code'].$customer_phone_number['national_number']);
                if(!empty($result)){
                    $this->addMemberIdToCustomer($id, $result[0]->getID());
                    return $result[0];
                }
            }
            if($customer->getCustomAttribute('diller_member_id') !== null) $this->addMemberIdToCustomer($id, null);
        }

        return false;
    }

    public function getCustomerPhoneNumber($id){
        try {
            if($customer = $this->customerRepository->getById($id)) {
                if ($addresses = $customer->getAddresses()) {
                    foreach ($addresses as $customer_address) {
                        if($phone_number = $this->getPhoneNumberFromAddress($customer_address)){
                            return $phone_number;
                        }
                    }
                }
            }
        }
        catch (NoSuchEntityException|LocalizedException) {}

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

    public function getMagentoProduct($id, $sku = ''){
        if(!empty($id)){
            try {
                return $this->productRepository->getById($id);
            } catch (NoSuchEntityException $ex) {}
        }

        if(!empty($sku)){
            try {
                return $this->productRepository->get($sku);
            } catch (NoSuchEntityException) {}
        }

        return false;
    }


    public function validateOrderCoupons($member_id, $coupon, $products): array
    {
        if($member_coupon = $this->validateMemberCoupon($member_id, $coupon)) {
            if ($member_coupon->getIsOk()) {
                return array("coupons" => [$coupon], "stamp_cards" => []);
            }
        }

        // check if this coupon matches a stamp card price rule
        $stamp_card_found = false;
        $validated_stamp_cards = [];
        if($price_rule = $this->getPriceRule('', '', $coupon)){
            foreach ($this->getMemberStampCards($member_id) as $stamp_card) {
                if ($price_rule->getRuleId() == $stamp_card->getExternalId() || $price_rule->getName() == 'Stamp Card - ' . $stamp_card->getTitle()) {
                    $stamp_card_found = $stamp_card;
                }
            }
            if($stamp_card_found){
                $stamps = 0;
                foreach ($products as $product){
                    if($product->getAdditionalData() === 'eligible_to_stamp_card_discount'){
                        $product_qty = 0;
                        if(method_exists($product, "getQty")) $product_qty = $product->getQty();
                        if(array_key_exists("qty_ordered", $product->getData()) && $product_qty == 0) $product_qty = $product->getData("qty_ordered");
                        $stamps += $product_qty;
                    }
                }
                if($stamps > 0){
                    $validated_stamp_cards = array_fill(0, $stamps, $stamp_card_found->getId());
                }
            }
        }
        return array("coupons" => [], "stamp_cards" => $validated_stamp_cards);
    }

    public function getPriceRuleStoreCoupon(int $rule_id, string $rule_name, string $rule_coupon_code){
        $coupons = $this->getStoreCoupons();
        if(empty($coupons)) return false;

        foreach ($coupons as $coupon){
            foreach ($coupon->getExternalIds() as $external_id){
                if($rule_id === $external_id || $rule_name == $coupon->getTitle() || $rule_coupon_code == $coupon->getCode()){
                    return $coupon;
                }
            }
        }
        return false;
    }

    public function cleanCartPriceRules($quote, $cleanCoupons): void
    {
        $rule_id = $quote->getData("applied_rule_ids");
        $coupon_code = $quote->getCouponCode();

        $removePriceRule = false;

        if($price_rule = $this->getPriceRule($rule_id, '', $coupon_code)){
            if($price_rule->getSimpleAction() === 'loyalty_stamp_card'){
                $removePriceRule = true;

                // remove "eligible_to_stamp_card_discount" from cart products
                $cart_items = $quote->getData('items');
                if(!empty($cart_items)){
                    foreach ($cart_items as $cart_item) {
                        $cart_item['additional_data'] = "";
                    }
                }
            }

            if($cleanCoupons){
                if($loyalty_coupon = $this->getPriceRuleStoreCoupon($price_rule->getRuleId(), $price_rule->getName(), $quote->getCouponCode())){
                    if($loyalty_coupon->getType() != "Public"){
                        $removePriceRule = true;
                    }
                }
            }
        }

        if($removePriceRule){
            $quote
                ->setData("applied_rule_ids", '')
                ->setCouponCode('')
                ->collectTotals()
                ->save();
        }
    }


    // Magento price rules
    public function getPriceRule($id, $name = '', $promo_code = ''){
        if(!empty($id)){
            try {
                return $this->ruleRepository->getById($id);
            } catch (NoSuchEntityException|LocalizedException) {}
            return false;
        }

        if(!empty($name)){
            try {
                if($price_rule = $this->getPriceRuleByName($name)) return $price_rule;
            } catch (NoSuchEntityException|LocalizedException) {}
        }

        if(!empty($promo_code)){
            try {
                return $this->getPriceRuleByPromoCode($promo_code);
            } catch (NoSuchEntityException|LocalizedException) {}
        }

        return false;
    }

    private function getPriceRuleByPromoCode($promo_code){
        $filter = new Filter();
        $filter->setField('code')->setValue($promo_code);

        $searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter($filter)->create();

        /** @var CouponRepositoryInterface $couponRepository */
        $couponRepository = $this->_objectManager->get(CouponRepositoryInterface::class);
        if(($items = $couponRepository->getList($searchCriteria)->getItems()) && !empty($items)){
            foreach ($items as $item){
                if($price_rule = $this->getPriceRule($item['rule_id'])){
                    return $price_rule;
                }
            }
        }

        return false;
    }

    private function getPriceRuleByName(string $name){
        $filter = new Filter();
        $filter->setField('name')->setValue($name);
        $filterGroupBuilder = $this->_objectManager->get(\Magento\Framework\Api\Search\FilterGroupBuilder::class);
        $filterGroup = $filterGroupBuilder->create();
        $filterGroup->setFilters([$filter]);

        $searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->create();
        $searchCriteria->setFilterGroups([$filterGroup]);

        /** @var CartRepositoryInterface $quoteRepository */
        $ruleRepository = $this->_objectManager->get(RuleRepositoryInterface::class);
        try {
            if(($items = $ruleRepository->getList($searchCriteria)->getItems()) && !empty($items)){
                foreach ($items as $item){
                    return $this->getPriceRule($item->getRuleId());
                }
            }
        }catch(LocalizedException){}

        return false;
    }

    public function addMemberIdToCustomer($customer_id, $member_id){
        if($customer = $this->customer->load($customer_id)){
            $customerData = $customer->getDataModel();
            $customerData->setCustomAttribute('diller_member_id',$member_id);
            $customer->updateData($customerData);
            $customerResource = $this->customerFactory->create();
            $customerResource->saveAttribute($customer, 'diller_member_id');
        }
    }

    public function getPriceRulesForMemberStampCards($member_id): array
    {
        $validated_stamp_cards = [];
        $stamp_cards = $this->getMemberStampCards($member_id);
        if(empty($stamp_cards)) return $validated_stamp_cards;

        foreach ($stamp_cards as $stamp_card){
            if($price_rule = $this->getPriceRule($stamp_card->getExternalID(), "Stamp Card - " . $stamp_card->getTitle())){
                if($price_rule->getSimpleAction() !== 'loyalty_stamp_card') continue;

                $price_rule_conditions = $price_rule->getActionCondition()->getConditions();
                if(empty($price_rule_conditions)) continue;

                $validated_stamp_cards[] = array(
                    "id" => $stamp_card->getID(),
                    "products" => explode(",", $price_rule_conditions[0]->getValue())
                );
            }
        }
        return $validated_stamp_cards;
    }
}
