<script setup>
    /**
     * Numeric input with a suffix addon (e.g. "%"). Uses a text input with
     * `inputmode="decimal"` instead of `type="number"` so the browser's native
     * spinner — which renders on the wrong side under RTL — never appears.
     *
     * The whole control is a single rounded, overflow-clipped group, so the
     * addon's outer corners follow the group's logical radius in both
     * directions with no direction-specific corner classes.
     */
    defineProps({
        modelValue: { type: [String, Number], default: "" },
        id: { type: String, default: undefined },
        suffix: { type: String, default: "%" },
        placeholder: { type: String, default: "" },
        disabled: { type: Boolean, default: false },
    });

    const emit = defineEmits(["update:modelValue"]);

    const onInput = (event) => {
        // Keep digits and a single decimal separator only.
        let value = event.target.value.replace(/[^\d.]/g, "");
        const firstDot = value.indexOf(".");
        if (firstDot !== -1) {
            value =
                value.slice(0, firstDot + 1) +
                value.slice(firstDot + 1).replace(/\./g, "");
        }
        event.target.value = value;
        emit("update:modelValue", value);
    };
</script>

<template>
    <div
        class="flex overflow-hidden rounded-lg border border-line bg-surface transition focus-within:border-emerald-300 focus-within:ring focus-within:ring-emerald-200 focus-within:ring-opacity-50 dark:focus-within:border-emerald-600 dark:focus-within:ring-emerald-800"
        :class="{ 'opacity-60': disabled }"
    >
        <input
            :id="id"
            type="text"
            inputmode="decimal"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-primary placeholder-disabled focus:outline-none focus:ring-0"
            @input="onInput"
        />
        <span
            class="flex select-none items-center border-s border-line bg-surface-sunken px-3 text-sm font-medium text-secondary"
        >
            {{ suffix }}
        </span>
    </div>
</template>
