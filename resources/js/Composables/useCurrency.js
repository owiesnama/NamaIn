import {
    formatMoney,
    formatMoneyPlain,
    formatAmount,
    formatAmountPlain,
    numeralSystem,
} from "@/Support/money";

/**
 * Money formatting bound to the tenant's numeral system. Thin wrapper over
 * @/Support/money — see there for the isolation rules. formatCurrency/formatAmount
 * return isolation-safe strings for HTML; the *Plain variants are for <canvas> and
 * exports.
 */
export function needsLtrIsolation() {
    return numeralSystem() === "latin";
}

export function useCurrency() {
    return {
        formatCurrency: (value, currencyCode = null) => formatMoney(value, currencyCode),
        formatAmount: (value) => formatAmount(value),
        formatCurrencyPlain: (value, currencyCode = null) => formatMoneyPlain(value, currencyCode),
        formatAmountPlain: (value) => formatAmountPlain(value),
        numeralSystem,
        needsLtrIsolation,
    };
}
