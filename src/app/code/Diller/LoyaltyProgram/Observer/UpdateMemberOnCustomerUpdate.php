<?php

namespace Diller\LoyaltyProgram\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\CustomerAssignment;

class UpdateMemberOnCustomerUpdate implements ObserverInterface{

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->request         = $request;
    }

    public function execute(Observer $observer) {
        $event = $observer->getEvent();
        /** @var CustomerInterface $customer */
        $customer = $event->getData('customer_data_object');
        echo "<pre>";
        print_r($this->request->getParams());
        echo "</pre>";

        $member_id = 0123456;

        $customer->setCustomAttribute('loyalty_member_id', 1);

        $customer = $event->getData('customer_data_object');
        echo "<pre>";
        print_r($customer->getCustomAttributes());
        echo "</pre>";
        die;
    }
}
