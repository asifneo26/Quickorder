define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    function bindEditableClicks() {
        $('.grid__3LFPm__fast_order[data-last-child]').each(function () {
            const $row = $(this);
            const $productCell = $row.find('.wrapProductName__1pGGd__fast_order');
            const $a = $productCell.find('a');
            const href = $a.attr('href') || '';
            const $qtyInput = $row.find('input[type="number"]');

            $productCell.removeClass('product-high product-low')
                .addClass(href.trim() !== '' ? 'product-high' : 'product-low');
            $qtyInput.prop('readonly', href.trim() === '')
                .removeClass('input-low input-high')
                .addClass(href.trim() !== '' ? 'input-high' : 'input-low');

            $qtyInput.off('input.fastSubtotal').on('input.fastSubtotal', function () {
                updateRowSubtotal($row);
            });

            if ($productCell.find('.dropdown-toggle-button').length === 0) {
                const $svgBtn = $(
                    `<span class="dropdown-toggle-button" style="margin-left: 8px; cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </span>`
                );

                $productCell.append($svgBtn);

                $svgBtn.on('click', function () {
                    const currentName = $a.text().trim();
                    const $input = $('<input type="text" class="editable-name" autocomplete="off" />')
                        .val(currentName)
                        .css({
                            width: '100%',
                            padding: '4px',
                            border: '1px solid red',
                            background: '#fff0f0',
                            color: '#000',
                            marginTop: '4px'
                        });

                    const $dropdown = $('<ul class="product-suggestions"></ul>').css({
                        position: 'absolute',
                        top: '100%',
                        left: '0',
                        right: '0',
                        zIndex: 9999,
                        background: '#fff',
                        border: '1px solid #ccc',
                        padding: 0,
                        marginTop: '2px',
                        maxHeight: '180px',
                        overflowY: 'auto',
                        listStyle: 'none',
                        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
                        fontSize: '14px'
                    });

                    $productCell.css('position', 'relative');
                    $productCell.find('input.editable-name, ul.product-suggestions').remove();
                    $productCell.append($input).append($dropdown);
                    $input.focus();

                    $input.on('keyup', function () {
                        const val = $(this).val().trim();
                        if (val.length > 1) {
                            $.ajax({
                                url: '/rest/default/V1/lof-fastorder/fastsearchproduct?value=' + encodeURIComponent(val) + '&storeCode=default',
                                method: 'GET',
                                dataType: 'json',
                                success: function (res) {
                                    $dropdown.empty();
                                    if (Array.isArray(res) && res.length > 0) {
                                        res.slice(0, 5).forEach(product => {
                                            const $li = $('<li></li>')
                                                .html(`
                                                    <strong>${product.name}</strong><br/>
                                                    <small>
                                                        Pack Size: ${product.pack_size || '-'} &nbsp;
                                                        Scheme: ${product.scheme || '-'}<br/>
                                                        PTR: ₹${product.ptr || '0.00'} &nbsp;
                                                        MRP: ₹${product.mrp || '0.00'}<br/>
                                                        Avl Qty: ${product.qty || '0'} &nbsp;
                                                        Discount: ${product.discount || '0'}%
                                                    </small>
                                                `)
                                                .css({
                                                    padding: '6px 10px',
                                                    cursor: 'pointer',
                                                    borderBottom: '1px solid #eee'
                                                })
                                                .hover(
                                                    function () { $(this).css('background', '#f5f5f5'); },
                                                    function () { $(this).css('background', 'transparent'); }
                                                )
                                                .on('click', function () {
                                                    const csvContent = `Name,Qty,SKU,Price,Discount\n${[
                                                        product.name || '',
                                                        1,
                                                        product.sku || '',
                                                        product.ptr || 0,
                                                        product.discount || 0
                                                    ].join(',')}`;

                                                    const blob = new Blob([csvContent], { type: 'text/csv' });
                                                    const formData = new FormData();
                                                    formData.append('csv_file', blob, 'update.csv');
                                                    formData.append('storeCode', 'default');
                                                    formData.append('is_single_update', 'true');

                                                    $.ajax({
                                                        url: urlBuilder.build("rest/default/V1/lof-fastorder/uploadcsv"),
                                                        method: "POST",
                                                        data: formData,
                                                        processData: false,
                                                        contentType: false,
                                                        dataType: "json",

                                                        success: function (res) {
                                                            const updated = res.products?.product?.product?.[0];
                                                            const innerNotFound = res.products?.product?.not_found || [];

                                                            if (updated) {
                                                                // Remove the old row if it exists
                                                                if ($row && $row.parent().length) {
                                                                    $row.remove();
                                                                }

                                                                // Make sure you're using the correct store module name
                                                                // This might be different in your implementation
                                                                require(['vue'], function (Vue) {
                                                                    const app = document.querySelector('#fast-order-app');
                                                                    if (app && app.__vue__) {
                                                                        // Check your actual Vuex store module name here
                                                                        const storeName = 'fastOrder'; // or whatever your actual module name is
                                                                        
                                                                        // Update the product in the store
                                                                        app.__vue__.$store.commit(`${storeName}/updateProduct`, updated);
                                                                        
                                                                        // Refresh the product list
                                                                        app.__vue__.$store.dispatch(`${storeName}/refreshProducts`);
                                                                        
                                                                        // For debugging - log the current state
                                                                        console.log('Current store state:', app.__vue__.$store.state[storeName]);
                                                                    }
                                                                });

                                                                // Consider using a more robust storage solution like IndexedDB for larger datasets
                                                                // or server-side storage with customer session data

                                                                showMagentoPopup("success", `Products updated: 1, Not Found: ${innerNotFound.length}`);
                                                            }

                                                            $input.remove();
                                                            $dropdown.remove();
                                                        },
                                                        error: function (xhr) {
                                                            console.error("Upload failed:", xhr);
                                                            showMagentoPopup("error", "Product update via dropdown failed");
                                                        }
                                                    });
                                                });

                                            $dropdown.append($li);
                                        });
                                    } else {
                                        $dropdown.html('<li style="padding: 6px 10px; color: #999;">No results found</li>');
                                    }
                                }
                            });
                        } else {
                            $dropdown.empty();
                        }
                    });

                    $input.on('blur', function () {
                        setTimeout(() => {
                            if (!$dropdown.is(':hover')) {
                                $dropdown.remove();
                            }
                        }, 200);
                    });
                });
            }
        });
    }

    function updateRowSubtotal($row) {
        const qty = parseFloat($row.find('input[type="number"]').val()) || 0;
        const ptr = parseFloat($row.find('.wrapPtr__As8_S__fast_order').text()) || 0;
        const discount = parseFloat($row.find('.wrapDiscount__As8_S__fast_order').text()) || 0;

        const price = ptr * qty;
        const discountAmt = price * (discount / 100);
        const total = price - discountAmt;

        const formatted = `Rs ${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        $row.find('.wrapSubTotal__3x0vV__fast_order').html(formatted);
    }

    function waitForProductGrid(maxWait = 5000, interval = 200) {
        const startTime = Date.now();
        const check = () => {
            if ($('.grid__3LFPm__fast_order[data-last-child]').length > 0) {
                bindEditableClicks();
                
                // Load saved products from localStorage if available
                require(['vue'], function (Vue) {
                    const app = document.querySelector('#fast-order-app');
                    if (app && app.__vue__) {
                        const savedProducts = localStorage.getItem('fastorder_products');
                        if (savedProducts) {
                            try {
                                const products = JSON.parse(savedProducts);
                                app.__vue__.$store.commit('fastOrder/setProducts', products);
                                app.__vue__.$store.dispatch('fastOrder/refreshProducts');
                            } catch (e) {
                                console.error('Error loading saved products:', e);
                            }
                        }
                    }
                });

                const container = document.querySelector('.grid__3LFPm__fast_order')?.parentNode;
                if (container) {
                    const observer = new MutationObserver(function (mutations) {
                        mutations.forEach(mutation => {
                            if (mutation.addedNodes.length) {
                                bindEditableClicks();
                            }
                        });
                    });
                    observer.observe(container, { childList: true, subtree: true });
                }

            } else if (Date.now() - startTime < maxWait) {
                setTimeout(check, interval);
            } else {
                console.warn('[FastOrder] Timeout waiting for grid.');
            }
        };
        check();
    }

    $(document).ready(() => {
        waitForProductGrid();
    });
});