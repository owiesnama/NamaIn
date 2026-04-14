import { usePage } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();

    const formatCurrency = (value, currencyCode = null) => {
        const currency = currencyCode ||
            (page.props.invoice?.currency && /^[A-Z]{3}$/.test(page.props.invoice.currency) ? page.props.invoice.currency :
            (window.preferences && window.preferences('currency') && /^[A-Z]{3}$/.test(window.preferences('currency')) ? window.preferences('currency') : 'USD'));

        try {
            return new Intl.NumberFormat(page.props.locale || 'en-US', {
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
