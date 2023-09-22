<?php

namespace Diller\LoyaltyProgram\Api;

use Diller\LoyaltyProgram\Helper\Data;
use Diller\LoyaltyProgram\Model\CouponStampCard;
use Diller\LoyaltyProgram\Model\CouponStampCardInterface;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection;

use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Model\Rule\Condition\Product;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine;

class CouponStampCardManagement implements CouponStampCardManagementInterface {
    protected Data $loyaltyHelper;

    protected RuleFactory $ruleFactory;
    private RuleRepository $ruleRepository;

    protected ConditionInterfaceFactory $conditionFactory;

    protected ProductRepositoryInterface $productRepository;
    protected Collection $customerGroupCollection;
    protected StoreManagerInterface $storeManager;

    protected Response $response;

    public function __construct(
        Data $loyaltyHelper,
        RuleFactory $ruleFactory,
        RuleRepository $ruleRepository,
        ConditionInterfaceFactory $conditionFactory,
        ProductRepositoryInterface $productRepository,
        Collection $customerGroupCollection,
        StoreManagerInterface $storeManager,
        Response $response){

        $this->loyaltyHelper = $loyaltyHelper;
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
        $this->conditionFactory = $conditionFactory;
        $this->productRepository = $productRepository;
        $this->customerGroupCollection = $customerGroupCollection;
        $this->storeManager = $storeManager;
        $this->response = $response;
    }

    /**
     * {@inheritDoc}
     *
     */
    public function update(string $type, int $priceRuleId, CouponStampCardInterface $details): void{
        $this->response->setHeader("Content-type", "application/json");

        // search price rule per promo code
        $price_rule_found = $this->loyaltyHelper->getPriceRule($priceRuleId, $details->getTitle(), '');

        // create empty price rule object
        $price_rule = $this->ruleFactory->create();
        $price_rule_data = $this->setPriceRuleData($type, $details);
        $price_rule_data['rule_id'] = $price_rule_found ? $priceRuleId : null;

        $price_rule->loadPost($price_rule_data);
        try {
            if($result = $price_rule->save()){
                $this->response->setHttpResponseCode(200)->setBody(json_encode(["message" => "Price rule updated successfully!", "price_rule_id" => intval($result->getRuleId())]))->sendResponse();
                exit();
            }
        } catch (InputException|LocalizedException|NoSuchEntityException $e) {
            $response_code = $e->getMessage() === 'Coupon with the same code already exists.' ? 409 : 400;
            $this->response->setHttpResponseCode($response_code)->setBody(json_encode(["message" => $e->getMessage()]))->sendResponse();
            exit();
        }

        $this->response->setHttpResponseCode(400)->setBody(json_encode(["message" => "Price rule not updated"]))->sendResponse();
    }

    /**
     * {@inheritDoc}
     *
     */
    public function create(string $type, CouponStampCardInterface $details): void{
        $this->response->setHeader("Content-type", "application/json");

        // create empty price rule object
        $price_rule = $this->ruleFactory->create();
        $price_rule_data = $this->setPriceRuleData($type, $details);

        $price_rule->loadPost($price_rule_data);
        try {
            if($result = $price_rule->save()){
                $this->response->setHttpResponseCode(200)->setBody(json_encode(["message" => "Price rule created successfully!", "price_rule_id" => intval($result->getRuleId())]))->sendResponse();
                exit();
            }
        } catch (InputException|LocalizedException|NoSuchEntityException $e) {
            $response_code = $e->getMessage() === 'Coupon with the same code already exists.' ? 409 : 400;
            $this->response->setHttpResponseCode($response_code)->setBody(json_encode(["message" => $e->getMessage()]))->sendResponse();
            exit();
        }

        $this->response->setHttpResponseCode(400)->setBody(json_encode(["message" => "Price rule not created"]))->sendResponse();
    }

    /**
     * {@inheritDoc}
     *
     */
    public function delete(string $type, int $priceRuleId): void{
        $this->response->setHeader("Content-type", "application/json");
        try {
            $this->ruleRepository->deleteById($priceRuleId);
            $this->response->setHttpResponseCode(202)->sendResponse();
            exit();
        }
        catch (NoSuchEntityException|LocalizedException) {}

        $this->response->setHttpResponseCode(404)->setBody(json_encode(["message" => "Price rule not found!"]))->sendResponse();
    }


