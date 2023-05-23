<?php

    namespace Diller\LoyaltyProgram\Observer;

    use Diller\LoyaltyProgram\Helper\Data;
    use Magento\Customer\Api\CustomerRepositoryInterface;
    use Magento\Customer\Model\Customer;
    use Magento\Customer\Model\ResourceModel\CustomerFactory;
    use Magento\Framework\App\RequestInterface;
    use Magento\Framework\Event\ObserverInterface;
    use Magento\Framework\Event\Observer as EventObserver;
    use Magento\Framework\Registry;
    use Magento\Sales\Model\Order;

    class GetCustomerConsentAndRegisterMemberOnCheckoutSubmitBefore implements ObserverInterface{
        protected $request;
        protected $customer;
        protected $_coreRegistry;
        protected $customerFactory;
        protected $customerRepository;
        protected Data $loyaltyHelper;

        public function __construct(RequestInterface $request, Customer $customer, Registry $coreRegistry, CustomerFactory $customerFactory, CustomerRepositoryInterface $customerRepository, Data $loyaltyHelper) {
            $this->request = $request;
            $this->customer = $customer;
            $this->_coreRegistry = $coreRegistry;
            $this->customerFactory = $customerFactory;
            $this->customerRepository = $customerRepository;
            $this->loyaltyHelper = $loyaltyHelper;
        }

        /**
         * Get consent from checkout and set add member_id element to order object
         *
         * @param EventObserver $observer
         * @return true
         */
        public function execute(EventObserver $observer){
            $event = $observer->getEvent();
            $order = $event->getOrder();

            if ($order instanceof \Magento\Framework\Model\AbstractModel) {
                $diller_consent = $diller_order_history_consent = $is_member = $valid_phone_number = false;

                // get Diller consents from checkout checkboxes
                if($quote = $event->getQuote()){
                    $diller_consent = (boolean)$quote->getData('diller_consent');
                    $diller_order_history_consent = (boolean)$quote->getData('diller_order_history_consent');

                    // save diller consents to order
                    $order->setData('diller_consent', $diller_consent);
                    $order->setData('diller_order_history_consent', $diller_order_history_consent);
                }

                // get customer from order
                if(!$order->getData("customer_is_guest")){
                    if($customer_id = $order->getData("customer_id")){
                        $is_member = ($member = $this->loyaltyHelper->searchMemberByCustomerId($customer_id));

                        if(!$is_member){
                            $valid_phone_number = ($customer_phone_number = $this->loyaltyHelper->getCustomerPhoneNumber($customer_id));
                        }
                    }
                }

                // get phone number from shipping address
                if(!$valid_phone_number){
                    if($customer_phone_number = $this->loyaltyHelper->getPhoneNumberFromAddress($order->getShippingAddress())){
                        $valid_phone_number = true;
                        $result = $this->loyaltyHelper->getMember('', $customer_phone_number['country_code'].$customer_phone_number['national_number']);
                        if(!empty($result)){
                            $is_member = ($member = $result[0]);
                        }
                    };
                }

                // get checkout consent and register member
                if($valid_phone_number && !$is_member && $diller_consent){
                    $member_object = array(
                        "first_name" => $order->getCustomerFirstname(),
                        "last_name" => $order->getCustomerLastname(),
                        "email" => $order->getCustomerEmail(),
                        "phone" => array(
                            "country_code" => $customer_phone_number['country_code'],
                            "number" => $customer_phone_number['national_number']
                        ),
                        "consent" => array(
                            "gdpr_accepted" => true,
                            "receive_sms" => true,
                            "receive_email" => true,
                            "save_order_history" => $diller_order_history_consent
                        ),
                        "department_ids" => []
                    );
                    if($address = $order->getAddresses()[0]){
                        $street = $order->getAddresses()[0]->getStreet();
                        if(!empty($street)){
                            $result = !empty($street[0]) ? $street[0] : '';
                            if(!empty($street[1])) $result .= ' ' . $street[1];
                            $street = $result;
                        }
                        $zip_code = $order->getAddresses()[0]->getPostCode();
                        $member_object['address'] = array(
                            "street" => $street ?? '',
                            "city" => $address->getCity() ?? '',
                            "zip_code" => isset($zip_code) ? filter_var($zip_code, FILTER_SANITIZE_NUMBER_INT) : '',
                            "state" => $order->getAddresses()[0]->getState() ?? '',
                            "country_code" => strtoupper($order->getAddresses()[0]->getCountryId()) ?? ''
                        );
                    }
                    if($member = $this->loyaltyHelper->registerMember($member_object)){
                        $order->setData('diller_member_id', $member->getId());
                        $is_member = true;
                        if($customer_id = $order->getCustomerId()){
                            if($customer = $this->customer->load($customer_id)){
                                $customerData = $customer->getDataModel();
                                $customerData->setCustomAttribute('diller_member_id',(string)$member['id'] ?? '');
                                $customer->updateData($customerData);
                                $customerResource = $this->customerFactory->create();
                                $customerResource->saveAttribute($customer, 'diller_member_id');
                            }
                        }
                    }
                }

                // reserve coupon / stamp card
                if($is_member && ($order_coupons = $order->getCouponCode())){
                    if(!empty($order_coupons)){
                        foreach ($order_coupons as $coupon){
                            if($result = $this->loyaltyHelper->validateMemberCoupon($member->getId(), $coupon)){
                                if($result->getIsOk()){
                                    $this->loyaltyHelper->reserveMemberCoupon($member->getId(), $coupon, $order->getId());
                                }
                            }
                        }
                    }
                }
            }
            return true;
        }
    }
