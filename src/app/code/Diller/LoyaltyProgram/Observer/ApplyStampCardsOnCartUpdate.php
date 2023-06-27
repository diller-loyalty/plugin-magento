<?php

namespace Diller\LoyaltyProgram\Observer;

use Diller\LoyaltyProgram\Helper\Data;

use Magento\Framework\Registry;
use Magento\Customer\Model\Customer;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\App\RequestInterface;
use Magento\SalesRule\Model\CouponGenerator;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;

class ApplyStampCardsOnCartUpdate implements ObserverInterface{
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
     * Apply Stamp Cards and Coupons if member is eligible
     *
     * @param EventObserver $observer
     * @return true
     */
    public function execute(EventObserver $observer){
        if(!($quote = $observer->getEvent()->getData('cart')->getData('quote'))) return true;
        if(!($customer = $quote->getCustomer())) return true;

        // remove stamp card rules from cart
        if($rule_id = $observer->getEvent()->getData('cart')->getData('quote')->getData("applied_rule_ids")){
            $price_rule = $this->ruleRepository->getById($rule_id);
            if(str_contains($price_rule->getName(), "Stamp card")){
                $observer->getEvent()->getData('cart')->getData('quote')
                    ->setData("applied_rule_ids", '');
                $observer->getEvent()->getData('cart')->getData('quote')
                    ->setCouponCode('')
                    ->collectTotals()
                    ->save();
            }
        }

        // get member
        if(!($member = $this->loyaltyHelper->searchMemberByCustomerId($customer->getId()))) return true;

        // get member stamp cards
        $stamp_cards = $this->loyaltyHelper->getMemberStampCards($member->getId());
        if(empty($stamp_cards)) return true;
        $stamp_cards_price_rules = [];

        // get stamp cards rules
        foreach ($stamp_cards as $stamp_card){
            $price_rule = false;
            try {
                if(!empty($stamp_card->getExternalId())){
                    $price_rule = $this->ruleRepository->getById($stamp_card->getExternalId());
                }
                if(!$price_rule){
                    $price_rule = $this->loyaltyHelper->getPriceRuleByName("Stamp card - " . $stamp_card->getTitle());
                }
            } catch(LocalizedException $ex){}
            if(!$price_rule) continue;

            $stamp_card_rules["price_rule"] = $price_rule;
            $stamp_card_rules["stamps_to_full"] = $stamp_card->getRequiredStamps() - $stamp_card->getStampsCollected();

            $stamp_cards_price_rules[] = $stamp_card_rules;
        }

        // get cart items
        $cart_items = $quote->getData('items');
        if(empty($cart_items)) return true;

        // check all stamp cards price rules and select the ones that matched the cart items
        // we're updating the quantity field if more than one product matches the same stamp card
        // we're also saving in the array the "stamps_to_full" in order to validate the free product eligibility in the next step
        if(empty($stamp_cards_price_rules)) return true;
        $applicable_price_rules = [];
        foreach ($stamp_cards_price_rules as $stamp_card_rule){
            $applicable_rule = $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] ?? array("price_rule" => 0, "quantity" => 0, "stamps_to_full" => 0);
            $conditions = $stamp_card_rule["price_rule"]->getActionCondition()->getConditions();
            foreach ($cart_items as $cart_item){
                if(in_array($cart_item->getSku(), explode(",", $conditions[0]->getValue()))){
                    $cart_item->setAdditionalData("eligible_to_stamp_card_discount");
                    $applicable_rule['price_rule'] = $stamp_card_rule["price_rule"];
                    $applicable_rule['quantity'] += $cart_item->getQty();
                    $applicable_rule['stamps_to_full'] = $stamp_card_rule["stamps_to_full"];
                }else{
                    $cart_item->setAdditionalData(null);
                }
            }
            if(!empty($applicable_rule)) $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] = $applicable_rule;
        }

        // walk through the applicable price rules and apply the valid one
        // when setting the coupon code, the StampCardDiscount.php will be called and the discount will be set based on the cheapest of the eligible products presented in the cart
        if(empty($applicable_price_rules)) return true;
        foreach ($applicable_price_rules as $price_rule_data){
            if($price_rule_data['quantity'] >= $price_rule_data['stamps_to_full']){
                if($coupon_code = $this->generateCouponCode($price_rule_data["price_rule"]->getRuleId(), str_replace(["Stamp card - "," "], "", $price_rule_data["price_rule"]->getName()))){
                    $observer->getEvent()->getData('cart')->getData('quote')
                        ->setCouponCode($coupon_code)
                        ->collectTotals()
                        ->save();
                }
            }
        }

        return true;
    }

    private function generateCouponCode(int $ruleId, string $stampCardName): bool|string{
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
