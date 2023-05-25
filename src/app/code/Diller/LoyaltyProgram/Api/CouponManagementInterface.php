<?php
namespace Diller\LoyaltyProgram\Api;

interface CouponManagementInterface {
    /**
     * POST for coupon creation/update
     * @param mixed $details
     * @return int|boolean
     */
    public function setCoupon($details);
}
