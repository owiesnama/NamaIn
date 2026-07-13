// Reads the tenant's inventory strategy from shared preferences, in one place,
// so the "stored as '1'/'0'" coercion isn't duplicated across components.
export function useInventoryStrategy() {
    const truthy = (value) => [true, 1, "1", "true"].includes(value);
    const strategy = window.preferences?.("inventory_strategy", "purchase_driven");

    return {
        strategy,
        // Sales may drive the balance negative (free-form + overselling on).
        oversellingEnabled: truthy(window.preferences?.("allow_overselling", false)),
        // Stock may be raised manually (adjustments / product-edit) — free-form only.
        manualStockAllowed: strategy === "free_form",
    };
}
