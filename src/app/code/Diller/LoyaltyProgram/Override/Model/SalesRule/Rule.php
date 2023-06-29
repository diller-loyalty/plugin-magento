<?php
declare(strict_types=1);

namespace Diller\LoyaltyProgram\Override\Model\SalesRule;

class Rule extends \Magento\SalesRule\Model\Data\Rule{
    public function getCouponCode(){
        return $this->_get('coupon_code');
    }
}