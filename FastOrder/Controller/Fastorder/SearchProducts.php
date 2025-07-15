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
use Lof\FastOrder\Model\FastSearchProductManagement;
use Magento\Store\Model\StoreManagerInterface;

class SearchProducts extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var FastSearchProductManagement
     */
    protected $searchProductManagement;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param FastSearchProductManagement $searchProductManagement
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FastSearchProductManagement $searchProductManagement,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->searchProductManagement = $searchProductManagement;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute action
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $query = $this->getRequest()->getParam('query', '');
            $storeId = $this->getRequest()->getParam('store_id', $this->storeManager->getStore()->getId());
            $limit = $this->getRequest()->getParam('limit', 10);

            if (strlen($query) < 2) {
                return $result->setData([
                    'success' => true,
                    'products' => []
                ]);
            }

            $products = $this->searchProductManagement->searchProducts($query, $storeId, $limit);

            return $result->setData([
                'success' => true,
                'products' => $products
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
                'products' => []
            ]);
        }
    }
}