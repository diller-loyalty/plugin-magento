<?php
namespace Diller\LoyaltyProgram\Api;

use Diller\LoyaltyProgram\Helper\Data;

use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\Framework\ObjectManagerInterface;
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
        // search price rule
        $price_rule_found = $this->loyaltyHelper->getPriceRule($details['ecommerce_external_id'] ?? '', '', $details['promo_code'] ?? '');

        // if a price rule was found and the delete flag sent
        if(array_key_exists("delete", $details) && $details['delete']){
            if($price_rule_found) $this->ruleRepository->deleteById($price_rule_found->getRuleId());
            return "Rule deleted";
        }

        // create empty price rule object
        $price_rule = $this->ruleFactory->create();

        // Price rule data
        $price_rule_data = array(
            'rule_id' => $price_rule_found ? $price_rule_found->getRuleId() : null,
            'is_active' => true,
            'name' => $details['title'],
            'description' => $details['coupon_description'],
            'from_date' => $details['start_date'],
            'website_ids' => [$this->storeManager->getStore()->getId()],
            'customer_group_ids' => array_keys($this->customerGroupCollection->toOptionArray()),
            'uses_per_coupon' => 100000,
            'coupon_type' => 2,
            'coupon_code' => $details['promo_code'],
            'discount_amount' => $details['coupon_type_value'],
            'discount_qty' => 1,
            'uses_per_customer' => $details['can_be_used']
        );

        if(array_key_exists("expire_date", $details)){
            $price_rule_data['to_date'] = $details['expire_date'];
        }

        $conditionCombine = null;
        if(!empty($details['product_id'])){
            $price_rule_conditions = $price_rule_product_ids = [];
            foreach (explode(',', $details["product_id"]) as $product_id){
                try {
                    $product = $this->productRepository->getById($product_id, false, $this->storeManager->getStore()->getId());

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

                $price_rule['product_ids'] = $price_rule_product_ids;
            }
        }
        $price_rule['condition'] = $conditionCombine;

        switch ($details['coupon_type']){
            case 1:
                // percent
                $price_rule['simple_action'] = RuleInterface::DISCOUNT_ACTION_BY_PERCENT;
                break;
            case 2:
                // fixed amount
                $price_rule['simple_action'] = RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART;
                if($price_rule['condition'] !== null){
                    $price_rule['simple_action'] = RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT;
                }
                break;
            default:
                // free shipping
        }

        $price_rule->loadPost($price_rule_data);
        $price_rule->save();

        return $price_rule->getRuleId() ?? false;
    }

    public function setStampCard($details){
        $website_id = $this->storeManager->getStore()->getId();

        // search price rule
        $stamp_card_external_id = $details['ecommerce_external_id'] ?? '';
        $price_rule = $this->loyaltyHelper->getPriceRule($stamp_card_external_id, "Stamp Card - " . $details['title']);

        // if a price rule was found and the delete flag sent
        if($price_rule && (array_key_exists("delete", $details) && $details['delete'])){
            $this->ruleRepository->deleteById($price_rule->getRuleId());
            return "Rule deleted";
        }

        // create new price rule
        if (!$price_rule) $price_rule = $this->ruleFactory->create();

        // General rule data
        $price_rule
            ->setName("Stamp Card - " . $details['title'])
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
                }
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