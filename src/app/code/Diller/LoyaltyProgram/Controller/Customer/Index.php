<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Controller\Customer;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action{

    /**
     * @var Data
     */
    protected Data $loyaltyHelper;

    /**
     * @param Context $context
     * @param Data $loyaltyHelper
     */
    public function __construct(
        Context $context,
        Data $loyaltyHelper
    ) {
        parent::__construct($context);
        $this->loyaltyHelper = $loyaltyHelper;
    }
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $loyaltyDetails = $this->loyaltyHelper->getLoyaltyDetails();
        $resultPage->getConfig()->getTitle()->set(__($loyaltyDetails['storeName']));

        return $resultPage;
    }
}
