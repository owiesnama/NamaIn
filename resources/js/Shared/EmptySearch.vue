<script setup>
    defineProps({
        data: Object,
    });
</script>

<template>
    <div v-cloak v-show="data.length == 0" class="w-full p-6 text-xl font-semibold text-center text-gray-800">
        <img class="w-full max-w-md mx-auto" src="/images/empty-search.svg" alt="">
        <p>No result found, Try a different query</p>
    </div>
</template>