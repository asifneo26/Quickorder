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

namespace Lof\FastOrder\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Lof\FastOrder\Helper\Data as FastOrderHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;

class QuickOrder extends Template
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var FastOrderHelper
     */
    protected $fastOrderHelper;

    /**
     * @var JsonHelper
     */
    protected $jsonHelper;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param FastOrderHelper $fastOrderHelper
     * @param JsonHelper $jsonHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        FastOrderHelper $fastOrderHelper,
        JsonHelper $jsonHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
        $this->fastOrderHelper = $fastOrderHelper;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * Get store ID
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Get store code
     */
    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get base URL
     */
    public function getBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * Get configuration data for Vue.js components
     */
    public function getQuickOrderConfig()
    {
        $config = [
            'baseUrl' => $this->getBaseUrl(),
            'storeId' => $this->getStoreId(),
            'storeCode' => $this->getStoreCode(),
            'uploadUrl' => $this->getUrl('loffastforder/fastorder/uploadCsv'),
            'downloadUrl' => $this->getUrl('loffastforder/fastorder/downloadCsv'),
            'searchUrl' => $this->getUrl('loffastforder/fastorder/searchProducts'),
            'addToCartUrl' => $this->getUrl('loffastforder/fastorder/addToCart'),
            'productDetailsUrl' => $this->getUrl('loffastforder/fastorder/getProductDetails'),
            'currencySymbol' => $this->storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol(),
            'maxFileSize' => 5 * 1024 * 1024, // 5MB
            'maxProducts' => 2000,
            'translations' => $this->getTranslations()
        ];

        return $this->jsonHelper->jsonEncode($config);
    }

    /**
     * Get translations for Vue.js components
     */
    protected function getTranslations()
    {
        return [
            'quickOrder' => __('Quick Order'),
            'uploadCsvFiles' => __('Upload CSV files or search products manually for bulk ordering'),
            'searchProducts' => __('Search Products'),
            'searchByProductName' => __('Search by product name, SKU, or manufacturer'),
            'bulkUpload' => __('Bulk Upload'),
            'downloadCsvTemplate' => __('Download CSV Template'),
            'dropYourCsvFile' => __('Drop your CSV file here'),
            'orClickToBrowse' => __('or click to browse files'),
            'chooseFile' => __('Choose File'),
            'supportsCsvFiles' => __('Supports CSV files up to 5MB with up to 2000 products'),
            'orderSummary' => __('Order Summary'),
            'productName' => __('Product Name'),
            'packSize' => __('Pack Size'),
            'qty' => __('Qty'),
            'freeQty' => __('Free Qty'),
            'mrp' => __('MRP'),
            'ptr' => __('PTR'),
            'discount' => __('Discount%'),
            'subTotal' => __('Sub Total'),
            'totalItems' => __('Total Items'),
            'totalProducts' => __('Total Products'),
            'estimatedValue' => __('Estimated Value'),
            'addToCart' => __('Add to Cart'),
            'productsCouldNotBeMatched' => __('products could not be matched. Please review them below.'),
            'itemsHaveQuantityErrors' => __('items have quantity errors. Please check available stock and adjust quantities.'),
            'pleaseReviewThem' => __('Please review them below.'),
            'pleaseCheckAvailableStock' => __('Please check available stock and adjust quantities.'),
            'onlyUnitsAvailable' => __('Only %1 units available in stock'),
            'max' => __('Max'),
            'original' => __('Original'),
            'searchProducts' => __('Search products...'),
            'noProductsFound' => __('No products found'),
            'loading' => __('Loading...'),
            'error' => __('Error'),
            'success' => __('Success'),
            'invalidFile' => __('Invalid file format. Please upload a CSV file.'),
            'fileTooLarge' => __('File too large. Maximum size is 5MB.'),
            'tooManyProducts' => __('Too many products. Maximum is 2000 products per file.'),
            'uploadSuccessful' => __('CSV file uploaded successfully'),
            'addedToCart' => __('Products added to cart successfully'),
            'confirmRemove' => __('Are you sure you want to remove this item?'),
            'remove' => __('Remove'),
            'cancel' => __('Cancel'),
            'confirm' => __('Confirm')
        ];
    }
}