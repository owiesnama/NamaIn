<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { ref, watch } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import Panel from "@/Shared/Panel.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
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
                <FileUploadButton
                    @input="submit"
                    download
                    >Import from spreadsheed</FileUploadButton
                >
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
                                    Phone
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
            <div class="container mx-auto my-8">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4">
                        <div class="flex justify-between">
                            <div
                                class="text-gray-500 uppercase tracking-wide font-bold"
                            >
                                Invoice No.
                            </div>
                            <div class="text-gray-800">#12345</div>
                        </div>
                        <div class="flex justify-between">
                            <div
                                class="text-gray-500 uppercase tracking-wide font-bold"
                            >
                                Date
                            </div>
                            <div class="text-gray-800">01/01/2022</div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between">
                                <div
                                    class="text-gray-500 uppercase tracking-wide font-bold"
                                >
                                    Bill To
                                </div>
                                <div class="text-gray-800">John Doe</div>
                            </div>
                            <div class="text-gray-500 mt-2">
                                123 Main Street
                            </div>
                            <div class="text-gray-500">Anytown, USA 12345</div>
                        </div>
                        <div class="mt-4">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left">
                                        <th class="py-2">Item</th>
                                        <th class="py-2">Quantity</th>
                                        <th class="py-2">Price</th>
                                        <th class="py-2">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-2">Item 1</td>
                                        <td class="py-2">2</td>
                                        <td class="py-2">$10</td>
                                        <td class="py-2">$20</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">Item 2</td>
                                        <td class="py-2">1</td>
                                        <td class="py-2">$25</td>
                                        <td class="py-2">$25</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">Item 3</td>
                                        <td class="py-2">3</td>
                                        <td class="py-2">$15</td>
                                        <td class="py-2">$45</td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td class="py-2">Subtotal:</td>
                                        <td class="py-2">$90</td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td class="py-2">Tax:</td>
                                        <td class="py-2">$9</td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td class="py-2 font-bold">Total:</td>
                                        <td class="py-2 font-bold">$99</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
