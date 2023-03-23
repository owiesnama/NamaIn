<script setup>
    defineProps({
        error: String
    });
</script>
<template>
    <p
        v-if="error"
        class="font-extralight capitalize text-red-500 mt-2"
        v-text="error"
    ></p>
</template>
