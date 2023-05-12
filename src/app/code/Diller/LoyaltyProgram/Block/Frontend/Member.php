<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Block\Frontend;

use Diller\LoyaltyProgram\Helper\Data;

use Magento\Customer\Helper\View;
use Magento\Framework\View\Element\Template;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Loyalty Customer and Member Info
 */
class Member extends Template {
    /**
     * @var View
     */
    protected View $_viewHelper;

    /**
     * @var Data
     */
    protected Data $_loyaltyHelper;

    /**
     * @var CurrentCustomer
     */
    protected CurrentCustomer $currentCustomer;

    /**
     * @var DillerAPI
     */
    private DillerAPI $dillerAPI;

    /**
     * @var String
     */
    private String $store_uid;

    /**
     * @var LoyaltyDetails
     */
    private LoyaltyDetails $loyaltyDetails;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private \Magento\Checkout\Model\Session $checkoutSession;

    /**
     * Constructor
     *
     * @param Context $context
     * @param CurrentCustomer $currentCustomer
     * @param View $viewHelper
     * @param Data $loyaltyHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        CurrentCustomer $currentCustomer,
        View $viewHelper,
        Data $loyaltyHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->currentCustomer = $currentCustomer;
        $this->_viewHelper = $viewHelper;
        $this->_loyaltyHelper = $loyaltyHelper;
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context, $data);
    }

    /**
     * Returns the Magento Customer Model for this block
     *
     * @return CustomerInterface|null
     */
    public function getCustomer(): ?CustomerInterface {
        try {
            return $this->currentCustomer->getCustomer();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    public function getMemberById($id){
        return $this->_loyaltyHelper->getMemberById($id);
    }

    public function getMemberByEmail($email){
        if(empty($email)){
            return false;
        }
        $member = $this->_loyaltyHelper->getMember($email);
        if(!empty($member) && $member[0]['email'] === $email){
            return $member[0];
        }
        return [];
    }

    public function getMemberByPhoneNumber($phone){
        $member = $this->_loyaltyHelper->getMember('', $phone);
        if(!empty($member)){
            return $member[0];
        }
        return [];
    }

    public function getMemberCoupons($id){
        return $this->_loyaltyHelper->getMemberCoupons($id);
    }

    public function getCheckoutSession(){
        return $this->checkoutSession;
    }
}
