<script setup>
import FavoriteStar from "@/Components/Pos/FavoriteStar.vue";
import { useInventoryStrategy } from "@/Composables/useInventoryStrategy";

defineProps({
    favorites: {
        type: Array,
        default: () => [],
    },
    currency: String,
});

const emit = defineEmits(['add-to-cart', 'toggle-favorite']);

const fmt = (val) => window.formatMoney(val);

// Overselling tenants never block on stock; favourites stay addable.
const { oversellingEnabled } = useInventoryStrategy();
const isAvailable = (product) => oversellingEnabled || product.sale_point_qty > 0;
</script>

<template>
    <div v-if="favorites.length > 0" class="mb-4">
        <div class="flex items-center gap-x-2 mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-amber-500">
                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.007Z" clip-rule="evenodd" />
            </svg>
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Favourites') }}</h3>
        </div>

        <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1">
            <div
                v-for="product in favorites"
                :key="`fav-${product.id}`"
                class="relative shrink-0 w-40"
            >
                <button
                    type="button"
                    :disabled="!isAvailable(product)"
                    class="w-full bg-white dark:bg-gray-900 p-3 rounded-xl border text-start shadow-sm group flex flex-col transition-all duration-150 active:scale-95"
                    :class="[
                        isAvailable(product)
                            ? 'border-gray-200 dark:border-gray-700 hover:border-emerald-400 hover:shadow-md cursor-pointer'
                            : 'border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed grayscale',
                    ]"
                    @click="isAvailable(product) && emit('add-to-cart', product)"
                >
                    <!-- Global (shared) badge — distinguishes the store baseline tier -->
                    <span
                        v-if="product.favorite_scope === 'global'"
                        class="inline-flex items-center gap-x-1 self-start px-1.5 py-0.5 mb-1.5 text-[9px] font-bold uppercase tracking-wider rounded-md bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                        {{ __('Shared') }}
                    </span>

                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 truncate leading-snug ltr:pr-8 rtl:pl-8">{{ product.name }}</h4>

                    <div class="flex items-center justify-between mt-2">
                        <span class="text-sm font-bold text-emerald-600">{{ fmt(product.price || 0) }}</span>
                        <span v-if="isAvailable(product)" class="text-[10px] font-medium text-gray-400"><Ltr>{{ product.sale_point_qty }}</Ltr></span>
                        <span v-else class="text-[10px] font-bold uppercase tracking-wider text-red-500 dark:text-red-400">{{ __('Out of Stock') }}</span>
                    </div>
                </button>

                <!-- Star toggle overlays the card (avoids nesting a button in a button) -->
                <div class="absolute top-2 ltr:right-2 rtl:left-2">
                    <FavoriteStar :active="product.is_favorite" @toggle="emit('toggle-favorite', product)" />
                </div>
            </div>
        </div>
    </div>
</template>
