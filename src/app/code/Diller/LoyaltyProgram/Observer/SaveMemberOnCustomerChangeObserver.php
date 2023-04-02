<?php

namespace Diller\LoyaltyProgram\Observer;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SaveMemberOnCustomerChangeObserver implements ObserverInterface{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Data
     */
    protected Data $loyaltyHelper;

    /**
     * @param RequestInterface $request
     * @param CustomerRepositoryInterface $customerRepository
     * @param Data $loyaltyHelper
     */
    public function __construct(
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        Data $loyaltyHelper
    ) {
        $this->request = $request;
        $this->customerRepository = $customerRepository;
        $this->loyaltyHelper = $loyaltyHelper;
    }

    public function execute(Observer $observer) {
        $event = $observer->getEvent();

        // Get observer parameters
        $params = $this->request->getParams();
        echo "<pre>";
        print_r($params);
        echo "</pre>";

        /** @var CustomerInterface $customer */
        $customer = $event->getData('customer_data_object');

        // Search member ID and update the member if it exists
        echo "Search member ID and update the member if it exists";
        if ($member_id = $customer->getCustomAttribute('diller_member_id')) {
            echo "Member id attribute found: ";
            var_dump($member_id['value']);

            // $member = $this->loyaltyHelper->getMemberById($member_id);
            // var_dump($member);
        }else{
            // look for member with email
            $customer_email = $customer->getEmail();
            $member = $this->loyaltyHelper->getMember($customer_email);
            if(!empty($member)){
                if($member["email"] == $customer_email){
                    // update member
                    $member_update = array(
                        "first_name" => $params['firstname'],
                        "last_name" => $params['lastname'],
                        // deal with phone number INTL
                        "consent" => array(
                            "gdpr_accepted" => true,
                            "save_order_history" => true
                        )
                    );
                    try {
                        $update_result = $this->loyaltyHelper->updateMember(json_encode($member_update));
                        echo "<pre>";
                        print_r($update_result);
                        echo "</pre>";

                        //$customer->setCustomAttribute('diller_member_id', $update_result['id]);
                        //$this->customerRepository->save($customer);
                    } catch (\DillerAPI\ApiException $e){
                        $update_result = $e->getResponseBody();
                    }
                }
            }
        }

        //$customer->setCustomAttribute('diller_member_id', 98765678);
        //$this->customerRepository->save($customer);

        die;
    }
}
