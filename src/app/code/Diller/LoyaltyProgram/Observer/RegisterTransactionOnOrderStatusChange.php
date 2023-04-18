<?php

namespace Diller\LoyaltyProgram\Observer;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

use libphonenumber\PhoneNumberUtil;
use Exception;

class RegisterTransactionOnOrderStatusChange implements ObserverInterface{
    protected $request;
    protected $_coreRegistry;
    protected $customerRepository;
    protected Data $loyaltyHelper;

    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param Registry $coreRegistry
     * @param CustomerRepositoryInterface $customerRepository
     * @param Data $loyaltyHelper
     */
    public function __construct(
        RequestInterface $request,
        Registry $coreRegistry,
        CustomerRepositoryInterface $customerRepository,
        Data $loyaltyHelper
    ) {
        $this->request = $request;
        $this->_coreRegistry = $coreRegistry;
        $this->customerRepository = $customerRepository;
        $this->loyaltyHelper = $loyaltyHelper;
    }

    /**
     * Save order into registry to use it in the overloaded controller.
     *
     * @param EventObserver $observer
     * @return true
     */
    public function execute(EventObserver $observer){
        /** @var Order $order */
        $event = $observer->getEvent();
        $order = $event->getOrder();
        if ($order instanceof \Magento\Framework\Model\AbstractModel) {
            $diller_consent = $diller_order_history_consent = 0;

            if($quote = $event->getQuote()){
                $diller_consent = (boolean)$quote->getData('diller_consent');
                $diller_order_history_consent = (boolean)$quote->getData('diller_order_history_consent');
            }

            $is_member = $valid_phone_number = false;

            // get customer from order
            if(!$order->getData("customer_is_guest")){
                if($customer_id = $order->getData("customer_id")){
                    $is_member = ($member = $this->loyaltyHelper->searchMemberByCustomerId($customer_id));
                }

                // get customer phone number
                if(!$is_member){
                    $valid_phone_number = ($customer_phone_number = $this->loyaltyHelper->getCustomerPhoneNumber($customer_id));
                }
            }

            // get phone number from shipping address
            if(!$valid_phone_number){
                $valid_phone_number = ($customer_phone_number = $this->loyaltyHelper->getPhoneNumberFromAddress($order->getShippingAddress()));
            }

            // get checkout consent and register member
            if(!$is_member && $valid_phone_number && $diller_consent){
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
                $is_member = ($member = $this->loyaltyHelper->registerMember($member_object));
            }

            if($is_member){
                $diller_consent = $member->getConsent()->getGdprAccepted();
                $diller_order_history_consent = $member->getConsent()->getSaveOrderHistory();

                if($diller_order_history_consent){

//                if($order->getState() !== $this->loyaltyHelper->getSelectedOrderStatus()) {
//                    return false;
//                }

                    $order_products = $order->getItems();

                    $transaction = array(
                        "external_id" => $order->getId(),
                        "created_at" => date("c", strtotime($order->getCreatedAt())),
                        "payment_details" => array(
                            array(
                                "payment_method" => $order->getPayment()->getAdditionalInformation()["method_title"],
                                "sub_total" => (float)$order->getGrandTotal() ?? 0
                            )
                        ),
                        "send_email_receipt" => false,
                        "origin" => array(
                            "system_id" => "magento_" . $order->getStore()->getId(),
                            "employee_id" => "",
                            "department_id" => $this->loyaltyHelper->getSelectedDepartment()
                        ),
                        "total" => $order->getGrandTotal(),
                        "total_tax" => $order->getTaxAmount(),
                        "total_discount" => $order->getDiscountAmount(),
                        "currency" => $order->getOrderCurrency()->getCode(),
                        "coupon_codes" => array($order->getCouponCode() ?? ''),
                        "stamp_card_ids" => array(),
                        "department_id" => $this->loyaltyHelper->getSelectedDepartment()
                    );

                    $transaction_products = array();
                    foreach ($order_products as $product){
                        $transaction_products[] = array(
                            "product" =>  array(
                                "external_id" => $product->getProductId(),
                                "sku" => $product->getSku(),
                                "name" => $product->getName()
                            ),
                            "quantity" => $product->getQtyOrdered(),
                            "unit_price" => $product->getPrice(),
                            "unit_measure" => "",
                            "tax_percentage" => $product->getTaxPercent(),
                            "discount" => $product->getDiscountAmount(),
                            "total_price" => $product->getRowTotal()
                        );
                    }
                    $transaction['details'] = $transaction_products;

                    try {
                        $result = $this->loyaltyHelper->createTransaction($member['id'], json_encode($transaction));
                    }
                    catch (\DillerAPI\ApiException $e){
                        $error_details = json_decode($e->getResponseBody())->detail;
                        return false;
                    }
                }
            }

            // save diller consents with order
            $order->setData('diller_consent', $diller_consent);
            $order->setData('diller_order_history_consent', $diller_order_history_consent);
        }

        return true;
    }
}
