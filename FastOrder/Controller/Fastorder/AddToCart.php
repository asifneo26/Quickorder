<?php
/**
 * Copyright (c) 2019  Landofcoder
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 */

namespace Lof\FastOrder\Controller\Fastorder;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AddToCart extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Cart $cart
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Execute action
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $items = $this->getRequest()->getParam('items', []);
            $storeId = $this->getRequest()->getParam('store_id', $this->storeManager->getStore()->getId());
            
            if (empty($items)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('No items to add to cart')
                ]);
            }

            $addedItems = 0;
            $errors = [];

            foreach ($items as $item) {
                try {
                    $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;

                    if ($productId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $product = $this->productRepository->getById($productId, false, $storeId);
                    
                    if (!$product->getId()) {
                        $errors[] = __('Product with ID %1 not found', $productId);
                        continue;
                    }

                    $params = [
                        'product' => $productId,
                        'qty' => $qty
                    ];

                    $this->cart->addProduct($product, $params);
                    $addedItems++;

                } catch (LocalizedException $e) {
                    $errors[] = $e->getMessage();
                } catch (\Exception $e) {
                    $this->logger->error('Error adding product to cart: ' . $e->getMessage());
                    $errors[] = __('Error adding product to cart');
                }
            }

            $this->cart->save();

            $response = [
                'success' => $addedItems > 0,
                'message' => $addedItems > 0 
                    ? __('Successfully added %1 item(s) to cart', $addedItems)
                    : __('No items were added to cart'),
                'added_items' => $addedItems,
                'cart_url' => $this->_url->getUrl('checkout/cart')
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return $result->setData($response);

        } catch (\Exception $e) {
            $this->logger->error('Bulk add to cart error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while adding items to cart: %1', $e->getMessage())
            ]);
        }
    }
}