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
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Lof\FastOrder\Controller\Fastorder;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManager;
use Lof\FastOrder\Model\FuzzyMatcher;
use Psr\Log\LoggerInterface;

class UploadCsv extends Action
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Filesystem $filesystem
     */
    protected $filesystem;

    /**
     * @var UploaderFactory $fileUploader
     */
    protected $fileUploader;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $mediaDirectory;

    /**
     * @var Data
     */
    private $jsonHelper;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * CSV Processor
     */
    protected $csvProcessor;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var \Lof\FastOrder\Helper\Data
     */
    private $dataHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StockItemRepository
     */
    private $stockItemRepository;

    /** @var PriceCurrencyInterface $priceCurrency */
    private $priceCurrency;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var FuzzyMatcher
     */
    private $fuzzyMatcher;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Data $jsonHelper
     * @param Filesystem $filesystem
     * @param Csv $csvProcessor
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param \Lof\FastOrder\Helper\Data $dataHelper
     * @param LoggerInterface $logger
     * @param StockItemRepository $stockItemRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManager $storeManager
     * @param FuzzyMatcher $fuzzyMatcher
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $jsonHelper,
        Filesystem $filesystem,
        Csv $csvProcessor,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        \Lof\FastOrder\Helper\Data $dataHelper,
        LoggerInterface $logger,
        StockItemRepository $stockItemRepository,
        PriceCurrencyInterface $priceCurrency,
        StoreManager $storeManager,
        FuzzyMatcher $fuzzyMatcher
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->csvProcessor = $csvProcessor;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->stockItemRepository = $stockItemRepository;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
        $this->fuzzyMatcher = $fuzzyMatcher;

        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    /**
     * Execute method
     *
     * @return ResultInterface
     */
    public function execute()
    {
        try {
            $result = $this->uploadFile();

            return $this->jsonResponse([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Upload file and return parsed product list
     *
     * @return array
     * @throws \Exception
     */
    protected function uploadFile()
    {
        $uploadFolder = 'fast-order-csv/';
        $inputField = 'csv_file';

        $filePath = $this->dataHelper->uploadFile(
            $this->getRequest(),
            $uploadFolder,
            $inputField,
            ['csv']
        );

        if (!$filePath || !file_exists($filePath)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('File upload failed or file does not exist.')
            );
        }

        return $this->readCsvFile($filePath);
    }

    /**
     * Read data from CSV file with fuzzy matching
     *
     * @param string $filePath
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function readCsvFile($filePath)
    {
        try {
            $csvData = $this->csvProcessor->getData($filePath);
        } catch (\Exception $e) {
            $this->logger->critical(__('CSV Parsing Error: %1', $e->getMessage()));
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to read CSV file.')
            );
        }

        if (empty($csvData)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('CSV file is empty or invalid.')
            );
        }

        $storeId = $this->getRequest()->getParam('store_id', $this->storeManager->getStore()->getId());
        
        // Parse CSV headers
        $headers = array_map('trim', $csvData[0]);
        $headerMap = $this->mapCsvHeaders($headers);
        
        if (!isset($headerMap['product_name'])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('CSV file must contain a "Product Name" column.')
            );
        }

        $processedItems = [];
        $totalRows = count($csvData);
        
        // Process each row (skip header)
        for ($i = 1; $i < $totalRows; $i++) {
            $row = $csvData[$i];
            
            if (empty($row) || count($row) < count($headers)) {
                continue;
            }

            $originalName = trim($row[$headerMap['product_name']]);
            if (empty($originalName)) {
                continue;
            }

            $qty = isset($headerMap['qty']) && isset($row[$headerMap['qty']]) 
                ? (int)$row[$headerMap['qty']] 
                : 1;
                
            $freeQty = isset($headerMap['free_qty']) && isset($row[$headerMap['free_qty']]) 
                ? (int)$row[$headerMap['free_qty']] 
                : 0;

            // Find matching products using fuzzy matching
            $matches = $this->fuzzyMatcher->findMatches($originalName, $storeId, 5);
            
            $bestMatch = null;
            $matchScore = 0;
            
            if (!empty($matches)) {
                $bestMatch = $matches[0];
                $matchScore = $bestMatch['match_score'];
            }

            $processedItems[] = [
                'original_name' => $originalName,
                'qty' => $qty,
                'free_qty' => $freeQty,
                'matched_product' => $bestMatch,
                'match_score' => $matchScore,
                'suggestions' => $matches,
                'confidence' => $this->fuzzyMatcher->getConfidenceLevel($matchScore)
            ];
        }

        return $processedItems;
    }

    /**
     * Map CSV headers to expected column names
     *
     * @param array $headers
     * @return array
     */
    private function mapCsvHeaders($headers)
    {
        $headerMap = [];
        $mapping = [
            'product_name' => ['product name', 'product_name', 'name', 'item name', 'drug name'],
            'qty' => ['qty', 'quantity', 'order qty', 'order quantity'],
            'free_qty' => ['free qty', 'free_qty', 'free quantity', 'bonus qty']
        ];

        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));
            
            foreach ($mapping as $field => $variations) {
                if (in_array($normalizedHeader, $variations)) {
                    $headerMap[$field] = $index;
                    break;
                }
            }
        }

        return $headerMap;
    }

    /**
     * Create JSON response
     *
     * @param array $response
     * @return ResultInterface
     */
    public function jsonResponse(array $response = [])
    {
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($response)
        );
    }
}
