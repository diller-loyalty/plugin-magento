<?php
namespace Diller\LoyaltyProgram\Model;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\ObjectManagerInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\SalesRule\Api\Data\ConditionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection;

class CouponManagement {
    protected Data $loyaltyHelper;
    protected ObjectManagerInterface $_objectManager;
    protected RuleFactory $ruleFactory;
    protected RuleRepository $ruleRepository;
    protected ConditionInterfaceFactory $conditionFactory;
    protected Collection $customerGroupCollection;
    protected ProductRepositoryInterface $productRepository;
    protected StoreManagerInterface $storeManager;

    public function __construct(
        Data $loyaltyHelper,
        ObjectManagerInterface $_objectManager,
        RuleFactory $ruleFactory,
        RuleRepository $ruleRepository,
        ConditionInterfaceFactory $conditionFactory,
        Collection $customerGroupCollection,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager) {
        $this->loyaltyHelper = $loyaltyHelper;
        $this->_objectManager = $_objectManager;
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
        $this->conditionFactory = $conditionFactory;
        $this->customerGroupCollection = $customerGroupCollection;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws InputException
     */
    public function setCoupon($details){
        $price_rule = $price_rule_id = false;
        $website_id = $this->storeManager->getStore()->getId();

        // search price rule
        $price_rule = $this->loyaltyHelper->getPriceRule($details['external_id'] ?? '', $details['promo_code'] ?? '');

        // if a price rule was found and the delete flag sent
        if($price_rule && (array_key_exists("delete", $details) && $details['delete'])){
            $price_rule->delete();
            return "Rule deleted";
        }

        // create new price rule
        if (!$price_rule) $price_rule = $this->ruleFactory->create();

        // General rule data
        $price_rule
            ->setName($details['title'])
            ->setDescription($details['coupon_description'])
            ->setIsAdvanced(true)
            ->setStopRulesProcessing(true)
            ->setDiscountQty(1)
            ->setFromDate($details['start_date'])
            ->setWebsiteIds([$website_id])
            ->setCustomerGroupIds(array_keys($this->customerGroupCollection->toOptionArray()))
            ->setIsRss(false)
            ->setUsesPerCoupon(100000)
            ->setUsesPerCustomer($details['can_be_used'])
            ->setDiscountStep(1)
            ->setCouponType(2)
            ->setCouponCode($details['promo_code'])
            ->setDiscountAmount($details['coupon_type_value'])
            ->setIsActive(true);

        if($details['expire_date'] !== null){
            $price_rule->setToDate($details['expire_date']);
        }

        $conditionCombine = null;
        if(!empty($details['product_id'])){
            $price_rule_conditions = $price_rule_product_ids = [];
            foreach (explode(',', $details["product_id"]) as $product_id){
                try {
                    $product = $this->productRepository->getById($product_id, false, $website_id);

                    /** @var ConditionInterface $conditionProductId */
                    $conditionProductId = $this->conditionFactory->create();
                    $conditionProductId->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Product::class);
                    $conditionProductId->setAttributeName('id');
                    $conditionProductId->setValue('1');
                    $conditionProductId->setOperator('=');
                    $conditionProductId->setValue($product_id);

                    $price_rule_conditions[] = $conditionProductId;
                    $price_rule_product_ids[] = $product_id;
                } catch (NoSuchEntityException $e) {}
            }

            if(!empty($price_rule_conditions)){
                /** @var ConditionInterface $conditionProductFound */
                $conditionProductFound = $this->conditionFactory->create();
                $conditionProductFound->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Product\Found::class);
                $conditionProductFound->setValue('1');
                $conditionProductFound->setAggregatorType('all');
                $conditionProductFound->setConditions($price_rule_conditions);

                /** @var ConditionInterface $conditionCombine */
                $conditionCombine = $this->conditionFactory->create();
                $conditionCombine->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Combine::class);
                $conditionCombine->setValue('1');
                $conditionCombine->setAggregatorType('all');
                $conditionCombine->setConditions([$conditionProductFound]);

                $price_rule->setCondition($conditionCombine);
                $price_rule->setProductIds($price_rule_product_ids);
            }
        }
        $price_rule->setCondition($conditionCombine);

        switch ($details['coupon_type']){
            case 1:
                // percent
                $price_rule->setSimpleAction(RuleInterface::DISCOUNT_ACTION_BY_PERCENT);
                break;
            case 2:
                // fixed amount
                $price_rule->setSimpleAction(RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART);
                if($price_rule->getCondition() !== null){
                    $price_rule->setSimpleAction(RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT);
                }
                break;
            default:
                // free shipping
        }

        // when updating existing price rules
        if(method_exists($price_rule, "save")){
            $save = $price_rule->save();
            $price_rule_id = $price_rule->getId();
        }else{ // when creating a new one
            $save = $this->ruleRepository->save($price_rule);
            $price_rule_id = $save->getRuleId();
        }

        return $save->getRuleId() ?? false;
    }

    public function setStampCard($details){
        $price_rule = $price_rule_id = false;
        $website_id = $this->storeManager->getStore()->getId();

        // set stamp card price rule name
        // we need to set the price rules name with a specific name structure
        // we'll go with "Stamp Card - {STAMP CARD TEXT ON LAST STAMP}"

        // search price rule by ID
        try {
            if(array_key_exists("ecommerce_external_id", $details)){
                $price_rule = $this->loyaltyHelper->getPriceRule($details['ecommerce_external_id'], '');
            }
        } catch (NoSuchEntityException $e) {}

        // search by name
        if(!$price_rule){
            $price_rule = $this->loyaltyHelper->getPriceRuleByName("Stamp Card - " . $details['title']);
        }

        // if a price rule was found and the delete flag sent
        if($price_rule && (array_key_exists("delete", $details) && $details['delete'])){
            $this->ruleRepository->deleteById($price_rule->getRuleId());
            return "Rule deleted";
        }

        // create new price rule
        if (!$price_rule) $price_rule = $this->ruleFactory->create();

        // General rule data
        $price_rule
            ->setName("Stamp card - " . $details['title'])
            ->setDescription($details['coupon_description'])
            ->setIsAdvanced(true)
            ->setStopRulesProcessing(true)
            ->setDiscountQty(1)
            ->setFromDate($details['start_date'])
            ->setWebsiteIds([$website_id])
            ->setCustomerGroupIds(array_keys($this->customerGroupCollection->toOptionArray()))
            ->setIsRss(false)
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(10000)
            ->setDiscountStep(1)
            ->setDiscountQty(1)
            ->setCouponType(Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction("loyalty_stamp_card")
            ->setDiscountAmount('100')
            ->setUseAutoGeneration(1)
            ->setIsActive(true);

        if($details['expire_date'] !== null){
            $price_rule->setToDate($details['expire_date']);
        }

        if(empty($details['ecommerce_product_id'])) return true;
        $product_skus = [];
        foreach (explode(',', $details["ecommerce_product_id"]) as $product_id){
            try {
                if($product = $this->productRepository->getById($product_id, false, $website_id)){
                    $product_skus[] = $product->getSku();
                };
            } catch (NoSuchEntityException $e) {}
        }

        if(empty($product_skus)) return true;
        /** @var ConditionInterface $actionConditionProduct */
        $actionConditionProduct = $this->conditionFactory->create();
        $actionConditionProduct->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Product::class)
            ->setAttributeName("sku")
            ->setOperator("()")
            ->setValue(implode(",", $product_skus));

        /** @var ConditionInterface $actionCondition */
        $actionCondition = $this->conditionFactory->create();
        $actionCondition->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Product\Combine::class)
            ->setValue('1')
            ->setAggregatorType('all')
            ->setConditions([$actionConditionProduct]);

        $price_rule->setActionCondition($actionCondition);

        $id = false;
        try {
            $result = $this->ruleRepository->save($price_rule);
            $id = $result->getRuleId();
        } catch (\Throwable $e) {

            // new price rule
            $action = array(
                "type" => \Magento\SalesRule\Model\Rule\Condition\Product\Combine::class,
                "attribute" => null,
                "operator" => null,
                "value" => 1,
                "is_value_processed" => null,
                "aggregator" => "all",
                "conditions" => array(
                    array(
                        "type" => \Magento\SalesRule\Model\Rule\Condition\Product::class,
                        "attribute" => "sku",
                        "operator" => "()",
                        "value" => implode(",", $product_skus),
                        "is_value_processed" => false,
                        "attribute_scope" => ""
                    )
                )
            );

            $price_rule->setData("actions_serialized", json_encode($action));

            $price_rule->save();
            $id = $price_rule->getId();
        }
        return $id;
    }
}
