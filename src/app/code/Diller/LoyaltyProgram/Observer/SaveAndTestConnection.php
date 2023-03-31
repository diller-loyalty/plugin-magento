<?php
    namespace Diller\LoyaltyProgram\Observer;
    use Magento\Framework\Event\ObserverInterface;
    use Magento\Framework\Event\Observer as EventObserver;
    use Magento\Framework\App\RequestInterface;
    use Magento\Framework\App\Config\Storage\WriterInterface;

    use DillerLoyalty\DillerApi;

    class SaveAndTestConnection implements ObserverInterface {
        private $request;
        private $configWriter;
        public function __construct(RequestInterface $request, WriterInterface $configWriter) {
            $this->request = $request;
            $this->configWriter = $configWriter;
        }
        public function execute(EventObserver $observer) {
            $dillerParams = $this->request->getParam('groups');
            $enabled = $dillerParams['settings']['fields']['loyalty_program_enabled']['value'];
            $store_uid = $dillerParams['settings']['fields']['store_uid']['value'];
            $api_key = $dillerParams['settings']['fields']['api_key']['value'];

            $diller_loyalty = new \DillerAPI\DillerAPI($store_uid, $api_key);

            try {
                $store = $diller_loyalty->Stores->get($store_uid);
                \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->info('DILLER LOYALTY - Connection done!');
            } catch (\DillerAPI\ApiException $e) {
                \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->error('DILLER LOYALTY - ' . json_decode($e->getResponseBody()));
            }

            return $this;
        }
    }
