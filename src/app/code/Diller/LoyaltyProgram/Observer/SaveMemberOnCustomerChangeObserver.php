<?php

namespace Diller\LoyaltyProgram\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InputMismatchException;
use libphonenumber\PhoneNumberUtil;
use Magento\Store\Model\ScopeInterface;

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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

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
        Data                        $loyaltyHelper,
        ScopeConfigInterface        $scopeConfig,
    ) {
        $this->request = $request;
        $this->customer = $customer;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->loyaltyHelper = $loyaltyHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @throws InputMismatchException
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(Observer $observer) {
        $valid_phone_number = $phone_country_code = $phone_national_number = false;
        $event = $observer->getEvent();

        // get customer object
        if(!($customer = $event->getData('customer_data_object'))){
            return true;
        }

        // Get observer parameters
        $params = $this->request->getParams();
        $country = isset($params['country_code']) ? $params['country_code'] : '';

        // Get chosen segments and prepare them
        $params_segments = array_filter(
            $params,
            fn ($key) => substr($key, 0, 8 ) === "segment_",
            ARRAY_FILTER_USE_KEY
        );

        // check if customer is member
        $is_member = ($member = $this->loyaltyHelper->searchMemberByCustomerId($customer->getID()));

        // Search member by phone number from Diller
        if(!$is_member && !empty($params['loyalty_phone_number'])){
            $phone = preg_replace("/[^0-9+]/", "", $params['loyalty_phone_number'] ?? "");
            $is_i18n_format = preg_match("/^(\+|00)/", $phone);
            $phone_country_code = $params['loyalty_phone_countrycode'] ?? "+47";
            $phone = $is_i18n_format ? preg_replace("/^00/", "+", $phone) : $phone_country_code.$phone;

            try {
                if(($phone_number_proto = PhoneNumberUtil::getInstance()->parse($phone, '')) && PhoneNumberUtil::getInstance()->isValidNumber($phone_number_proto)) {
                    $phone_national_number = $phone_number_proto->getNationalNumber();
                    $country = !empty($country) ? $country : PhoneNumberUtil::getInstance()->getRegionCodeForNumber($phone_number_proto);
                    $valid_phone_number = true;

                    // check if customer is a Diller member
                    $result = $this->loyaltyHelper->getMember('', $phone_country_code.$phone_national_number);
                    if(!empty($result)){
                        $is_member = ($member = $result[0]);
                    }
                }
            }
            catch (\Exception $ex){}
        }

        if($is_member || $valid_phone_number){
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

            $member_object = array(
                "first_name" => trim($params['firstname']),
                "last_name" => trim($params['lastname']),
                "email" => $customer->getEmail(),
                "phone" => array(
                    "country_code" => $phone_country_code,
                    "number" => $phone_national_number
                ),
                "consent" => array(
                    "gdpr_accepted" => key_exists('loyalty_consent', $params),
                    "save_order_history" => key_exists('loyalty_consent_order_history', $params)
                ),
                "origin" => array(
                    "department_id" => $this->loyaltyHelper->getSelectedDepartment(),
                    "channel" => "OnlineStore"
                ),
                "department_ids" => $params['department'] ?? [],
                "segments" => $member_segments
            );

            $member_object["consent"]["receive_sms"] = key_exists('loyalty_consent_sms', $params);
            $member_object["consent"]["receive_email"] = key_exists('loyalty_consent_email', $params);

            if(array_key_exists('birth_date', $params)) $member_object['birth_date'] = !empty($params['birth_date']) ? date('Y-m-d', strtotime($params['birth_date'])) : null;
            if(array_key_exists('gender', $params)) $member_object['gender'] = $params['gender'];

            $member_object['address'] = array(
                "street"       => !empty($params['address']) ? $params['address'] : "",
                "city"         => !empty($params['city']) ? $params['city'] : "",
                "zip_code"     => !empty($params['zip_code']) ? $params['zip_code'] : "",
                "state"        => !empty($params['state']) ? $params['state'] : "",
                "country_code" => strtoupper($country),
            );

            // update/delete member
            if($is_member){
                try {
                    // Set the communications consents as true if member didn't had GDPR accepted before this
                    if(!$member->getConsent()->getGdprAccepted() && $member_object['consent']['gdpr_accepted']){
                        $member_object["consent"]["receive_sms"] = true;
                        $member_object["consent"]["receive_email"] = true;
                    }
                    if(!$member_object['consent']['gdpr_accepted']){
                        if($this->loyaltyHelper->deleteMember($member->getId())){
                            $this->loyaltyHelper->addMemberIdToCustomer($customer->getId(), null);
                            return true;
                        }
                    }
                    $member = $this->loyaltyHelper->updateMember($member->getId(), json_encode($member_object));
                }
                catch (\DillerAPI\ApiException $e){
                    // TODO: return error message
                    return false;
                }
            }else{
                // register member in Diller
                try {
                    $member_object["consent"]["receive_sms"] = true;
                    $member_object["consent"]["receive_email"] = true;
                    $member = $this->loyaltyHelper->registerMember(json_encode($member_object));
                }
                catch (\DillerAPI\ApiException $e){
                    // TODO: return error message
                    return false;
                }
            }

            // Save member id in relation to customer
            if($member){
                if($customer->getCustomAttribute('diller_member_id') !== $member->getId()){
                    $this->loyaltyHelper->addMemberIdToCustomer($customer->getId(), $member->getId());
                }
            }

        }

        return true;
    }
}
