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
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Data
     */
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
        $order = $observer->getEvent()->getOrder();
        if ($order instanceof \Magento\Framework\Model\AbstractModel) {
            $order_data = $order->getStoredData();

            // get customer from order
            $customer = $this->customerRepository->getById($order_data['customer_id']);
            $is_member = false;

            // get member_id from customer
            if($member_id = $customer->getCustomAttribute('diller_member_id')){
                if($member = $this->loyaltyHelper->getMemberById($member_id)){
                    $is_member = true;
                }
            }

            // search member with phone number
            foreach ($customer->getAddresses() as $customer_address){
                // Get customer phone and check if it exists in Diller
                $customerPhone = $customerPhone ?? $customer_address->getTelephone();
                $customerPhone = preg_replace("/[^0-9+]/", "", $customerPhone ?? "");
                $country_code = $country_code ?? $customer_address->getCountryId() ?? "NO";

                // Check if phone is in international format
                if(preg_match("/^(\+|00)/", $customerPhone)){
                    $country_code = "";
                    $customerPhone = preg_replace("/^00/", "+", $customerPhone);
                }

                try {
                    if(($phone_number_proto = PhoneNumberUtil::getInstance()->parse($customerPhone, $country_code)) && PhoneNumberUtil::getInstance()->isValidNumber($phone_number_proto)) {
                        $phone_country_code = '00' . $phone_number_proto->getCountryCode();
                        $phone_national_number = $phone_number_proto->getNationalNumber();
                        $country_code = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($phone_number_proto);

                        // check if customer is a Diller member
                        $result = $this->loyaltyHelper->getMember('', $phone_country_code.$phone_national_number);
                        if(!empty($result)){
                            $member = $result[0];
                            $is_member = true;
                            continue;
                        }
                    }
                }
                catch (Exception $ex){}
            }


            // get checkout consent
            if(!$is_member && $checkout_consent = $order->getShippingAddress()->getCustomAttribute('diller_consent')){
                // register member
            }

            if($is_member){
//                if($order->getState() !== $this->loyaltyHelper->getSelectedOrderStatus()) {
//                    return false;
//                }

                $order_products = $order->getItems();

                $transaction = array(
                    "external_id" => $order_data['increment_id'],
                    "created_at" => date("c", strtotime($order_data['created_at'])),
                    "payment_details" => array(
                        array(
                            "payment_method" => $order->getPayment()->getAdditionalInformation()["method_title"],
                            "sub_total" => (float)$order_data['grand_total'] ?? 0
                        )
                    ),
                    "send_email_receipt" => false,
                    "total" => $order_data['grand_total'],
                    "total_tax" => $order_data['tax_amount'],
                    "total_discount" => $order_data['discount_amount'],
                    "currency" => $order_data['order_currency_code'],
                    "coupon_codes" => array($order_data['coupon_code']),
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
                    return false;
                }
            }


            die;
        }
        return true;
    }
}
