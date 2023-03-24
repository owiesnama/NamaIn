<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import Panel from "@/Shared/Panel.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import { Link } from "@inertiajs/vue3";
    defineProps({
        products: Object,
    });

    let form = useForm({
        file: null,
    });
    let search = useQueryString("search");
    let submit = (files) => {
        form.file = files[0];
        form.post(route("products.import"));
    };
    watch(
        search,
        debounce(function (value) {
            router.get("/products", { search: value }, { preserveState: true });
        }, 300)
    );
</script>

<template>
    <AppLayout title="products">
        <div class="container mx-auto">
            <div class="flex justify-between mt-4 items-center mb-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search ..."
                    class="mb-4 rounded-lg p-2 border border-gray-200 w-64"
                />
                <div class="space-x-2">
                    <FileUploadButton
                        download
                        @input="submit"
                        >Import from spreadsheed</FileUploadButton
                    >
                    <Link
                        :href="route('products.create')"
                        as="button"
                        >Create a product</Link
                    >
                </div>
            </div>

            <Panel>
                <div class="block w-full overflow-x-auto">
                    <table
                        class="items-center bg-transparent w-full border-separate"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="px-6 rounded-tl-md text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-1 border-r-0 whitespace-nowrap font-semibold text-left"
                                >
                                    Name
                                </th>
                                <th
                                    class="px-6 rounded-tr-md text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-0 border-r-1 whitespace-nowrap font-semibold text-left"
                                >
                                    Cost
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="products.data">
                                <tr
                                    v-for="product in products.data"
                                    :key="product.id"
                                >
                                    <th
                                        class="px-6 text-xs border-gray-100 py-3 text-left border border-l-1 border-r-0"
                                        v-text="product.name"
                                    ></th>
                                    <td
                                        class="px-6 text-xs border-gray-100 py-3 text-left border border-l-0 border-r-1"
                                        v-text="product.phone"
                                    ></td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td
                                        colspan="2"
                                        class="text-center text-sm leading-7 p-4 text-gray-600"
                                    >
                                        No products available
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="products.links"></Pagination>
            </Panel>
        </div>
    </AppLayout>
</template>