    private function setPriceRuleData(string $type, CouponStampCard $details){
        // Price rule data
        $price_rule_data = array(
            'is_active' => true,
            'name' => $details->getTitle(),
            'description' => $details->getDescription(),
            'from_date' => $details->getStartDate(),
            'website_ids' => [$this->storeManager->getStore()->getId()],
            'customer_group_ids' => array_keys($this->customerGroupCollection->toOptionArray()),
            'uses_per_coupon' => 10000,
            'coupon_type' => 2,
            'discount_qty' => 1,
        );

        if(!is_null($details->getExpireDate())){
            $price_rule_data['to_date'] = $details->getExpireDate();
        }

        if($type == "coupon"){
            $price_rule_data = $this->setCouponDetails($price_rule_data, $details);
        }else{
            // Stamp Card
            if(empty($details->getProductIds())){
                $this->response->addMessage("Price rule not created. No product ids were sent", 400)->sendResponse();
                exit();
            }
            $price_rule_data = $this->setStampCardDetails($price_rule_data, $details);
        }

        return $price_rule_data;
    }

    private function setCouponDetails($price_rule_data, CouponStampCard $coupon){
        $price_rule_data["coupon_code"] = $coupon->getPromoCode();
        $price_rule_data["uses_per_customer"] = $coupon->getCanBeUsed();
        $price_rule_data["discount_amount"] = $coupon->getDiscountValue();

        // set discount type
        switch ($coupon->getDiscountType()){
            case "percentage":
                $price_rule_data['simple_action'] = RuleInterface::DISCOUNT_ACTION_BY_PERCENT;
                break;
            case "fixed_amount":
                $price_rule_data['simple_action'] = RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART;
                if(array_key_exists("condition", $price_rule_data)){
                    $price_rule_data['simple_action'] = RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT;
                }
                break;
            default:
            // free shipping
        }

        return $price_rule_data;
    }

    private function setStampCardDetails($price_rule_data, $stamp_card){
        // Set discount qty high to be able to give free more than 1 unit on stamp card eligibility
        $price_rule_data['discount_qty'] = 50;
        // Set 10000 as uses per customer since we don't want to block the times a member uses the stamp card
        $price_rule_data["uses_per_customer"] = 10000;

        // Set "loyalty_stamp_card" for the custom action created with this plugin
        $price_rule_data['simple_action'] = "loyalty_stamp_card";

        // set auto generation as true since the stamp cards promo codes are auto generated on the fly
        $price_rule_data["use_auto_generation"] = 1;

        // this value is only for UI since the amount itself will be calculated programmatically on the fly
        $price_rule_data["discount_amount"] = 100;

        // create price rule action according to the given products
        $price_rule_product_ids = $product_skus = [];
        foreach (explode(',', $stamp_card->getProductIds()) as $product_id){
            try {
                if($product = $this->productRepository->getById($product_id, false, $this->storeManager->getStore()->getId())){
                    $price_rule_product_ids[] = $product_id;
                    $product_skus[] = $product->getSku();
                }

            } catch (NoSuchEntityException) {}
        }
        if(empty($price_rule_product_ids)){
            $this->response->addMessage("Price rule not created. We could not find any product with the given product ids", 400)->sendResponse();
            exit();
        }
        $price_rule_data['product_ids'] = $price_rule_product_ids;

        $action = array(
            "type" => Combine::class,
            "attribute" => null,
            "operator" => null,
            "value" => 1,
            "is_value_processed" => null,
            "aggregator" => "all",
            "conditions" => array(
                array(
                    "type" => Product::class,
                    "attribute" => "sku",
                    "operator" => "()",
                    "value" => implode(",", $product_skus),
                    "is_value_processed" => false,
                    "attribute_scope" => ""
                )
            )
        );

        $price_rule_data['actions_serialized'] = json_encode($action);

        return $price_rule_data;
    }
}
