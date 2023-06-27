<?php
namespace Diller\LoyaltyProgram\Model\Rule\Action\Discount;

use Magento\Checkout\Model\SessionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\Rule;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule\Action\Discount\Data;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Validator;

class StampCardDiscount extends AbstractDiscount{
    protected $checkoutSession;
    protected $validator;
    protected $discountDataFactory;
    protected $priceCurrency;

    public function __construct(
        SessionFactory $checkoutSession,
        Validator $validator,
        DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency
    ){
        $this->checkoutSession = $checkoutSession;
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

        $checkoutSession = $this->checkoutSession->create();
        $cartItems = $checkoutSession->getQuote()->getAllVisibleItems();
        $prices = [];
        foreach ($cartItems as $cartItem) {
            if($cartItem->getAdditionalData() === 'eligible_to_stamp_card_discount'){
                $prices[] = $cartItem->getPrice();
            }
        }
        $discountAmount = min($prices);
        $itemPrice = $this->validator->getItemPrice($item);

        if($itemPrice > $discountAmount) $discountAmount = 0;

        /** Set the discount price in Price **/
        $baseItemPrice = $this->validator->getItemBasePrice($item);
        $itemOriginalPrice = $this->validator->getItemOriginalPrice($item);
        $baseItemOriginalPrice = $this->validator->getItemBaseOriginalPrice($item);

        $discountData->setAmount($discountAmount);
        $discountData->setBaseAmount($discountAmount);
        $discountData->setOriginalAmount($discountAmount);
        $discountData->setBaseOriginalAmount($discountAmount);

        return $discountData;
    }
}