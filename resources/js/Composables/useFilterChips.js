import { computed } from "vue";

/**
 * Derive removable filter chips from a reactive filters object.
 *
 * Each descriptor maps a filter key to a human-readable label and, optionally,
 * how to display its value (a formatter or an option list to resolve the label
 * from). A chip is emitted only when the filter holds a meaningful value — an
 * empty string, null/undefined, or a value equal to the descriptor default is
 * treated as inactive.
 *
 * @param {import('vue').Ref<Object>} filters
 * @param {Array<{
 *   key: string,
 *   label: string,
 *   default?: *,
 *   format?: (value: *) => string,
 *   options?: Array<Object>,
 *   optionValue?: string,
 *   optionLabel?: string,
 * }>} descriptors
 * @returns {{ chips: import('vue').ComputedRef<Array>, removeChip: (key: string) => void }}
 */
export const useFilterChips = (filters, descriptors) => {
    const isInactive = (value, defaultValue) => {
        if (value === null || value === undefined || value === "") {
            return true;
        }

        if (Array.isArray(value)) {
            return value.length === 0;
        }

        return defaultValue !== undefined && String(value) === String(defaultValue);
    };

    const displayValue = (descriptor, value) => {
        if (descriptor.format) {
            return descriptor.format(value);
        }

        if (descriptor.options) {
            const valueKey = descriptor.optionValue ?? "value";
            const labelKey = descriptor.optionLabel ?? "label";
            const match = descriptor.options.find(
                (option) => String(option[valueKey]) === String(value)
            );

            if (match) {
                return match[labelKey];
            }
        }

        return value;
    };

    const chips = computed(() =>
        descriptors
            .filter((descriptor) => !isInactive(filters.value[descriptor.key], descriptor.default))
            .map((descriptor) => ({
                key: descriptor.key,
                label: descriptor.label,
                value: displayValue(descriptor, filters.value[descriptor.key]),
            }))
    );

    const removeChip = (key) => {
        const descriptor = descriptors.find((descriptor) => descriptor.key === key);

        filters.value = {
            ...filters.value,
            [key]: descriptor?.default ?? null,
        };
    };

    return { chips, removeChip };
};
