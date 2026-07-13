<script setup>
    /**
     * A switch paired with its label on a single, width-capped row — the
     * control sits directly next to the label instead of being flung to the
     * far edge by a full-width justify-between. Optional description line.
     *
     * The knob slides with a logical translate (`start-*` + `rtl:` mirror) so
     * it moves toward the inline-end in both LTR and RTL.
     */
    const props = defineProps({
        modelValue: { type: Boolean, default: false },
        label: { type: String, default: "" },
        description: { type: String, default: "" },
        disabled: { type: Boolean, default: false },
    });

    const emit = defineEmits(["update:modelValue"]);

    const toggle = () => {
        if (!props.disabled) {
            emit("update:modelValue", !props.modelValue);
        }
    };
</script>

<template>
    <label
        class="inline-flex max-w-md items-start gap-x-3"
        :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
    >
        <!-- Switch -->
        <span
            class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full transition-colors duration-200"
            :class="modelValue ? 'bg-emerald-500' : 'bg-line-strong'"
        >
            <span
                class="absolute top-0.5 start-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform duration-200"
                :class="modelValue ? 'translate-x-4 rtl:-translate-x-4' : ''"
            />
        </span>

        <!-- Native input drives state + accessibility; visually hidden. -->
        <input
            type="checkbox"
            class="sr-only"
            :checked="modelValue"
            :disabled="disabled"
            @change="toggle"
        />

        <span
            v-if="label || description"
            class="min-w-0"
        >
            <span
                v-if="label"
                class="block text-sm font-medium text-primary"
            >
                {{ label }}
            </span>
            <span
                v-if="description"
                class="mt-0.5 block text-sm text-secondary"
            >
                {{ description }}
            </span>
        </span>
    </label>
</template>
