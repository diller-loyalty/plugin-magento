<?php
    namespace Diller\LoyaltyProgram\Model;

    use Diller\LoyaltyProgram\Helper\Data;
    use Magento\SalesRule\Model\RuleRepository;

    class CouponManagement {
        /**
         * @var Data
         */
        protected Data $loyaltyHelper;

        /**
         * @var \Magento\Framework\ObjectManagerInterface
         */
        protected $_objectManager;

        /**
         * @var \Magento\SalesRule\Model\RuleFactory
         */
        protected $ruleFactory;

        /**
         * @var \Magento\SalesRule\Model\RuleRepository
         */
        protected $ruleRepository;

        public function __construct(
            Data $loyaltyHelper,
            \Magento\Framework\ObjectManagerInterface $_objectManager,
            \Magento\SalesRule\Model\RuleFactory $ruleFactory,
            RuleRepository $ruleRepository) {
            $this->loyaltyHelper = $loyaltyHelper;
            $this->_objectManager = $_objectManager;
            $this->ruleFactory = $ruleFactory;
            $this->ruleRepository = $ruleRepository;
        }


        public function setCoupon($price_rule_id, $price_rule_data){
            $price_rule = false;
            if ($price_rule_id > 0) {
                try {
                    $price_rule = $this->ruleFactory->create()->load($price_rule_id);
                    if(array_key_exists("delete", $price_rule_data)){
                        if($price_rule_data['delete']){
                            $price_rule->delete();
                            return "Rule deleted";
                        }
                    }
                } catch (\Exception $ex) { // no price rule found
                }
            }
            if (!$price_rule) $price_rule = $this->ruleFactory->create();

            // set price rule data
            foreach ($price_rule_data as $key => $value) {
                $price_rule->setData($key, $value);
            }
            $price_rule->save();

            return $price_rule->getId() ?? false;
        }
    }
