<?php
namespace Diller\LoyaltyProgram\Api;

use Diller\LoyaltyProgram\Model\CouponStampCardInterface;

interface CouponStampCardManagementInterface {

    /**
     * Coupon/Stamp Card update
     *
     * @api
     * @param string $type
     * @param int $priceRuleId
     * @param CouponStampCardInterface $details
     *
     * @return void
     */
    public function update(string $type, int $priceRuleId, CouponStampCardInterface $details): void;


    /**
     * Coupon/Stamp Card create
     *
     * @api
     * @param string $type
     * @param CouponStampCardInterface $details
     *
     * @return void
     */
    public function create(string $type, CouponStampCardInterface $details): void;


    /**
     * Coupon/Stamp Card delete
     *
     * @api
     * @param string $type
     * @param int $priceRuleId
     * @return void
     */
    public function delete(string $type, int $priceRuleId): void;

}
