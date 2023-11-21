<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Controller\Member;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Search extends Action
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
        $resultArray = array('success' => false, 'member' => null);

        $params = $this->getRequest()->getParams();
        if(!empty($params)){
            $search = $this->loyaltyHelper->getMember('', '+' . $params['phoneNumber']);
            if(!empty($search)){
                $member = $search[0];
                if($member->getConsent()->getGdprAccepted()){
                    $resultArray = array('success' => true, 'result' => array(
                        "is_member" => true,
                        "member_id" => $member->getId()
                    ));
                    $this->loyaltyHelper->sendLoginOTP($member->getId());
                }
            }
        }

        $resultJson->setData($resultArray);
        return $resultJson;
    }
}