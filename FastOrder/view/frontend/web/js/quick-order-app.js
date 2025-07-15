define([
    'jquery',
    'mage/url',
    'mage/storage',
    'mage/translate',
    'domReady!'
], function ($, urlBuilder, storage, $t) {
    'use strict';

    $.widget('lof.quickOrderApp', {
        options: {
            config: null
        },

        _create: function() {
            this.config = JSON.parse(this.element.data('config'));
            this.translations = this.config.translations;
            this.initializeApp();
        },

        initializeApp: function() {
            const self = this;
            
            // Wait for Vue.js to be available (loaded via RequireJS separately)
            this.waitForVue().then(() => {
                self.createVueApp();
            }).catch(() => {
                // Fallback to vanilla JS if Vue is not available
                console.warn('Vue.js not available, using fallback implementation');
                self.createFallbackApp();
            });
        },

        waitForVue: function() {
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const maxAttempts = 50;
                
                const checkVue = () => {
                    if (window.Vue) {
                        resolve();
                    } else if (attempts < maxAttempts) {
                        attempts++;
                        setTimeout(checkVue, 100);
                    } else {
                        reject();
                    }
                };
                
                checkVue();
            });
        },

        createVueApp: function() {
            const self = this;
            const { createApp } = Vue;
            
            const app = createApp({
                data() {
                    return {
                        // State
                        orderItems: [],
                        searchQuery: '',
                        searchResults: [],
                        isSearching: false,
                        isUploading: false,
                        showSearchResults: false,
                        
                        // Error handling
                        unmatchedProducts: 0,
                        quantityErrors: 0,
                        errorMessages: [],
                        
                        // Configuration
                        config: self.config,
                        translations: self.translations
                    };
                },

                computed: {
                    totalItems() {
                        return this.orderItems.reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0);
                    },

                    totalProducts() {
                        return this.orderItems.length;
                    },

                    estimatedValue() {
                        return this.orderItems.reduce((sum, item) => {
                            const qty = parseInt(item.qty) || 0;
                            const price = parseFloat(item.ptr) || 0;
                            return sum + (qty * price);
                        }, 0);
                    },

                    canAddToCart() {
                        return this.orderItems.length > 0 && 
                               this.quantityErrors === 0 && 
                               !this.isUploading;
                    },

                    hasErrors() {
                        return this.unmatchedProducts > 0 || this.quantityErrors > 0;
                    }
                },

                methods: {
                    // Search functionality
                    async searchProducts() {
                        if (this.searchQuery.length < 2) {
                            this.searchResults = [];
                            this.showSearchResults = false;
                            return;
                        }

                        this.isSearching = true;
                        
                        try {
                            const response = await this.makeApiRequest('searchUrl', {
                                query: this.searchQuery,
                                store_id: this.config.storeId
                            });
                            
                            this.searchResults = response.products || [];
                            this.showSearchResults = true;
                        } catch (error) {
                            console.error('Search error:', error);
                            this.searchResults = [];
                        } finally {
                            this.isSearching = false;
                        }
                    },

                    selectSearchResult(product) {
                        this.addProductToOrder(product);
                        this.searchQuery = '';
                        this.searchResults = [];
                        this.showSearchResults = false;
                    },

                    addProductToOrder(product) {
                        // Check if product already exists
                        const existingIndex = this.orderItems.findIndex(item => 
                            item.product_id === product.entity_id
                        );

                        if (existingIndex !== -1) {
                            // Increase quantity
                            this.orderItems[existingIndex].qty += 1;
                        } else {
                            // Add new item
                            this.orderItems.push({
                                product_id: product.entity_id,
                                sku: product.sku,
                                name: product.name,
                                original_name: product.name,
                                pack_size: product.pack_size || '',
                                qty: 1,
                                free_qty: 0,
                                mrp: parseFloat(product.price) || 0,
                                ptr: parseFloat(product.special_price || product.price) || 0,
                                discount: this.calculateDiscount(product.price, product.special_price),
                                stock_qty: product.stock_qty || 0,
                                confidence: 'high',
                                matched_products: [product]
                            });
                        }

                        this.validateQuantities();
                    },

                    // CSV Upload functionality
                    handleFileUpload(event) {
                        const file = event.target.files[0];
                        if (file) {
                            this.uploadCsvFile(file);
                        }
                    },

                    handleFileDrop(event) {
                        event.preventDefault();
                        const file = event.dataTransfer.files[0];
                        if (file) {
                            this.uploadCsvFile(file);
                        }
                    },

                    async uploadCsvFile(file) {
                        // Validate file
                        if (!this.validateFile(file)) {
                            return;
                        }

                        this.isUploading = true;
                        this.errorMessages = [];
                        
                        const formData = new FormData();
                        formData.append('csv_file', file);
                        formData.append('store_id', this.config.storeId);

                        try {
                            const response = await this.makeFileUploadRequest(formData);
                            
                            if (response.success) {
                                this.processUploadedData(response.data);
                                this.showSuccessMessage(this.translations.uploadSuccessful);
                            } else {
                                this.showErrorMessage(response.message || 'Upload failed');
                            }
                        } catch (error) {
                            console.error('Upload error:', error);
                            this.showErrorMessage('Failed to upload file');
                        } finally {
                            this.isUploading = false;
                        }
                    },

                    validateFile(file) {
                        if (!file.name.toLowerCase().endsWith('.csv')) {
                            this.showErrorMessage(this.translations.invalidFile);
                            return false;
                        }

                        if (file.size > this.config.maxFileSize) {
                            this.showErrorMessage(this.translations.fileTooLarge);
                            return false;
                        }

                        return true;
                    },

                    processUploadedData(data) {
                        this.orderItems = [];
                        this.unmatchedProducts = 0;
                        
                        data.forEach(item => {
                            const orderItem = {
                                product_id: item.matched_product?.entity_id || null,
                                sku: item.matched_product?.sku || '',
                                name: item.matched_product?.name || item.original_name,
                                original_name: item.original_name,
                                pack_size: item.matched_product?.pack_size || '',
                                qty: parseInt(item.qty) || 1,
                                free_qty: parseInt(item.free_qty) || 0,
                                mrp: parseFloat(item.matched_product?.price) || 0,
                                ptr: parseFloat(item.matched_product?.special_price || item.matched_product?.price) || 0,
                                discount: this.calculateDiscount(
                                    item.matched_product?.price, 
                                    item.matched_product?.special_price
                                ),
                                stock_qty: item.matched_product?.stock_qty || 0,
                                confidence: this.getConfidenceLevel(item.match_score),
                                matched_products: item.suggestions || [],
                                error: null
                            };

                            if (!item.matched_product) {
                                this.unmatchedProducts++;
                                orderItem.error = 'No match found';
                            }

                            this.orderItems.push(orderItem);
                        });

                        this.validateQuantities();
                    },

                    // Product selection and management
                    updateProductSelection(index, productId) {
                        const item = this.orderItems[index];
                        const selectedProduct = item.matched_products.find(p => p.entity_id == productId);
                        
                        if (selectedProduct) {
                            item.product_id = selectedProduct.entity_id;
                            item.sku = selectedProduct.sku;
                            item.name = selectedProduct.name;
                            item.pack_size = selectedProduct.pack_size || '';
                            item.mrp = parseFloat(selectedProduct.price) || 0;
                            item.ptr = parseFloat(selectedProduct.special_price || selectedProduct.price) || 0;
                            item.discount = this.calculateDiscount(selectedProduct.price, selectedProduct.special_price);
                            item.stock_qty = selectedProduct.stock_qty || 0;
                            item.error = null;
                            
                            // Update unmatched count
                            if (item.confidence === 'none') {
                                this.unmatchedProducts--;
                            }
                            item.confidence = 'high';
                        }

                        this.validateQuantities();
                    },

                    updateQuantity(index, qty) {
                        this.orderItems[index].qty = parseInt(qty) || 0;
                        this.validateQuantities();
                    },

                    removeItem(index) {
                        if (confirm(this.translations.confirmRemove)) {
                            this.orderItems.splice(index, 1);
                            this.validateQuantities();
                        }
                    },

                    // Validation
                    validateQuantities() {
                        this.quantityErrors = 0;
                        
                        this.orderItems.forEach(item => {
                            item.qty_error = null;
                            item.stock_warning = null;
                            
                            if (item.qty <= 0) {
                                item.qty_error = 'Quantity must be greater than 0';
                                this.quantityErrors++;
                            } else if (item.stock_qty > 0 && item.qty > item.stock_qty) {
                                item.qty_error = `Only ${item.stock_qty} units available`;
                                this.quantityErrors++;
                            } else if (item.stock_qty > 0 && item.qty > item.stock_qty * 0.8) {
                                item.stock_warning = `Low stock: ${item.stock_qty} available`;
                            }
                        });
                    },

                    // Cart functionality
                    async addToCart() {
                        if (!this.canAddToCart) {
                            return;
                        }

                        this.isUploading = true;

                        try {
                            const cartItems = this.orderItems
                                .filter(item => item.product_id && item.qty > 0)
                                .map(item => ({
                                    product_id: item.product_id,
                                    qty: item.qty
                                }));

                            const response = await this.makeApiRequest('addToCartUrl', {
                                items: cartItems,
                                store_id: this.config.storeId
                            });

                            if (response.success) {
                                this.showSuccessMessage(this.translations.addedToCart);
                                // Optionally redirect to cart
                                if (response.cart_url) {
                                    window.location.href = response.cart_url;
                                }
                            } else {
                                this.showErrorMessage(response.message || 'Failed to add items to cart');
                            }
                        } catch (error) {
                            console.error('Add to cart error:', error);
                            this.showErrorMessage('Failed to add items to cart');
                        } finally {
                            this.isUploading = false;
                        }
                    },

                    // Utility methods
                    calculateDiscount(originalPrice, finalPrice) {
                        if (!originalPrice || !finalPrice || originalPrice <= finalPrice) {
                            return 0;
                        }
                        return Math.round(((originalPrice - finalPrice) / originalPrice) * 100);
                    },

                    getConfidenceLevel(score) {
                        if (score >= 80) return 'high';
                        if (score >= 60) return 'medium';
                        if (score > 0) return 'low';
                        return 'none';
                    },

                    formatCurrency(amount) {
                        return this.config.currencySymbol + parseFloat(amount).toFixed(2);
                    },

                    formatNumber(number) {
                        return parseInt(number).toLocaleString();
                    },

                    // API methods
                    async makeApiRequest(urlKey, data) {
                        const url = this.config[urlKey];
                        
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: url,
                                type: 'POST',
                                data: data,
                                dataType: 'json',
                                success: resolve,
                                error: reject
                            });
                        });
                    },

                    async makeFileUploadRequest(formData) {
                        const url = this.config.uploadUrl;
                        
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: url,
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                dataType: 'json',
                                success: resolve,
                                error: reject
                            });
                        });
                    },

                    // UI feedback
                    showSuccessMessage(message) {
                        // Implementation depends on Magento's message system
                        console.log('Success:', message);
                    },

                    showErrorMessage(message) {
                        // Implementation depends on Magento's message system
                        console.error('Error:', message);
                        this.errorMessages.push(message);
                    },

                    // Event handlers
                    onSearchInput: self.debounce(function() {
                        this.searchProducts();
                    }, 300),

                    onDragOver(event) {
                        event.preventDefault();
                        event.currentTarget.classList.add('drag-over');
                    },

                    onDragLeave(event) {
                        event.preventDefault();
                        event.currentTarget.classList.remove('drag-over');
                    },

                    downloadTemplate() {
                        window.location.href = this.config.downloadUrl;
                    }
                },

                mounted() {
                    // Initialize drag and drop
                    document.addEventListener('click', (e) => {
                        if (!e.target.closest('.search-section')) {
                            this.showSearchResults = false;
                        }
                    });
                },

                template: `
                <div class="quick-order-container">
                    <div class="quick-order-layout">
                        <!-- Left Sidebar -->
                        <div class="quick-order-sidebar">
                            <!-- Search Section -->
                            <div class="search-section">
                                <h3>{{ translations.searchProducts }}</h3>
                                <div style="position: relative;">
                                    <input 
                                        type="text" 
                                        v-model="searchQuery"
                                        @input="onSearchInput"
                                        @focus="showSearchResults = searchResults.length > 0"
                                        :placeholder="translations.searchByProductName"
                                        class="search-input"
                                    />
                                    
                                    <!-- Search Results Dropdown -->
                                    <div v-if="showSearchResults && searchResults.length > 0" class="search-results">
                                        <div 
                                            v-for="product in searchResults" 
                                            :key="product.entity_id"
                                            @click="selectSearchResult(product)"
                                            class="search-result-item"
                                        >
                                            <div class="search-result-name">{{ product.name }}</div>
                                            <div class="search-result-details">
                                                SKU: {{ product.sku }} | {{ product.manufacturer }}
                                            </div>
                                            <div class="search-result-price">{{ formatCurrency(product.price) }}</div>
                                        </div>
                                    </div>
                                    
                                    <!-- No Results -->
                                    <div v-if="showSearchResults && searchResults.length === 0 && searchQuery.length > 2" class="search-results">
                                        <div class="search-result-item">
                                            <div class="search-result-name">{{ translations.noProductsFound }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Section -->
                            <div class="upload-section">
                                <h3>{{ translations.bulkUpload }}</h3>
                                
                                <a :href="config.downloadUrl" class="download-template-btn">
                                    {{ translations.downloadCsvTemplate }}
                                </a>

                                <div 
                                    class="file-upload-area"
                                    @dragover="onDragOver"
                                    @dragleave="onDragLeave"
                                    @drop="handleFileDrop"
                                    @click="$refs.fileInput.click()"
                                >
                                    <div class="file-upload-icon">📁</div>
                                    <div class="file-upload-text">{{ translations.dropYourCsvFile }}</div>
                                    <div class="file-upload-subtext">{{ translations.orClickToBrowse }}</div>
                                    <button type="button" class="file-upload-btn">
                                        {{ translations.chooseFile }}
                                    </button>
                                    <div class="file-upload-info">
                                        {{ translations.supportsCsvFiles }}
                                    </div>
                                </div>

                                <input 
                                    ref="fileInput"
                                    type="file" 
                                    accept=".csv"
                                    @change="handleFileUpload"
                                    style="display: none;"
                                />
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="quick-order-main">
                            <!-- Error Messages -->
                            <div v-if="unmatchedProducts > 0" class="error-banner">
                                {{ unmatchedProducts }} {{ translations.productsCouldNotBeMatched }}
                            </div>
                            
                            <div v-if="quantityErrors > 0" class="warning-banner">
                                {{ quantityErrors }} {{ translations.itemsHaveQuantityErrors }}
                            </div>

                            <!-- Order Summary -->
                            <div class="order-summary">
                                <div class="order-summary-header">
                                    <h2 class="order-summary-title">
                                        {{ translations.orderSummary }} ({{ totalProducts }} items)
                                    </h2>
                                    <button 
                                        @click="addToCart"
                                        :disabled="!canAddToCart"
                                        class="add-to-cart-btn"
                                    >
                                        {{ translations.addToCart }}
                                    </button>
                                </div>

                                <!-- Products Grid -->
                                <div v-if="orderItems.length > 0" class="products-grid">
                                    <table class="products-table">
                                        <thead>
                                            <tr>
                                                <th>{{ translations.productName }}</th>
                                                <th>{{ translations.packSize }}</th>
                                                <th>{{ translations.qty }}</th>
                                                <th>{{ translations.freeQty }}</th>
                                                <th>{{ translations.mrp }}</th>
                                                <th>{{ translations.ptr }}</th>
                                                <th>{{ translations.discount }}</th>
                                                <th>{{ translations.subTotal }}</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in orderItems" :key="index">
                                                <!-- Product Name -->
                                                <td class="product-name-cell">
                                                    <select 
                                                        v-if="item.matched_products && item.matched_products.length > 0"
                                                        @change="updateProductSelection(index, $event.target.value)"
                                                        :class="['product-dropdown', 'confidence-' + item.confidence]"
                                                        :value="item.product_id"
                                                    >
                                                        <option value="">Select Product...</option>
                                                        <option 
                                                            v-for="product in item.matched_products" 
                                                            :key="product.entity_id"
                                                            :value="product.entity_id"
                                                        >
                                                            {{ product.name }} ({{ product.sku }})
                                                        </option>
                                                    </select>
                                                    <div v-else class="product-dropdown confidence-low">
                                                        {{ item.name }}
                                                    </div>
                                                    <small v-if="item.original_name !== item.name" class="original-text">
                                                        {{ translations.original }}: {{ item.original_name }}
                                                    </small>
                                                </td>

                                                <!-- Pack Size -->
                                                <td class="pack-size-cell">{{ item.pack_size }}</td>

                                                <!-- Quantity -->
                                                <td>
                                                    <input 
                                                        type="number" 
                                                        :value="item.qty"
                                                        @input="updateQuantity(index, $event.target.value)"
                                                        :class="['quantity-input', { error: item.qty_error }]"
                                                        min="1"
                                                    />
                                                    <div v-if="item.qty_error" class="quantity-error">
                                                        {{ item.qty_error }}
                                                    </div>
                                                    <div v-if="item.stock_warning" class="stock-info warning">
                                                        {{ item.stock_warning }}
                                                    </div>
                                                </td>

                                                <!-- Free Qty -->
                                                <td>{{ item.free_qty }}</td>

                                                <!-- MRP -->
                                                <td class="price-cell">{{ formatCurrency(item.mrp) }}</td>

                                                <!-- PTR -->
                                                <td class="price-cell">{{ formatCurrency(item.ptr) }}</td>

                                                <!-- Discount -->
                                                <td>{{ item.discount }}%</td>

                                                <!-- Sub Total -->
                                                <td class="price-cell">{{ formatCurrency(item.qty * item.ptr) }}</td>

                                                <!-- Remove -->
                                                <td>
                                                    <button @click="removeItem(index)" class="remove-btn">×</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Empty State -->
                                <div v-else class="no-results">
                                    <div class="no-results-icon">📋</div>
                                    <p>No products in your order yet.</p>
                                    <p>Search for products above or upload a CSV file to get started.</p>
                                </div>

                                <!-- Summary Footer -->
                                <div v-if="orderItems.length > 0" class="order-summary-footer">
                                    <div class="summary-stats">
                                        <div class="summary-stat">
                                            <div class="summary-stat-value">{{ formatNumber(totalItems) }}</div>
                                            <div class="summary-stat-label">{{ translations.totalItems }}</div>
                                        </div>
                                        <div class="summary-stat">
                                            <div class="summary-stat-value">{{ totalProducts }}</div>
                                            <div class="summary-stat-label">{{ translations.totalProducts }}</div>
                                        </div>
                                    </div>
                                    <div class="estimated-value">
                                        {{ translations.estimatedValue }}: {{ formatCurrency(estimatedValue) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                `
            });

            app.mount(this.element[0]);
        },

        createFallbackApp: function() {
            // Fallback implementation using vanilla JavaScript
            this.element.html(`
                <div class="quick-order-container">
                    <div class="quick-order-layout">
                        <div class="quick-order-sidebar">
                            <div class="search-section">
                                <h3>🔍 Search Products</h3>
                                <input type="text" placeholder="Search by product name, SKU, or manufacturer" class="search-input" />
                            </div>
                            <div class="upload-section">
                                <h3>📁 Bulk Upload</h3>
                                <a href="${this.config.downloadUrl}" class="download-template-btn">
                                    ⬇️ Download CSV Template
                                </a>
                                <div class="file-upload-area">
                                    <div class="file-upload-icon">📁</div>
                                    <div class="file-upload-text">Drop your CSV file here</div>
                                    <div class="file-upload-subtext">or click to browse files</div>
                                    <button type="button" class="file-upload-btn">Choose File</button>
                                    <div class="file-upload-info">Supports CSV files up to 5MB with up to 2000 products</div>
                                </div>
                            </div>
                        </div>
                        <div class="quick-order-main">
                            <div class="order-summary">
                                <div class="order-summary-header">
                                    <h2 class="order-summary-title">Order Summary (0 items)</h2>
                                    <button class="add-to-cart-btn" disabled>Add to Cart</button>
                                </div>
                                <div class="no-results">
                                    <div class="no-results-icon">📋</div>
                                    <p>No products in your order yet.</p>
                                    <p>Search for products above or upload a CSV file to get started.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        },

        // Utility function for debouncing
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    });

    return $.lof.quickOrderApp;
});