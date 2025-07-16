define(['jquery'], function ($) {
    'use strict';

    function applyPatch() {
        const app = document.querySelector('#fast-order-app');
        if (app && app.__vue__) {
            const mixin = {
                methods: {
                    formatCurrency(value) {
                        if (this.currencyCode === 'INR') {
                            return new Intl.NumberFormat('en-IN', {
                                style: 'currency',
                                currency: 'INR',
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(Number(value));
                        }
                        if (this.currencyCode) {
                            const locale = this.$options?.feeLocales?.[this.currencyCode]?.[0] || 'en-US';
                            return new Intl.NumberFormat(locale, {
                                style: 'currency',
                                currency: this.currencyCode,
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(value);
                        }
                        return value;
                    }
                }
            };

            // Apply mixin globally to the Vue constructor used by the app
            app.__vue__.constructor.mixin(mixin);
        } else {
            // Retry until Vue app is mounted
            setTimeout(applyPatch, 300);
        }
    }

    applyPatch();

    return {};
});