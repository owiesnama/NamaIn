<script setup>
    import { usePage } from "@inertiajs/vue3";
    import { ref, watch,computed } from "vue";
    const show = ref(true);
    const props = computed(() => usePage().props)
    watch(props, () => show.value.true, {
        deep: true,
    });
</script>
<template>
    <div
        class="fixed inset-0 z-50 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end"
    >
        <Transition
            appear
            enter-active-class="transition duration-300 ease-out transform"
            enter-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition duration-100 ease-in"
            leave-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="$page.props.flash && show"
                :key="new Date().getTime()"
                class="w-full max-w-sm overflow-hidden bg-white rounded-lg shadow-lg pointer-events-auto ring-1 ring-black ring-opacity-5"
                @mouseleave="show = false"
            >
                <div class="px-5 py-3">
                    <div class="flex items-start">
                        <div class="flex-1 w-0">
                            <template
                                v-if="
                                    $page.props.flash?.title &&
                                    $page.props.flash?.message
                                "
                            >
                                <p class="font-bold text-emerald-500">
                                    {{ $page.props.flash?.title }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $page.props.flash?.message }}
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-sm font-semibold tracking-wide text-gray-800 capitalize ">
                                    {{
                                        $page.props.flash
                                            ?.message ||
                                        $page.props.flash?.title
                                    }}
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
