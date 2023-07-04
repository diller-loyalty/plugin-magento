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

        if(!($customer = $quote->getCustomer())) return true;

        // Get Diller member
        if(!($member = $this->loyaltyHelper->searchMemberByCustomerId($customer->getId()))){
            $this->loyaltyHelper->cleanCartPriceRules($quote, true);
            return true;
        }

        // Remove stamp card rules from cart
        $this->loyaltyHelper->cleanCartPriceRules($quote, false);

        // Get member stamp cards
        $stamp_cards = $this->loyaltyHelper->getMemberStampCards($member->getId());
        if(empty($stamp_cards)) return true;

        // Get Magento rules related to the member stamp cards
        // this will return result in an array with the Magento price rule and the related stamp card
        $stamp_cards_price_rules = [];
        foreach ($stamp_cards as $stamp_card){
            if(!$price_rule = $this->loyaltyHelper->getPriceRule($stamp_card->getExternalId(), "Stamp Card - " . $stamp_card->getTitle())) continue;

            $stamp_card_rules["price_rule"] = $price_rule;
            $stamp_card_rules["stamp_card"] = $stamp_card;

            $stamp_cards_price_rules[] = $stamp_card_rules;
        }

        // Get cart items
        $cart_items = $quote->getData('items');
        if(empty($cart_items) && method_exists($observer->getEvent(), "getCart")) $cart_items = $observer->getEvent()->getCart()->getItems()->getData();
        if(empty($cart_items)) return true;

        // Run through all stamp cards price rules and select the ones that are eligible with the cart items
        // this will result in an array with the applicable price rules. Each one of them will have the following information
        // - Magento price rule
        // - Diller stamp card id
        // - Total of stamps to add
        // - Discounts (array) will have all the discounts that this stamp will offer, with the product SKU as key and the discount as value
        // -- we need to save this as an array for the cases where there are more than one free product unit and that can happen with 2 or more different products
        // - Eligible products (array) with only the eligible products SKU. Used for easier comparison a few steps ahead
        // - Total discount that we'll need to make sure we set the lowest offer if a member has access to more than one stamp card in the same cart
        if(empty($stamp_cards_price_rules)) return true;
        $applicable_price_rules = [];
        foreach ($stamp_cards_price_rules as $stamp_card_rule){
            $applicable_rule = $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] ?? array(
                "price_rule" => 0,
                "stamp_card_id" => 0,
                "stamps" => 0,
                "discounts" => [],
                "eligible_products" => [],
                "total_discount" => 0
            );

            // Get product details
            // run through the cart items and match that with the price rules details
            // the array $stamp_card_products will be populated with the product sku, it's quantity and the unit price. This will be used in the next step to calculate the stamp card discounts
            $stamp_card_products = $eligible_products = [];
            $conditions = $stamp_card_rule["price_rule"]->getActionCondition()->getConditions();
            $price_rule_products = explode(",", $conditions[0]->getValue());
            foreach ($cart_items as $cart_item){
                if(in_array($cart_item['sku'], $price_rule_products)){
                    $stamp_card_products[] = array("sku" => $cart_item['sku'], "qty" => $cart_item['qty'], "price" => $cart_item['price']);
                    $eligible_products[] = $cart_item['sku'];
                    $applicable_rule['price_rule'] = $stamp_card_rule["price_rule"];
                    $applicable_rule['stamps'] += $cart_item['qty'];
                }
            }
            $applicable_rule['eligible_products'] = $eligible_products;

            // sort product array in an ascending price order and check if the stamps are enough to a free product
            if(!empty($stamp_card_products) && count($stamp_card_products) > 1){
                usort($stamp_card_products, fn($a, $b) => (int) ($a['price'] > $b['price']));
            }

            // calculate discounts
            $discounts = [];
            $total_discount = 0;
            if(!empty($stamp_card_products)){
                // check if the stamps are enough to give free products
                $stamps_to_full = $stamp_card_rule['stamp_card']->getRequiredStamps() - $stamp_card_rule['stamp_card']->getStampsCollected();
                if($applicable_rule['stamps'] >= $stamps_to_full){
                    $free_products = 1;

                    // if the stamp card is restartable, check if there is enough stamps to give more free products
                    if($stamp_card_rule['stamp_card']->getIsRestartable()){
                        $remaining_stamps = $applicable_rule['stamps'] - $stamps_to_full;
                        $free_products += floor($remaining_stamps / $stamp_card_rule['stamp_card']->getRequiredStamps());
                    }

                    for ($i = 0; $i < $free_products; $i++) {
                        $product = reset($stamp_card_products);
                        $product_sku = $product['sku'];
                        $discount = $discounts[$product_sku] ?? 0;
                        $discount += $product['price'];
                        $discounts[$product_sku] = $discount;

                        $total_discount += $stamp_card_products[0]['price'];

                        // update product qty for correct future discounts
                        $stamp_card_products[0]['qty'] -= 1;
                        if($stamp_card_products[0]['qty'] == 0) array_shift($stamp_card_products);
                    }
                }
            }
            $applicable_rule['discounts'] = $discounts;
            $applicable_rule['total_discount'] = $total_discount;

            if(!empty($applicable_rule)) $applicable_price_rules[$stamp_card_rule["price_rule"]->getRuleId()] = $applicable_rule;
        }

        // walk through the applicable price rules and apply the valid and cheapest one
        // when setting the coupon code, the StampCardDiscount.php will be called and the discount will be set based on the cheapest of the eligible products presented in the cart
        if(empty($applicable_price_rules)) return true;
        $discount_given = 0;
        foreach ($applicable_price_rules as $price_rule_data){
            if(!empty($price_rule_data['discounts'])){
                if(($discount_given == 0 || ($discount_given > 0 && $price_rule_data['total_discount'] < $discount_given)) &&
                    $coupon_code = $this->generateCouponCode($price_rule_data["price_rule"]->getRuleId(), str_replace(["Stamp Card - "," "], "", $price_rule_data["price_rule"]->getName()))
                ){
                    $discount_given = $price_rule_data['total_discount'];
                    $price_rule_id = $price_rule_data["price_rule"]->getRuleId();

                    foreach ($cart_items as $cart_item){
                        $cart_item['additional_data'] = null;
                        if(in_array($cart_item['sku'], $price_rule_data['eligible_products'])){
                            $cart_item['additional_data'] = json_encode($price_rule_data);
                        }
                    }

                    // save timestamp to avoid looping this observer
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
