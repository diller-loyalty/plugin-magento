<?php

namespace Diller\LoyaltyProgram\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Framework\Event\Observer;
use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InputMismatchException;

use libphonenumber\PhoneNumberUtil;

class SaveMemberOnCustomerChangeObserver implements ObserverInterface{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Data
     */
    protected Data $loyaltyHelper;

    /**
     * @param RequestInterface $request
     * @param Customer $customer
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param Data $loyaltyHelper
     */
    public function __construct(
        RequestInterface            $request,
        Customer                    $customer,
        CustomerFactory             $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        Data                        $loyaltyHelper
    ) {
        $this->request = $request;
        $this->customer = $customer;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->loyaltyHelper = $loyaltyHelper;
    }

    /**
     * @throws InputMismatchException
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(Observer $observer) {
        $event = $observer->getEvent();

        // get config department id
        $store_department_id = $this->loyaltyHelper->getSelectedDepartment();

        // Get observer parameters
        $params = $this->request->getParams();

        // Get chosen segments and prepare them
        $params_segments = array_filter(
            $params,
            fn ($key) => substr($key, 0, 8 ) === "segment_",
            ARRAY_FILTER_USE_KEY
        );

        $member_segments = [];
        foreach ($this->loyaltyHelper->getStoreSegments() as $storeSegment){
            if(isset($params_segments['segment_'.$storeSegment['id']]) && !empty($params_segments['segment_'.$storeSegment['id']])){
                $result = array(
                    "segment_id" => $storeSegment['id'],
                    "value" => '',
                    "selected_options" => array()
                );
                $member_choice = $params_segments['segment_' . $storeSegment['id']];
                if(!empty($member_choice)){
                    if($storeSegment['type'] === 'Checkbox' || $storeSegment['type'] === 'Dropdown' || $storeSegment['type'] === 'Radio') {
                        $result["selected_options"] = is_array($member_choice) ? $member_choice : [(int)$member_choice];
                    }else{
                        $result["value"] = $member_choice;
                    }
                    $member_segments[] = $result;
                }
            }
        }


        $is_member = $member_id = false;

        /** @var CustomerInterface $customer */
        $customer = $event->getData('customer_data_object');
        $customer_email = $customer->getEmail();

        // look for customer attribute on Magento that has the Diller member ID
        if($attribute = $customer->getCustomAttribute('diller_member_id')){
            if($member = $this->loyaltyHelper->getMemberById($attribute->getValue())){
                $member_id = $attribute->getValue();
                $is_member = true;
            }
        }

        // Search member by phone number from Diller
        if(!$is_member && !empty($params['loyalty_phone_number'])){
            $phone = preg_replace("/[^0-9+]/", "", $params['loyalty_phone_number'] ?? "");
            $country_code = strtoupper($params['country_code']) ?? "NO";

            // Check if phone is in international format
            if(preg_match("/^(\+|00)/", $phone)){
                $country_code = "";
                $phone = preg_replace("/^00/", "+", $phone);
            }

            try {
                if(($phone_number_proto = PhoneNumberUtil::getInstance()->parse($phone, $country_code)) && PhoneNumberUtil::getInstance()->isValidNumber($phone_number_proto)) {
                    $phone_country_code = '+' . $phone_number_proto->getCountryCode();
                    $phone_national_number = $phone_number_proto->getNationalNumber();
                    $country_code = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($phone_number_proto);

                    // check if customer is a Diller member
                    $result = $this->loyaltyHelper->getMember('', $phone_country_code.$phone_national_number);
                    if(!empty($result)){
                        $member = $result[0];
                        $is_member = true;
                    }
                }
            }
            catch (Exception $ex){}
        }

        // Search member by email from Diller
        if(!$is_member){
            $search = $this->loyaltyHelper->getMember($customer_email);
            if(!empty($search)) $member = $search[0];
        }

        // check loyalty consent
        if($params['loyalty_consent'] === 'on'){
            $member_object = array(
                "first_name" => $params['firstname'],
                "last_name" => $params['lastname'],
                "email" => $customer_email,
                "phone" => array(
                    "country_code" => $phone_country_code ?? $params['loyalty_phone_countrycode'],
                    "number" => $phone_national_number ?? $params['loyalty_phone_number']
                ),
                "consent" => array(
                    "gdpr_accepted" => true,
                    "receive_sms" => true,
                    "receive_email" => true,
                    "save_order_history" => $params['loyalty_consent_order_history'] === 'on'
                ),
                "department_ids" => $params['department'] ?? [$store_department_id],
                "segments" => $member_segments
            );

            if(array_key_exists('loyalty_consent_sms', $params)) $params["consent"]["receive_sms"] = $params['loyalty_consent_sms'] === 'on';
            if(array_key_exists('loyalty_consent_email', $params)) $params["consent"]["receive_email"] = $params['loyalty_consent_email'] === 'on';

            if($params['birth_date']) $member_object['birth_date'] = (string)date('Y-m-d', strtotime($params['birth_date']));
            if($params['gender']) $member_object['gender'] = $params['gender'];
            if($params['address']){
                $member_object['address'] = array(
                    "street" => $params['address'],
                    "city" => $params['city'] ?? '',
                    "zip_code" => isset($params['zip_code']) ? filter_var($params['zip_code'], FILTER_SANITIZE_NUMBER_INT) : '',
                    "state" => $params['state'] ?? '',
                    "country_code" => strtoupper($params['country_code']) ?? ''
                );
            }

            // register member in Diller
            if(!$is_member){
                try {
                    $member = $this->loyaltyHelper->registerMember(json_encode($member_object));
                } catch (\DillerAPI\ApiException $e){
                    Mage::app()->getResponse()->setBody(Mage::helper('customer')->__(json_decode($e->getResponseBody())->detail));
                }
            }else{
                // update member
                try {
                    $member = $this->loyaltyHelper->updateMember($member['id'], json_encode($member_object));
                } catch (\DillerAPI\ApiException $e){
                    Mage::app()->getResponse()->setBody(Mage::helper('customer')->__(json_decode($e->getResponseBody())->detail));
                }
            }
        }

        // save customer attribute with Diller member ID
        if(isset($member['id']) && $member_id !== $member['id']){
            $customerId = $customer->getID();
            $customer = $this->customer->load($customerId);
            $customerData = $customer->getDataModel();
            $customerData->setCustomAttribute('diller_member_id',(string)$member['id'] ?? '');
            $customer->updateData($customerData);
            $customerResource = $this->customerFactory->create();
            $customerResource->saveAttribute($customer, 'diller_member_id');
        }

        return true;
    }
}
