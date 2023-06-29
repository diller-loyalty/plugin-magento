<?php

namespace Diller\LoyaltyProgram\Controller\Quote;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;

class Save extends \Magento\Framework\App\Action\Action
{
    protected $quoteIdMaskFactory;

    protected $quoteRepository;
    private $resultRawFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        ResultFactory $resultRawFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->resultRawFactory = $resultRawFactory;
        parent::__construct($context);
    }

    /**
        * Execute request
        *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute(): Raw{
        $post = $this->getRequest()->getPostValue();
        if ($post) {
            $cartId                         = $post['cartId'];
            $diller_consent                 = $post['diller_consent'];
            $diller_order_history_consent   = $post['diller_order_history_consent'];
            $loggin                         = $post['is_customer'];

            if ($loggin === 'false') {
                $cartId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id')->getQuoteId();
            }

            $quote = $this->quoteRepository->getActive($cartId);
            if (!$quote->getItemsCount()) {
                throw new NoSuchEntityException(__('Cart %1 doesn\'t contain products', $cartId));
            }

            $quote->setData('diller_consent', $diller_consent);
            $quote->setData('diller_order_history_consent', $diller_order_history_consent);
            $this->quoteRepository->save($quote);
        }

        /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
        $resultRaw = $this->resultRawFactory->create();
        return $resultRaw;
    }
}
