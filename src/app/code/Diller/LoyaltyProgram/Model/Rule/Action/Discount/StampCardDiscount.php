<?php
namespace Diller\LoyaltyProgram\Model\Rule\Action\Discount;

use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;

class StampCardDiscount extends AbstractDiscount{
    protected $validator;
    protected DataFactory $discountDataFactory;
    protected $priceCurrency;

    public function __construct(
        Validator $validator,
        \Magento\SalesRule\Model\Rule\Action\Discount\DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency
    ){
        $this->discountDataFactory = $discountDataFactory;
        parent::__construct($validator, $discountDataFactory, $priceCurrency);
    }

    /**
     * @param Rule $rule
     * @param AbstractItem $item
     * @param float $qty
     * @return \Magento\SalesRule\Model\Rule\Action\Discount\Data
     */
    public function calculate($rule, $item, $qty): \Magento\SalesRule\Model\Rule\Action\Discount\Data
    {
        return $this->_calculateStampCardDiscount($item);
    }

    /**
     * Calculate Stamp card discount amount
     *
     * @param AbstractItem $item
     * @return \Magento\SalesRule\Model\Rule\Action\Discount\Data
     */
    protected function _calculateStampCardDiscount(AbstractItem $item): \Magento\SalesRule\Model\Rule\Action\Discount\Data{
        $discountData = $this->discountDataFactory->create();

        $discountAmount = 0;
        if(!is_null($item->getData("additional_data"))){
            $stamp_card_details = json_decode($item->getData("additional_data"), 1);
            if(is_array($stamp_card_details) && array_key_exists('discounts', $stamp_card_details)){
                foreach ($stamp_card_details['discounts'] as $item_sku => $discount) {
                    if($item['sku'] == $item_sku){
                        $discountAmount = $discount;
                        continue;
                    }
                }
            }
        }

        $discountData->setAmount($discountAmount);
        $discountData->setBaseAmount($discountAmount);
        $discountData->setOriginalAmount($discountAmount);
        $discountData->setBaseOriginalAmount($discountAmount);

        return $discountData;
    }
}