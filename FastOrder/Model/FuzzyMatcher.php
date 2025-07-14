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

namespace Lof\FastOrder\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fuzzy Matching Service for Product Names
 * 
 * This class implements fuzzy matching algorithm specifically designed for pharmaceutical products
 * where names can have variations, abbreviations, or slight differences.
 */
class FuzzyMatcher
{
    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Common pharmaceutical abbreviations and synonyms
     * @var array
     */
    protected $pharmaAbbreviations = [
        'tab' => 'tablet',
        'tabs' => 'tablets',
        'cap' => 'capsule',
        'caps' => 'capsules',
        'inj' => 'injection',
        'sol' => 'solution',
        'syr' => 'syrup',
        'susp' => 'suspension',
        'mg' => 'milligram',
        'mcg' => 'microgram',
        'ml' => 'milliliter',
        'gm' => 'gram',
        'iu' => 'international unit'
    ];

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistry
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Find matching products for a given product name with fuzzy matching
     *
     * @param string $searchName
     * @param int $storeId
     * @param int $limit
     * @return array
     */
    public function findMatches($searchName, $storeId = null, $limit = 5)
    {
        if (empty($searchName)) {
            return [];
        }

        $storeId = $storeId ?: $this->storeManager->getStore()->getId();
        $normalizedSearchName = $this->normalizeProductName($searchName);

        // Get all products from the store
        $productCollection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'sku', 'price', 'special_price', 'manufacturer'])
            ->addStoreFilter($storeId)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);

        $matches = [];

        foreach ($productCollection as $product) {
            $productName = $product->getName();
            $normalizedProductName = $this->normalizeProductName($productName);
            
            // Calculate similarity score
            $score = $this->calculateSimilarityScore($normalizedSearchName, $normalizedProductName, $searchName, $productName);
            
            if ($score > 30) { // Minimum threshold of 30%
                $stockItem = $this->stockRegistry->getStockItem($product->getId(), $storeId);
                
                $matches[] = [
                    'entity_id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $productName,
                    'price' => $product->getPrice(),
                    'special_price' => $product->getSpecialPrice(),
                    'manufacturer' => $product->getManufacturer(),
                    'stock_qty' => $stockItem->getQty(),
                    'is_in_stock' => $stockItem->getIsInStock(),
                    'pack_size' => $this->extractPackSize($productName),
                    'match_score' => $score,
                    'normalized_name' => $normalizedProductName
                ];
            }
        }

        // Sort by match score descending
        usort($matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return array_slice($matches, 0, $limit);
    }

    /**
     * Calculate similarity score between two product names
     *
     * @param string $normalizedSearch
     * @param string $normalizedProduct
     * @param string $originalSearch
     * @param string $originalProduct
     * @return float
     */
    protected function calculateSimilarityScore($normalizedSearch, $normalizedProduct, $originalSearch, $originalProduct)
    {
        $scores = [];

        // 1. Exact match (highest priority)
        if (strtolower($originalSearch) === strtolower($originalProduct)) {
            return 100;
        }

        // 2. Exact normalized match
        if ($normalizedSearch === $normalizedProduct) {
            $scores[] = 95;
        }

        // 3. Levenshtein distance (character-based similarity)
        $levenshteinScore = $this->calculateLevenshteinScore($normalizedSearch, $normalizedProduct);
        $scores[] = $levenshteinScore;

        // 4. Jaro-Winkler similarity (good for name matching)
        $jaroWinklerScore = $this->calculateJaroWinklerScore($normalizedSearch, $normalizedProduct);
        $scores[] = $jaroWinklerScore;

        // 5. Token-based matching (word similarity)
        $tokenScore = $this->calculateTokenScore($normalizedSearch, $normalizedProduct);
        $scores[] = $tokenScore;

        // 6. Substring matching
        $substringScore = $this->calculateSubstringScore($normalizedSearch, $normalizedProduct);
        $scores[] = $substringScore;

        // 7. Pharmaceutical-specific matching
        $pharmaScore = $this->calculatePharmaScore($normalizedSearch, $normalizedProduct);
        $scores[] = $pharmaScore;

        // Return weighted average with emphasis on higher scores
        $weights = [0.3, 0.25, 0.2, 0.15, 0.1];
        rsort($scores); // Sort scores descending
        
        $weightedScore = 0;
        for ($i = 0; $i < min(count($scores), count($weights)); $i++) {
            $weightedScore += $scores[$i] * $weights[$i];
        }

        return $weightedScore;
    }

    /**
     * Normalize product name for better matching
     *
     * @param string $name
     * @return string
     */
    protected function normalizeProductName($name)
    {
        // Convert to lowercase
        $normalized = strtolower($name);
        
        // Remove special characters and extra spaces
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);
        
        // Expand common pharmaceutical abbreviations
        foreach ($this->pharmaAbbreviations as $abbrev => $full) {
            $normalized = preg_replace('/\b' . preg_quote($abbrev) . '\b/', $full, $normalized);
        }
        
        // Remove common words that don't add value
        $stopWords = ['the', 'and', 'or', 'of', 'in', 'for', 'with', 'by'];
        $words = explode(' ', $normalized);
        $words = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) > 1;
        });
        
        return implode(' ', $words);
    }

    /**
     * Calculate Levenshtein-based score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateLevenshteinScore($str1, $str2)
    {
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen == 0) return 100;
        
        $distance = levenshtein($str1, $str2);
        return max(0, (1 - $distance / $maxLen) * 100);
    }

    /**
     * Calculate Jaro-Winkler similarity score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateJaroWinklerScore($str1, $str2)
    {
        $jaro = $this->calculateJaroScore($str1, $str2);
        
        // Calculate common prefix length (up to 4 characters)
        $prefixLength = 0;
        $maxPrefix = min(4, min(strlen($str1), strlen($str2)));
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefixLength++;
            } else {
                break;
            }
        }
        
        // Jaro-Winkler = Jaro + (prefix_length * p * (1 - Jaro))
        // where p is typically 0.1
        return $jaro + ($prefixLength * 0.1 * (1 - $jaro));
    }

    /**
     * Calculate Jaro similarity score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateJaroScore($str1, $str2)
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 == 0 && $len2 == 0) return 1.0;
        if ($len1 == 0 || $len2 == 0) return 0.0;
        
        $matchWindow = max($len1, $len2) / 2 - 1;
        $matchWindow = max(0, $matchWindow);
        
        $matches1 = array_fill(0, $len1, false);
        $matches2 = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;
        
        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);
            
            for ($j = $start; $j < $end; $j++) {
                if ($matches2[$j] || $str1[$i] !== $str2[$j]) continue;
                
                $matches1[$i] = true;
                $matches2[$j] = true;
                $matches++;
                break;
            }
        }
        
        if ($matches == 0) return 0.0;
        
        // Count transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$matches1[$i]) continue;
            
            while (!$matches2[$k]) $k++;
            
            if ($str1[$i] !== $str2[$k]) $transpositions++;
            $k++;
        }
        
        $jaro = ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
        
        return $jaro * 100; // Convert to percentage
    }

    /**
     * Calculate token-based score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateTokenScore($str1, $str2)
    {
        $tokens1 = explode(' ', $str1);
        $tokens2 = explode(' ', $str2);
        
        $commonTokens = array_intersect($tokens1, $tokens2);
        $totalTokens = array_unique(array_merge($tokens1, $tokens2));
        
        if (empty($totalTokens)) return 0;
        
        return (count($commonTokens) / count($totalTokens)) * 100;
    }

    /**
     * Calculate substring matching score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateSubstringScore($str1, $str2)
    {
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            $shorter = strlen($str1) < strlen($str2) ? $str1 : $str2;
            $longer = strlen($str1) >= strlen($str2) ? $str1 : $str2;
            return (strlen($shorter) / strlen($longer)) * 80; // 80% max for substring matches
        }
        
        return 0;
    }

    /**
     * Calculate pharmaceutical-specific score
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculatePharmaScore($str1, $str2)
    {
        $score = 0;
        
        // Extract dosage information
        $dosage1 = $this->extractDosage($str1);
        $dosage2 = $this->extractDosage($str2);
        
        if ($dosage1 && $dosage2 && $dosage1 === $dosage2) {
            $score += 20; // Bonus for matching dosage
        }
        
        // Extract form information (tablet, capsule, etc.)
        $form1 = $this->extractForm($str1);
        $form2 = $this->extractForm($str2);
        
        if ($form1 && $form2 && $form1 === $form2) {
            $score += 15; // Bonus for matching form
        }
        
        return $score;
    }

    /**
     * Extract dosage from product name
     *
     * @param string $name
     * @return string|null
     */
    protected function extractDosage($name)
    {
        // Pattern to match dosage like "500mg", "20mcg", etc.
        if (preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|gm|ml|iu)/i', $name, $matches)) {
            return strtolower($matches[1] . $matches[2]);
        }
        
        return null;
    }

    /**
     * Extract pharmaceutical form from product name
     *
     * @param string $name
     * @return string|null
     */
    protected function extractForm($name)
    {
        $forms = ['tablet', 'capsule', 'injection', 'syrup', 'suspension', 'solution'];
        
        foreach ($forms as $form) {
            if (strpos(strtolower($name), $form) !== false) {
                return $form;
            }
        }
        
        return null;
    }

    /**
     * Extract pack size from product name
     *
     * @param string $name
     * @return string
     */
    protected function extractPackSize($name)
    {
        // Pattern to match pack sizes like "10x10", "1x3", "20ml", etc.
        if (preg_match('/(\d+x\d+|\d+\s*ml|\d+\s*gm)/i', $name, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    /**
     * Get confidence level based on score
     *
     * @param float $score
     * @return string
     */
    public function getConfidenceLevel($score)
    {
        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 60) {
            return 'medium';
        } elseif ($score >= 30) {
            return 'low';
        } else {
            return 'none';
        }
    }
}