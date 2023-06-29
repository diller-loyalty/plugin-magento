<?php
namespace Diller\LoyaltyProgram\Model\Rule\Action\Discount;

use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\Rule\Action\Discount\Data;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;

class StampCardDiscount extends AbstractDiscount{
    protected $validator;
    protected DataFactory $discountDataFactory;
    protected $priceCurrency;

    public function __construct(
        Validator $validator,
        DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency
    ){
        parent::__construct($validator, $discountDataFactory, $priceCurrency);
    }

    /**
     * @param Rule $rule
     * @param AbstractItem $item
     * @param float $qty
     * @return Data
     */
    public function calculate($rule, $item, $qty): Data
    {
        return $this->_calculateStampCardDiscount($item);
    }

    /**
     * Calculate Stamp card discount amount
     *
     * @param AbstractItem $item
     * @return Data
     */
    protected function _calculateStampCardDiscount(AbstractItem $item): Data{
        /** @var Data $discountData */
        $discountData = $this->discountFactory->create();
        $quote = $item->getQuote();

        $cartItems = $quote->getItems();
        $discountAmount = 0;
        $prices = [];

        if(!empty($cartItems)){
            foreach ($cartItems as $cartItem) {
                if($cartItem->getAdditionalData() === 'eligible_to_stamp_card_discount'){
                    $prices[] = $cartItem->getPrice();
                }
            }
            if(!empty($prices)) $discountAmount = min($prices);
        }

        $itemPrice = $this->validator->getItemPrice($item);
        if($itemPrice > $discountAmount) $discountAmount = 0;

        $discountData->setAmount($discountAmount);
        $discountData->setBaseAmount($discountAmount);
        $discountData->setOriginalAmount($discountAmount);
        $discountData->setBaseOriginalAmount($discountAmount);

        return $discountData;
    }
}