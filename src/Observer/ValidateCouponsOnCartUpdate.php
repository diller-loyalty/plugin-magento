<?php

namespace Diller\LoyaltyProgram\Observer;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;
use Magento\SalesRule\Model\CouponGenerator;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;

class ValidateCouponsOnCartUpdate implements ObserverInterface{
    protected Customer $customer;
    protected Data $loyaltyHelper;
    protected Registry $_coreRegistry;
    protected RequestInterface $request;
    protected ObjectManagerInterface $_objectManager;
    protected RuleRepository $ruleRepository;
    protected CouponGenerator $couponGenerator;
    protected ConditionInterfaceFactory $conditionFactory;
    protected CouponRepositoryInterface $couponRepository;
    protected CustomerRepositoryInterface $customerRepository;

    /**
     * @var PriceCurrencyInterface
     */
    protected $_priceCurrency;

    /**
     * @var RuleFactory
     */
    protected RuleFactory $ruleFactory;

    public function __construct(
        Customer $customer,
        Data $loyaltyHelper,
        Registry $coreRegistry,
        RuleFactory $ruleFactory,
        RequestInterface $request,
        RuleRepository $ruleRepository,
        CouponGenerator $couponGenerator,
        PriceCurrencyInterface $priceCurrency,
        ObjectManagerInterface $_objectManager,
        ConditionInterfaceFactory $conditionFactory,
        CouponRepositoryInterface $couponRepository,
        CustomerRepositoryInterface $customerRepository) {
        $this->request = $request;
        $this->customer = $customer;
        $this->ruleFactory = $ruleFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->loyaltyHelper = $loyaltyHelper;
        $this->_priceCurrency = $priceCurrency;
        $this->_objectManager = $_objectManager;
        $this->ruleRepository = $ruleRepository;
        $this->couponGenerator = $couponGenerator;
        $this->couponRepository = $couponRepository;
        $this->conditionFactory = $conditionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Validate Coupons usage
     *
     * @param EventObserver $observer
     * @return true
     */
    public function execute(EventObserver $observer){
        if(array_key_exists("dillerLoyalty_lastCartCheckedTimeStamp", $_SESSION)) {
            if ((time() - $_SESSION["dillerLoyalty_lastCartCheckedTimeStamp"]) < 5) return true;
        }
        if($event_data = $observer->getEvent()->getData()){
            if(array_key_exists("cart", $event_data)){
                if(!($quote = $observer->getEvent()->getData('cart')->getData('quote'))) return true;
            }
            if(array_key_exists("quote", $observer->getEvent()->getData())){
                if(!($quote = $observer->getEvent()->getData('quote'))) return true;
            }
        }
        if(!$quote) return true;

        $is_member = $valid_coupon = false;

        // get coupons from cart
        if(!($cart_coupon = $quote->getCouponCode())) return true;

        // get coupons from diller store
        if(!($store_coupons = $this->loyaltyHelper->getStoreCoupons()) || empty($store_coupons)) return true;

        // get member
        if($customer = $quote->getCustomer()) {
            if($member = $this->loyaltyHelper->searchMemberByCustomerId($customer->getId())) $is_member = true;
        }

        // validate cart coupon
        if($is_member){
            if($validation = $this->loyaltyHelper->validateMemberCoupon($member->getId(), $cart_coupon)){
                if($validation->getIsOk()) return true;
            }
        }
        foreach($store_coupons as $coupon){
            if($coupon->getCode() == $cart_coupon && $coupon->getType() == 'Public') return true;
        }

        // if coupon not valid so far, remove from cart and save timestamp to avoid looping this observer
        $_SESSION["dillerLoyalty_lastCartCheckedTimeStamp"] = time();
        $quote->setCouponCode(null)->setAppliedRuleIds(null)->collectTotals()->save();

        return true;
    }

    private function generateCouponCode(int $ruleId, string $stampCardName) {
        try {
            $rule = $this->ruleRepository->getById($ruleId);
        } catch(LocalizedException $ex){
            return false;
        }

        $data = [
            'rule_id' => $rule->getRuleId(),
            'qty' => '1',
            'length' => '6', //change to your requirements
            'format' => 'alphanum', //options alphanum, num, alpha
            'prefix' => strtoupper($stampCardName) . '_',
            'suffix' => '',
        ];
        return $this->couponGenerator->generateCodes($data)[0];
    }
}
