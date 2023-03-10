<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Diller\LoyaltyProgram\Controller\Customer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action {
    public function execute() {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $resultPage->getConfig()->getTitle()->set(__('Loyalty Program'));

        return $resultPage;
    }
}
