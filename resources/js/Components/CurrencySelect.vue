<script setup>
    import { computed } from "vue";
    import { usePage } from "@inertiajs/vue3";

    /**
     * Currency picker backed by a fixed ISO 4217 list. Option labels are
     * localised at runtime via Intl.DisplayNames (so Arabic gets Arabic
     * currency names) — no hardcoded, per-currency translation keys.
     */
    const props = defineProps({
        modelValue: { type: String, default: "SDG" },
        id: { type: String, default: undefined },
        disabled: { type: Boolean, default: false },
    });

    defineEmits(["update:modelValue"]);

    // Regionally relevant codes, SDG (app default) first.
    const CURRENCY_CODES = [
        "SDG", "USD", "EUR", "GBP", "SAR", "AED",
        "EGP", "QAR", "KWD", "TRY", "ETB", "SSP", "CNY",
    ];

    const page = usePage();

    const localizedName = (code) => {
        try {
            const names = new Intl.DisplayNames([page.props.locale || "en"], {
                type: "currency",
            });
            return names.of(code) || code;
        } catch (e) {
            return code;
        }
    };

    // Ensure a previously-stored value that isn't in the canonical list still
    // appears as a selectable option (so migration/legacy data isn't lost).
    const codes = computed(() => {
        const current = props.modelValue;
        if (current && !CURRENCY_CODES.includes(current)) {
            return [current, ...CURRENCY_CODES];
        }
        return CURRENCY_CODES;
    });

    const options = computed(() =>
        codes.value.map((code) => ({ code, label: `${code} — ${localizedName(code)}` }))
    );
</script>

<template>
    <select
        :id="id"
        :value="modelValue"
        :disabled="disabled"
        class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-start text-sm text-primary focus:border-emerald-300 focus:outline-none focus:ring focus:ring-emerald-200 focus:ring-opacity-50 disabled:opacity-60 dark:focus:border-emerald-600 dark:focus:ring-emerald-800 sm:max-w-xs"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <option
            v-for="option in options"
            :key="option.code"
            :value="option.code"
        >
            {{ option.label }}
        </option>
    </select>
</template>
