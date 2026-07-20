import { usePage } from "@inertiajs/vue3";

// Single source of truth for money formatting. Read the numeral system from the
// tenant preference, format with Intl, and make Latin output safe to drop into
// the RTL layout as a bare text node.
//
// Exposed on window (app.js) as window.formatMoney / window.formatMoneyPlain /
// window.formatAmount / window.formatAmountPlain, matching the existing
// window.preferences / window.lang / window.__ globals, so page-level formatters
// delegate here without an import. useCurrency() and <Money> wrap these too.

// LRI opens a left-to-right isolate, PDI closes it — the string-level equivalent
// of dir="ltr" + unicode-bidi:isolate, so a Latin money run renders correctly
// inside RTL text with no wrapper element. Arabic-Indic Intl output already
// carries its own bidi controls (U+061C, U+200F) and must NOT be isolated.
const LRI = "⁦";
const PDI = "⁩";

export function numeralSystem() {
    return usePage().props.preferences?.numerals === "arabic" ? "arabic" : "latin";
}

function intlLocale() {
    return numeralSystem() === "arabic" ? "ar-SA" : "en-US";
}

function resolveCurrency(currencyCode = null) {
    const page = usePage().props;
    if (currencyCode && /^[A-Z]{3}$/.test(currencyCode)) {
        return currencyCode;
    }
    if (page.invoice?.currency && /^[A-Z]{3}$/.test(page.invoice.currency)) {
        return page.invoice.currency;
    }
    const pref = page.preferences?.currency;
    return pref && /^[A-Z]{3}$/.test(pref) ? pref : "SDG";
}

function isolate(text) {
    return numeralSystem() === "latin" ? `${LRI}${text}${PDI}` : text;
}

// Plain (un-isolated) — for <canvas> (charts, where CSS bidi does not apply),
// exports, and string comparisons.
export function formatMoneyPlain(value, currencyCode = null) {
    const currency = resolveCurrency(currencyCode);
    try {
        return new Intl.NumberFormat(intlLocale(), { style: "currency", currency }).format(value || 0);
    } catch (e) {
        return `${value || 0} ${currency}`;
    }
}

export function formatAmountPlain(value) {
    return value
        ? new Intl.NumberFormat(intlLocale(), { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(value)
        : "—";
}

// Isolation-safe — the default for HTML text interpolation.
export function formatMoney(value, currencyCode = null) {
    return isolate(formatMoneyPlain(value, currencyCode));
}

export function formatAmount(value) {
    return value ? isolate(formatAmountPlain(value)) : "—";
}
