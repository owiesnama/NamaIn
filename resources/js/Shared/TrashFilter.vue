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
            class="px-2 flex-1 shrink-0 py-2 text-xs font-semibold text-gray-600 transition-colors duration-200 dark:hover:bg-gray-800 dark:text-gray-300 hover:bg-gray-100 whitespace-nowrap overflow-hidden text-ellipsis"
            :class="isActive(key) || (isDefault(key) && ! activeTab) ? 'bg-gray-100 dark:bg-gray-700' : '' "
            @click="tabbed(key)"
            :title="tab"
            v-text="tab"
        >
        </button>

    </div>
</template>
