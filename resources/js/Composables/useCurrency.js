import { usePage } from '@inertiajs/vue3';

/**
 * The tenant's numeral system: 'arabic' (Arabic-Indic) or 'latin'.
 *
 * HandleInertiaRequests always shares a concrete value, defaulting to the
 * tenant's language, so there is no default to re-derive here.
 */
export function numeralSystem() {
    return usePage().props.preferences?.numerals === 'arabic' ? 'arabic' : 'latin';
}

/**
 * Whether formatted output needs LTR isolation to survive the RTL layout.
 *
 * The rule inverts per system, which is why <Money> owns it rather than leaving
 * every call site to remember:
 *
 *   Latin      "-SDG 377,297.50"  carries no bidi controls. The leading minus is
 *                                 bidi-neutral and reorders to the visual end
 *                                 ("SDG 377,297.50-"), so it MUST be isolated.
 *   Arabic     "؜-‏٣٧٧٬٢٩٧٫٥٠ ج.س."  already ships U+061C (Arabic Letter Mark) and
 *                                 U+200F (RLM), placed by ICU precisely so it
 *                                 renders correctly in an RTL context. Isolating
 *                                 it fights those marks and shifts the currency
 *                                 punctuation, so it must NOT be isolated.
 */
export function needsLtrIsolation(system = null) {
    return (system ?? numeralSystem()) === 'latin';
}

export function useCurrency() {
    const page = usePage();

    const intlLocale = () => (numeralSystem() === 'arabic' ? 'ar-SA' : 'en-US');

    const resolveCurrency = (currencyCode = null) =>
        currencyCode ||
        (page.props.invoice?.currency && /^[A-Z]{3}$/.test(page.props.invoice.currency)
            ? page.props.invoice.currency
            : window.preferences &&
                window.preferences('currency') &&
                /^[A-Z]{3}$/.test(window.preferences('currency'))
              ? window.preferences('currency')
              : 'SDG');

    const formatCurrency = (value, currencyCode = null) => {
        const currency = resolveCurrency(currencyCode);

        try {
            return new Intl.NumberFormat(intlLocale(), {
                style: 'currency',
                currency: currency,
            }).format(value);
        } catch (e) {
            return `${value} ${currency}`;
        }
    };

    // A bare amount (no currency symbol) for tables that put the currency in the
    // column header. Zero/null renders as an em dash so a column of zeros isn't
    // noise. Render through <Money bare> so isolation follows the numeral system.
    const formatAmount = (value) =>
        value
            ? new Intl.NumberFormat(intlLocale(), {
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 2,
              }).format(value)
            : '—';

    return {
        formatCurrency,
        formatAmount,
        numeralSystem,
        needsLtrIsolation: () => needsLtrIsolation(),
    };
}
