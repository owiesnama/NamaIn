<script setup>
    defineProps({
        data: [Array, Object],
    });
</script>

<template>
    <div v-cloak v-show="data.length == 0" class="w-full p-6 text-xl font-semibold text-center text-gray-800">
        <img class="w-full max-w-md mx-auto transform transition-transform duration-500 hover:scale-105 empty-state-animation" src="/images/empty-search.svg" alt="">
        <p class="mt-4">
            <slot>
                <span class="text-gray-500 text-sm font-medium" v-text="__('No result found, Try a different query')"></span>
            </slot>
        </p>
    </div>
</template>

<style scoped>
@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

.empty-state-animation {
    animation: wave 2s ease-in-out infinite;
    transform-origin: bottom center;
}
</style>
