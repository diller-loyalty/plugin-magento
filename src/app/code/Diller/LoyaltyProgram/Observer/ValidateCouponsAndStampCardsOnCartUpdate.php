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

class ValidateCouponsAndStampCardsOnCartUpdate implements ObserverInterface{
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
     * Validate Stamp Cards and Coupons usage
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

        // get cart items
        $cart_items = $quote->getData('items');
        if(empty($cart_items) && method_exists($observer->getEvent(), "getCart")) $cart_items = $observer->getEvent()->getCart()->getItems()->getData();
        if(empty($cart_items)) return true;

        if(!($customer = $quote->getCustomer())) return true;

        // get member
        if(!($member = $this->loyaltyHelper->searchMemberByCustomerId($customer->getId()))){
            $this->loyaltyHelper->cleanCartPriceRules($quote, true);
            return true;
        }

        // remove stamp card rules from cart
        $this->loyaltyHelper->cleanCartPriceRules($quote, false);

        // get member stamp cards
        $stamp_cards = $this->loyaltyHelper->getMemberStampCards($member->getId());
        if(empty($stamp_cards)) return true;
        $stamp_cards_price_rules = [];

        // get stamp cards rules
        foreach ($stamp_cards as $stamp_card){
            $price_rule = $this->loyaltyHelper->getPriceRule($stamp_card->getExternalId(), "Stamp Card - " . $stamp_card->getTitle());

            if(!$price_rule) continue;

            $stamp_card_rules["price_rule"] = $price_rule;
            $stamp_card_rules["stamps_to_full"] = $stamp_card->getRequiredStamps() - $stamp_card->getStampsCollected();

            $stamp_cards_price_rules[] = $stamp_card_rules;
        }

        // check all stamp cards price rules and select the ones that matched the cart items
        // we're updating the quantity field if more than one product matches the same stamp card
        // we're also saving in the array the "stamps_to_full" in order to validate the free product eligibility in the next step
        if(empty($stamp_cards_price_rules)) return true;
        $applicable_price_rules = [];
        foreach ($stamp_cards_price_rules as $stamp_card_rule){
            $applicable_rule = $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] ?? array("price_rule" => 0, "quantity" => 0, "discount" => 0, "stamps_to_full" => 0);
            $conditions = $stamp_card_rule["price_rule"]->getActionCondition()->getConditions();
            foreach ($cart_items as $cart_item){
                $cart_item['additional_data'] = null;
                if(in_array($cart_item['sku'], explode(",", $conditions[0]->getValue()))){
                    $cart_item['additional_data'] = "eligible_to_stamp_card_discount";
                    $applicable_rule['price_rule'] = $stamp_card_rule["price_rule"];
                    $applicable_rule['quantity'] += $cart_item['qty'];
                    $applicable_rule['stamps_to_full'] = $stamp_card_rule["stamps_to_full"];

                    if($applicable_rule['discount'] == 0) $applicable_rule['discount'] = $cart_item['price'];

                    if($applicable_rule['discount'] > $cart_item['price']){
                        $applicable_rule['discount'] = $cart_item['price'];
                    }
                }
            }
            if(!empty($applicable_rule)) $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] = $applicable_rule;
        }

        // walk through the applicable price rules and apply the valid one
        // when setting the coupon code, the StampCardDiscount.php will be called and the discount will be set based on the cheapest of the eligible products presented in the cart
        if(empty($applicable_price_rules)) return true;
        $discount_given = 0;
        foreach ($applicable_price_rules as $price_rule_data){
            if($price_rule_data['quantity'] >= $price_rule_data['stamps_to_full']){
                if($price_rule_data['discount'] > $discount_given && $coupon_code = $this->generateCouponCode($price_rule_data["price_rule"]->getRuleId(), str_replace(["Stamp Card - "," "], "", $price_rule_data["price_rule"]->getName()))){
                    $discount_given = $price_rule_data['discount'];
                    $price_rule_id = $price_rule_data["price_rule"]->getRuleId();

                    $_SESSION["dillerLoyalty_lastCartCheckedTimeStamp"] = time();

                    $quote
                        ->setAppliedRuleIds($price_rule_id)
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
