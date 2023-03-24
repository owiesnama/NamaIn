<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { Link } from "@inertiajs/vue3";
    import { router } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import Cheque from "@/Shared/Cheque.vue";
    import SelectBox from "@/Shared/SelectBox.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch } from "vue";
    import { debounce } from "lodash";

    defineProps({
        cheques: Object,
    });
    let search = useQueryString("search");
    let chequeType = useQueryString("chequeType");
    watch(
        [search, chequeType],
        debounce(function ([newSearch, newChequeType]) {
            router.get(
                "/cheques",
                { chequeType: newChequeType, search: newSearch },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Products">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4">
                    <form class="flex justify-between items-center">
                        <div class="space-x-2">
                            <TextInput
                                v-model="search"
                                type="text"
                                placeholder="Search here ..."
                            ></TextInput>
                            <SelectBox
                                v-model="chequeType"
                                class="w-52"
                            >
                                <option value="">All</option>
                                <option value="1">Credit</option>
                                <option value="0">Debit</option>
                            </SelectBox>
                        </div>
                        <div>
                            <Link
                                href="/cheques/create"
                                as="Button"
                                class="inline-flex items-center px-4 py-3 bg-gray-100 border border-gray-200 rounded font-semibold text-sm text-gray-900 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >New Cheque</Link
                            >
                        </div>
                    </form>
                </div>

                <Cheque
                    v-for="cheque in cheques"
                    :key="cheque.id"
                    :cheque="cheque"
                ></Cheque>
            </div>
        </div>
    </AppLayout>
</template>
