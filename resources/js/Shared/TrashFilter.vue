<script setup>
    import { computed, ref } from "vue";

    const props = defineProps({
        "default": {
            type: String,
            default: "all"
        }
    });
    const emits = defineEmits(["tabbed"]);

    const tabs = {
        all: __("All"),
        withTrash: __("With Trashed"),
        trash: __("Trashed")
    };

    const activeTab = ref();

    const isDefault = (key) => key === props.default;

    const isActive = computed(() => (tab) => tab === activeTab.value);

    const tabbed = (key) => {
        activeTab.value = key;
        emits("tabbed", key);
    };
</script>
<template>
    <div
        class="flex overflow-hidden bg-white border divide-x rounded-lg md:w-auto sm:w-1/2 dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700"
    >
        <button
            v-for="(tab,key) in tabs"
            :key="'tab'+key+ (new Date).valueOf()"
            class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-300 hover:bg-gray-100"
            :class="isActive(key) || (isDefault(key) && ! activeTab) ? 'bg-gray-100' : '' "
            @click="tabbed(key)"
            v-text="tab"
        >
        </button>

    </div>
</template>
