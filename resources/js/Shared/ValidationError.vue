<script setup>
    defineProps({
        error: String
    });
</script>
<template>
    <p
        class="font-extralight capitalize text-red-500 mt-2"
        v-if="error"
        v-text="error"
    ></p>
</template>
