<?php

namespace Diller\LoyaltyProgram\Model;

class CouponStampCard implements CouponStampCardInterface{

    protected string $title;
    protected string $description;
    protected string $start_date;
    protected string|null $expire_date;
    protected string $product_ids;
    protected string $product_categories;
    protected string $promo_code;
    protected int $discount_value;
    protected string $discount_type;
    protected int $can_be_used;

    public function __construct(){
        $this->promo_code = '';
        $this->expire_date = null;
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return $this->title;
    }
    /**
     * {@inheritDoc}
     */
    public function setTitle($title): void
    {
        $this->title = $title;
    }


    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    /**
     * {@inheritDoc}
     */
    public function setDescription(string $description = ''): void
    {
        $this->description = $description;
    }

    /**
     * {@inheritDoc}
     */
    public function getExpireDate(): string|null
    {
        return $this->expire_date;
    }
    /**
     * {@inheritDoc}
     */
    public function setExpireDate(string $expire_date = null): void
    {
        $this->expire_date = $expire_date;
    }


    /**
     * {@inheritDoc}
     */
    public function getStartDate(): string
    {
        return $this->start_date;
    }
    /**
     * {@inheritDoc}
     */
    public function setStartDate(string $start_date): void
    {
        $this->start_date = $start_date;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductIds(): string
    {
        return $this->product_ids;
    }
    /**
     * {@inheritDoc}
     */
    public function setProductIds($product_ids): void
    {
        $this->product_ids = $product_ids;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductCategories(): string
    {
        return $this->product_categories;
    }
    /**
     * {@inheritDoc}
     */
    public function setProductCategories($product_categories): void
    {
        $this->product_categories = $product_categories;
    }

    /**
     * {@inheritDoc}
     */
    public function getPromoCode(): string
    {
        return $this->promo_code;
    }
    /**
     * {@inheritDoc}
     */
    public function setPromoCode($promo_code = ''): void
    {
        $this->promo_code = $promo_code;
    }

    /**
     * {@inheritDoc}
     */
    public function getDiscountValue(): int
    {
        return $this->discount_value;
    }
    /**
     * {@inheritDoc}
     */
    public function setDiscountValue(int $discount_value): void
    {
        $this->discount_value = $discount_value;
    }

    /**
     * {@inheritDoc}
     */
    public function getDiscountType(): string
    {
        return $this->discount_type;
    }
    /**
     * {@inheritDoc}
     */
    public function setDiscountType(string $discount_type): void
    {
        $this->discount_type = $discount_type;
    }

    /**
     * {@inheritDoc}
     */
    public function getCanBeUsed(): int
    {
        return $this->can_be_used;
    }
    /**
     * {@inheritDoc}
     */
    public function setCanBeUsed(int $can_be_used): void
    {
        $this->can_be_used = $can_be_used;
    }
}