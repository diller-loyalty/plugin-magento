<?php

namespace Diller\LoyaltyProgram\Controller\Quote;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class Save extends Action{
    protected QuoteIdMaskFactory $quoteIdMaskFactory;
    protected CartRepositoryInterface $quoteRepository;

    public function __construct(
        Context $context,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        parent::__construct($context);
    }

    /**
     * Execute request
     *
     * @throws NoSuchEntityException
     */
    public function execute(): void
    {
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
    }
}
