<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Controller\Member;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class ValidateOTP extends Action
{
    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var JsonFactory
     * */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var Data
     */
    protected Data $loyaltyHelper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Data $loyaltyHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Data $loyaltyHelper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->loyaltyHelper = $loyaltyHelper;
        return parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultArray = array('success' => false, 'result' => 'Invalid OTP');

        $params = $this->getRequest()->getParams();
        if(!empty($params)){
            $otpValidation = $this->loyaltyHelper->loginOTPVerification($params['memberId'], $params['otp']);
            if($otpValidation->getIsOk()){
                $member = $this->loyaltyHelper->getMemberById($params['memberId']);
                $resultArray = array(
                    'success' => true,
                    'result' => json_decode($member->__toString(), true)
                );
            }
        }

        $resultJson->setData($resultArray);
        return $resultJson;
    }
}