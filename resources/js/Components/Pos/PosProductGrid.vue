<script setup>
import { ref, watch, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import debounce from "lodash/debounce";
import TextInput from "@/Components/TextInput.vue";
import PosFavorites from "@/Components/Pos/PosFavorites.vue";
import FavoriteStar from "@/Components/Pos/FavoriteStar.vue";

const props = defineProps({
    initialProducts: Object,
    hotProducts: {
        type: Array,
        default: () => [],
    },
    favoriteProducts: {
        type: Array,
        default: () => [],
    },
    currency: String,
});

const emit = defineEmits(['add-to-cart', 'toggle-favorite']);

const fmt = (val) => `${Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${props.currency}`;

// Overselling tenants never block on stock; every product stays addable.
const oversellingEnabled = [true, 1, '1', 'true'].includes(window.preferences?.('allow_overselling', false));

const search = ref('');
const products = ref(props.initialProducts.data);
const loadingMore = ref(false);
const productLandmark = ref(null);

const loadMore = () => {
    if (!props.initialProducts.next_page_url || loadingMore.value) return;
    loadingMore.value = true;

    router.get(
        props.initialProducts.next_page_url,
        { search: search.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['initialProducts'],
            onSuccess() {
                products.value = [...products.value, ...props.initialProducts.data];
                loadingMore.value = false;
            },
            onError() {
                loadingMore.value = false;
            },
        }
    );
};

const searchProducts = debounce(() => {
    router.get(
        route('pos.index'),
        { search: search.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['initialProducts'],
            onSuccess() {
                products.value = props.initialProducts.data;
            },
        }
    );
}, 300);

watch(search, searchProducts);

const scrollObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) loadMore();
    });
});

onMounted(() => {
    if (productLandmark.value) scrollObserver.observe(productLandmark.value);
});

watch(productLandmark, (el, oldEl) => {
    if (oldEl) scrollObserver.unobserve(oldEl);
    if (el) scrollObserver.observe(el);
});

onUnmounted(() => {
    scrollObserver.disconnect();
});
</script>

<template>
    <div class="flex-grow flex flex-col min-w-0 min-h-0">
        <!-- Favourites section (user tier first, then global tier) -->
        <PosFavorites
            :favorites="favoriteProducts"
            :currency="currency"
            @add-to-cart="emit('add-to-cart', $event)"
            @toggle-favorite="emit('toggle-favorite', $event)"
        />

        <!-- Hot items strip -->
        <div v-if="hotProducts.length > 0" class="mb-4">
            <div class="flex items-center gap-x-2 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-amber-500">
                    <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 0 0-1.071-.136 9.742 9.742 0 0 0-3.539 6.176 7.547 7.547 0 0 1-1.705-1.715.75.75 0 0 0-1.152-.082A9 9 0 1 0 15.68 4.534a7.46 7.46 0 0 1-2.717-2.248ZM15.75 14.25a3.75 3.75 0 1 1-7.313-1.172c.628.465 1.35.81 2.133 1.002A5.99 5.99 0 0 1 12 12.75c0-1.18-.34-2.281-.926-3.214a3.75 3.75 0 0 1 4.676 4.714Z" clip-rule="evenodd" />
                </svg>
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Hot Items') }}</h3>
            </div>
            <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1">
                <button
                    v-for="product in hotProducts"
                    :key="`hot-${product.id}`"
                    type="button"
                    :disabled="!oversellingEnabled && product.sale_point_qty === 0 && !product.replenishment"
                    class="shrink-0 w-36 bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-700 text-start shadow-sm group flex flex-col transition-all duration-150 active:scale-95"
                    :class="!oversellingEnabled && product.sale_point_qty === 0 && !product.replenishment
                        ? 'opacity-40 cursor-not-allowed grayscale'
                        : 'hover:border-emerald-400 hover:shadow-md cursor-pointer'"
                    @click="emit('add-to-cart', product)"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 truncate leading-snug">{{ product.name }}</h4>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-sm font-bold text-emerald-600">{{ fmt(product.price || 0) }}</span>
                        <span v-if="product.sale_point_qty > 0" class="text-[10px] font-medium text-gray-400">{{ product.sale_point_qty }}</span>
                        <span v-else-if="product.replenishment" class="text-[10px] font-medium text-amber-500">{{ __('via warehouse') }}</span>
                    </div>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <div class="relative flex items-center">
                <span class="absolute ltr:left-3 rtl:right-3 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </span>
                <TextInput v-model="search" :placeholder="__('Search products...')" class="w-full ltr:ps-10 rtl:pe-10 py-3 rounded-xl" />
            </div>
        </div>

        <div class="flex-grow overflow-y-auto overscroll-contain grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-1 pb-24 md:pb-1 auto-rows-max">
            <div v-for="product in products" :key="product.id" class="relative h-fit">
                <button
                    type="button"
                    :disabled="!oversellingEnabled && product.sale_point_qty === 0 && !product.replenishment"
                    class="w-full bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-start shadow-sm group flex flex-col transition-all duration-150 active:scale-95"
                    :class="!oversellingEnabled && product.sale_point_qty === 0 && !product.replenishment
                        ? 'opacity-40 cursor-not-allowed grayscale'
                        : 'hover:border-emerald-400 hover:shadow-md cursor-pointer'"
                    @click="emit('add-to-cart', product)"
                >
                    <div class="w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-lg mb-3 flex items-center justify-center text-gray-300 group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/20 group-hover:text-emerald-400 transition-colors overflow-hidden relative">
                        <div v-if="product.sale_point_qty === 0 && product.replenishment" class="absolute top-1.5 start-1.5 flex items-center gap-x-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[9px] font-bold uppercase tracking-wider z-10">
                            <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                            {{ __('Transfer') }}
                        </div>
                        <div v-else-if="!oversellingEnabled && product.sale_point_qty === 0" class="absolute inset-0 bg-gray-900/10 dark:bg-gray-900/30 flex items-center justify-center rounded-lg">
                            <span class="bg-gray-800/80 text-white px-2 py-1 rounded text-[10px] font-bold uppercase">{{ __('Out of Stock') }}</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 truncate leading-snug ltr:pr-8 rtl:pl-8">{{ product.name }}</h3>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <span class="text-sm font-bold text-emerald-600">{{ fmt(product.price || 0) }}</span>
                        <span v-if="product.sale_point_qty > 0" class="text-[10px] font-medium text-gray-400">{{ product.sale_point_qty }}</span>
                        <span v-else-if="product.replenishment" class="text-[10px] font-medium text-amber-500">{{ __('via warehouse') }}</span>
                    </div>
                </button>

                <!-- Star toggle overlays the card corner (logical end for RTL) -->
                <div class="absolute top-2 ltr:right-2 rtl:left-2">
                    <FavoriteStar :active="product.is_favorite" @toggle="emit('toggle-favorite', product)" />
                </div>
            </div>
            <div ref="productLandmark" class="col-span-full h-1"></div>
            <div v-if="loadingMore" class="col-span-full flex justify-center py-4">
                <svg class="animate-spin h-5 w-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    </div>
</template>
