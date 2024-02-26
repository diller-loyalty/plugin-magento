<?php
namespace Diller\LoyaltyProgram\Model;

interface CouponStampCardInterface {
    /**
     * Gets the title
     *
     * @api
     * @return string
     */
    public function getTitle(): string;
    /**
     * Sets the title
     *
     * @param string $title
     * @return void
     * @api
     */
    public function setTitle(string $title): void;


    /**
     * Gets th description
     *
     * @api
     * @return string
     */
    public function getDescription(): string;

    /**
     * Sets the description
     *
     * @param string $description
     * @return void
     * @api
     */
    public function setDescription(string $description): void;


    /**
     * Gets the start date
     *
     * @api
     * @return string
     */
    public function getStartDate(): string;
    /**
     * Sets the expiry date
     *
     * @param string $start_date
     * @return void
     * @api
     */
    public function setStartDate(string $start_date): void;


    /**
     * Gets the expiry date
     *
     * @api
     * @return string|null
     */
    public function getExpireDate(): string|null;

    /**
     * Sets the expiry date
     *
     * @api
     * @param string|null $expire_date
     * @return void
     */
    public function setExpireDate(string $expire_date = null): void;


    /**
     * Get product ids
     *
     * @api
     * @return string
     */
    public function getProductIds(): string;
    /**
     * Sets product ids
     *
     * @api
     * @param string $product_ids
     * @return void
     */
    public function setProductIds(string $product_ids): void;


    /**
     * Get product categories
     *
     * @api
     * @return string
     */
    public function getProductCategories(): string;
    /**
     * Sets product categories
     *
     * @api
     * @param string $product_categories
     * @return void
     */
    public function setProductCategories(string $product_categories): void;


    /**
     * Gets the promo code
     *
     * @api
     * @return string
     */
    public function getPromoCode(): string;
    /**
     * Sets the promo code
     *
     * @param string $promo_code
     * @return void
     * @api
     */
    public function setPromoCode(string $promo_code): void;


    /**
     * Get discount value
     *
     * @api
     * @return int
     */
    public function getDiscountValue(): int;
    /**
     * Sets discount value
     *
     * @param int $discount_value
     * @return void
     * @api
     */
    public function setDiscountValue(int $discount_value): void;


    /**
     * Get discount type
     *
     * @api
     * @return string
     */
    public function getDiscountType(): string;
    /**
     * Sets discount type (percent, fixed amount, free shipping)
     *
     * @param string $discount_type
     * @return void
     * @api
     */
    public function setDiscountType(string $discount_type): void;


    /**
     * Get can be used
     *
     * @api
     * @return int
     */
    public function getCanBeUsed(): int;
    /**
     * Sets can be used
     *
     * @param int $can_be_used
     * @return void
     * @api
     */
    public function setCanBeUsed(int $can_be_used): void;
}