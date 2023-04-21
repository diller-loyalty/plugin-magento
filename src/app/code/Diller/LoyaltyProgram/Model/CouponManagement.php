<?php
namespace Diller\LoyaltyProgram\Model;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class CouponManagement {
    /**
     * @param Data
     */
    protected Data $loyaltyHelper;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\SalesRule\Model\RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var \Magento\SalesRule\Model\RuleRepository
     */
    protected $ruleRepository;

    public function __construct(
        Data $loyaltyHelper,
        \Magento\Framework\ObjectManagerInterface $_objectManager,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        RuleRepository $ruleRepository) {
        $this->loyaltyHelper = $loyaltyHelper;
        $this->_objectManager = $_objectManager;
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     */
    public function setCoupon($coupon_id = false, $all_coupons = false){
        if(!$coupon_id && !$all_coupons) return 'no "coupon id" passed nor "all coupons" flag';

        $coupons_to_create = $result = [];
        $store_coupons = $this->loyaltyHelper->getStoreCoupons();

        if(empty($store_coupons)) return 'no store coupons';

        if($all_coupons) $coupons_to_create = $store_coupons;

        if($coupon_id && !$all_coupons){
            foreach ($store_coupons as $coupon){
                if($coupon->getId() == (int)$coupon_id){
                    $coupons_to_create[] = $coupon;
                    continue;
                }
            }
        }

        if(!empty($coupons_to_create)){
            foreach ($coupons_to_create as $coupon) {
                $price_rule_data = [
                    "name" => $coupon->getTitle(),
                    "description" => $coupon->getDescription(),
                    "from_date" => $coupon->getValidFrom(),
                    "to_date" => $coupon->getValidTo(),
                    "uses_per_customer" => $coupon->getMaxRedemptions() > 0 ? $coupon->getMaxRedemptions() : 100000,
                    "is_active" => "1",
                    "stop_rules_processing" => "0",
                    "is_advanced" => "1",
                    "product_ids" => $coupon->getProductIds(),
                    "sort_order" => "0",
                    "discount_amount" => $coupon->getDiscountValue() ?? 0,
                    "discount_qty" => null,
                    "discount_step" => "3",
                    "apply_to_shipping" => "0",
                    "times_used" => "0",
                    "is_rss" => "1",
                    "coupon_type" => "NO_COUPON",
                    "use_auto_generation" => "0",
                    "uses_per_coupon" => "0",
                    "simple_free_shipping" => "0",
                    "customer_group_ids" => [0, 1, 2, 3],
                    "website_ids" => [1],
                    "coupon_code" => $coupon->getCode(),
                    "store_labels" => [],
                    "conditions_serialized" => '',
                    "actions_serialized" => ''
                ];

                switch ($coupon->getDiscountType()) {
                    case 'Percentage':
                        $price_rule_data['simple_action'] = 'by_percent';
                        break;
                    case 'FreeShipping':
                        $price_rule_data['simple_action'] = 'by_fixed';
                        $price_rule_data['simple_free_shipping'] = 1;
                        break;
                    default: // "fixed"
                        $price_rule_data['simple_action'] = 'by_fixed';
                }

                $price_rule = false;
                $price_rule_id = $coupon->getExternalIds()[0]['externalId'] ?? false;
                if ($price_rule_id) {
                    try {
                        $price_rule = $this->ruleFactory->create()->load($price_rule_id);
                    } catch (\Exception $ex) { // no price rule found
                    }
                }
                if (!$price_rule) $price_rule = $this->ruleFactory->create();

                // set price rule data
                foreach ($price_rule_data as $key => $value) {
                    $price_rule->setData($key, $value);
                }
                $price_rule->save();

                $result[] = array(
                    "diller_id" => $coupon->getId(),
                    "webshop_id" => $price_rule->getId()
                );
            }
        }

        return $result;
    }
}
