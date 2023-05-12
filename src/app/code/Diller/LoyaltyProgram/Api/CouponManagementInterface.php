<?php
    namespace Diller\LoyaltyProgram\Api;

    interface CouponManagementInterface {
        /**
         * POST for coupon creation/update
         * @param int $price_rule_id
         * @param mixed $price_rule_data
         * @return int|boolean
         */
        public function setCoupon($price_rule_id, $price_rule_data);
}
