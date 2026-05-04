<script setup>
    import { computed, ref, watch } from "vue";
    import Modal from "@/Components/Modal.vue";

    const props = defineProps({
        show: { type: Boolean, default: false },
        templates: { type: Array, default: () => [] },
        sampleUrl: { type: String, default: null },
        processing: { type: Boolean, default: false },
    });

    const emit = defineEmits(["close", "submit"]);

    const defaultTemplate = computed(() => {
        return props.templates.find((template) => !template.disabled)?.value || "default";
    });

    const selectedTemplate = ref(defaultTemplate.value);
    const selectedFile = ref(null);
    const dragOver = ref(false);

    const selectedTemplateIsAvailable = computed(() => {
        if (props.templates.length === 0) {
            return selectedTemplate.value === "default";
        }

        return props.templates.some((template) => {
            return template.value === selectedTemplate.value && !template.disabled;
        });
    });

    const onFileSelected = (event) => {
        selectedFile.value = event.target.files[0] || null;
    };

    const onDrop = (event) => {
        dragOver.value = false;
        const file = event.dataTransfer.files[0];
        if (file) {
            selectedFile.value = file;
        }
    };

    const submit = () => {
        if (!selectedFile.value || !selectedTemplateIsAvailable.value) return;
        emit("submit", { file: selectedFile.value, template: selectedTemplate.value });
    };

    const reset = () => {
        selectedFile.value = null;
        selectedTemplate.value = defaultTemplate.value;
    };

    const close = () => {
        reset();
        emit("close");
    };

    watch(() => props.show, () => reset());

    watch(defaultTemplate, (template) => {
        if (!selectedTemplateIsAvailable.value) {
            selectedTemplate.value = template;
        }
    });
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("Import Data") }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Upload a CSV or Excel file to import records.") }}</p>

            <div class="mt-5 space-y-4">
                <!-- Template selector (only if multiple) -->
                <div v-if="templates.length > 1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 rtl:text-right">{{ __("Template") }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tpl in templates"
                            :key="tpl.value"
                            type="button"
                            class="relative px-4 py-2 text-sm rounded-lg border transition-colors duration-200"
                            :class="tpl.disabled
                                ? 'bg-gray-50 dark:bg-gray-800/50 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700 cursor-not-allowed opacity-60'
                                : selectedTemplate === tpl.value
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800 font-medium'
                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            :disabled="tpl.disabled"
                            @click="!tpl.disabled && (selectedTemplate = tpl.value)"
                        >
                            {{ tpl.label }}
                            <span v-if="tpl.badge" class="ms-1.5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ tpl.badge }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- File drop zone -->
                <div
                    class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 transition-colors duration-200 cursor-pointer"
                    :class="dragOver
                        ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-900/10'
                        : selectedFile
                            ? 'border-emerald-300 bg-emerald-50/50 dark:bg-emerald-900/5 dark:border-emerald-800'
                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                    @click="$refs.fileInput.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.xlsx,.xls"
                        class="sr-only"
                        @change="onFileSelected"
                    />

                    <template v-if="selectedFile">
                        <svg class="h-8 w-8 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ selectedFile.name }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __("Click to change file") }}</p>
                    </template>
                    <template v-else>
                        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __("Drop a file here or click to browse") }}</p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">CSV, XLSX, XLS</p>
                    </template>
                </div>

                <!-- Sample download -->
                <div v-if="sampleUrl" class="flex items-center justify-center">
                    <a
                        :href="sampleUrl"
                        class="inline-flex items-center gap-x-1.5 text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ __("Download sample file") }}
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 flex justify-end gap-x-3">
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="close"
                >
                    {{ __("Cancel") }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    :disabled="!selectedFile || !selectedTemplateIsAvailable || processing"
                    @click="submit"
                >
                    <svg v-if="processing" class="animate-spin ltr:-ml-1 rtl:-mr-1 ltr:mr-2 rtl:ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __("Import") }}
                </button>
            </div>
        </div>
    </Modal>
</template>
