<script setup>
    import { computed, ref, watch } from "vue";

    const props = defineProps({
        modelValue: {
            type: String,
            default: "all"
        }
    });
    const emits = defineEmits(["update:modelValue", "tabbed"]);

    const tabs = {
        all: __("All"),
        withTrash: __("With Trashed"),
        trash: __("Trashed")
    };

    const activeTab = ref(props.modelValue);

    watch(() => props.modelValue, (val) => {
        activeTab.value = val;
    });

    const isDefault = (key) => key === "all";

    const isActive = computed(() => (tab) => tab === activeTab.value);

    const tabbed = (key) => {
        activeTab.value = key;
        emits("update:modelValue", key);
        emits("tabbed", key);
    };
</script>
<template>
    <div
        class="flex divide-x md:w-auto sm:w-1/2 rtl:flex-row-reverse dark:divide-gray-700 h-full"
    >
        <button
            v-for="(tab,key) in tabs"
            :key="'tab'+key+ (new Date).valueOf()"
            class="px-2 flex-1 shrink-0 py-2 text-xs font-semibold transition-colors duration-200 whitespace-nowrap overflow-hidden text-ellipsis"
            :class="isActive(key) || (isDefault(key) && ! activeTab)
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
            @click="tabbed(key)"
            :title="tab"
            v-text="tab"
        >
        </button>

    </div>
</template>
