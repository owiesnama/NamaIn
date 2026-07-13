<script setup>
    import { computed, ref } from "vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";

    /**
     * Logo picker with a real image preview and a placeholder-icon fallback
     * (instead of a broken <img> alt string). Emits the chosen File via
     * v-model; the section persists it through the shared form.
     */
    const props = defineProps({
        // The currently stored logo URL (shown until a new file is picked).
        currentUrl: { type: String, default: "" },
    });

    const emit = defineEmits(["update:modelValue"]);

    const fileInput = ref(null);
    const newPreview = ref(null);
    const currentFailed = ref(false);

    // New pick wins; otherwise the stored logo (unless it failed to load).
    const previewSrc = computed(() => {
        if (newPreview.value) {
            return newPreview.value;
        }
        if (props.currentUrl && !currentFailed.value) {
            return props.currentUrl;
        }
        return null;
    });

    const pick = () => {
        fileInput.value.click();
    };

    const onChange = () => {
        const file = fileInput.value.files[0];
        if (!file) {
            return;
        }
        emit("update:modelValue", file);

        const reader = new FileReader();
        reader.onload = (e) => (newPreview.value = e.target.result);
        reader.readAsDataURL(file);
    };
</script>

<template>
    <div class="flex items-center gap-x-4">
        <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onChange"
        />

        <!-- Preview / placeholder -->
        <div
            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-surface-sunken"
        >
            <img
                v-if="previewSrc"
                :src="previewSrc"
                alt=""
                class="h-full w-full object-contain"
                @error="currentFailed = true"
            />
            <svg
                v-else
                class="h-7 w-7 text-disabled"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                />
            </svg>
        </div>

        <div>
            <SecondaryButton
                type="button"
                @click.prevent="pick"
            >
                {{ __("Select A New Logo") }}
            </SecondaryButton>
            <p class="mt-1.5 text-xs text-tertiary">
                {{ __("PNG, JPG or SVG · up to 2MB.") }}
            </p>
        </div>
    </div>
</template>
