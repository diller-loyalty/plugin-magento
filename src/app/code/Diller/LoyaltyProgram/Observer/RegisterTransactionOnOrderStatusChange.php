<?php

namespace Diller\LoyaltyProgram\Observer;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

class RegisterTransactionOnOrderStatusChange implements ObserverInterface{
    protected $request;
    protected $customer;
    protected $_coreRegistry;
    protected $customerRepository;
    protected Data $loyaltyHelper;

    public function __construct(RequestInterface $request, Customer $customer, Registry $coreRegistry, CustomerRepositoryInterface $customerRepository, Data $loyaltyHelper) {
        $this->request = $request;
        $this->customer = $customer;
        $this->_coreRegistry = $coreRegistry;
        $this->customerRepository = $customerRepository;
        $this->loyaltyHelper = $loyaltyHelper;
    }

    /**
     * Sent transaction to Diller if consent was given and if the order status matches the status chosen in the module backoffice.
     *
     * @param EventObserver $observer
     * @return true
     */
    public function execute(EventObserver $observer){
        $event = $observer->getEvent();
        $order = $event->getOrder();

        if ($order instanceof \Magento\Framework\Model\AbstractModel) {
            $is_member = false;

            // get Diller consents from order object
            $diller_consent = (boolean)($order->getData('diller_consent') ?? 0);
            $diller_order_history_consent = (boolean)($order->getData('diller_order_history_consent') ?? 0);

            // get member id from order object
            if($member_id = $order->getData('diller_member_id')){
                $is_member = ($member = $this->loyaltyHelper->getMemberById($member_id));
            }

            if(!$is_member){
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
                        $result = $this->getMember('', $customer_phone_number['country_code'].$customer_phone_number['national_number']);
                        if(!empty($result)){
                            $is_member = ($member = $result[0]);
                        }
                    };
                }
            }

            if($is_member){
                if(!$diller_consent) $diller_consent = $member->getConsent()->getGdprAccepted();
                if(!$diller_order_history_consent) $diller_order_history_consent = $member->getConsent()->getSaveOrderHistory();

                if($diller_consent && $diller_order_history_consent){

                    //send transaction to Diller when the order status matches the option chosen in the backoffice
//                    if($order->getState() !== $this->loyaltyHelper->getSelectedOrderStatus()) {
//                        return true;
//                    }

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

                if($customer_id = $order->getCustomerId()){
                    if($customer = $this->customer->load($customer_id)){
                        $customerData = $customer->getDataModel();
                        $customerData->setCustomAttribute('diller_member_id',(string)$member['id'] ?? '');
                        $customer->updateData($customerData);
                        $customerResource = $this->customerFactory->create();
                        $customerResource->saveAttribute($customer, 'diller_member_id');
                    };
                }
            }
        }

        return true;
    }
}
