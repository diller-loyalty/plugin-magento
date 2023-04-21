<?php
namespace Diller\LoyaltyProgram\Api;

interface CouponManagementInterface {

    /**
     * POST for coupons creation/update
     * @param int $coupon_id
     * @param boolean $all_coupons
     * @return string
     */

    public function setCoupon($coupon_id = false, $all_coupons = false);
}
