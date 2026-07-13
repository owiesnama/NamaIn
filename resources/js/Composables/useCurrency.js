import { usePage } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();

    const formatCurrency = (value, currencyCode = null) => {
        const currency = currencyCode ||
            (page.props.invoice?.currency && /^[A-Z]{3}$/.test(page.props.invoice.currency) ? page.props.invoice.currency :
            (window.preferences && window.preferences('currency') && /^[A-Z]{3}$/.test(window.preferences('currency')) ? window.preferences('currency') : 'SDG'));

        try {
            // Always format with Latin (Western) digits regardless of app locale;
            // Arabic-Indic digits are avoided app-wide for numeric/financial data.
            // Render the result inside <Ltr> / .ltr-isolate so the RTL bidi
            // algorithm doesn't reorder the currency symbol or sign.
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency,
            }).format(value);
        } catch (e) {
            return `${value} ${currency}`;
        }
    };

    return {
        formatCurrency
    };
}
