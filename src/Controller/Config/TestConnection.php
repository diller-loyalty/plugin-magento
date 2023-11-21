<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Controller\Config;

use Diller\LoyaltyProgram\Helper\Data;
use DillerAPI\Configuration;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class TestConnection extends Action
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
        $resultArray = array('success' => false);

        $params = $this->getRequest()->getParams();
        if(!empty($params)){
            $configs = clone Configuration::getDefaultConfiguration();
            if($params['test_environment']){
                $configs->setHost("https://api.prerelease.dillerapp.com");
            }

            $store_uid = $params['store_uid'];
            $api_key = $params['api_key'];

            $configs->setUserAgent("DillerLoyaltyPlugin/Magento v1.0.0");
            try {
                $dillerAPI = new \DillerAPI\DillerAPI($store_uid, $api_key, $configs);
                $store = $dillerAPI->Stores->get($store_uid);
                if($store->getId() === $store_uid){
                    $resultArray = array('success' => true);
                }
            }catch (\DillerAPI\ApiException $e){}
        }

        $resultJson->setData($resultArray);
        return $resultJson;
    }
}