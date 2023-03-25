<template>
    <div class="flex flex-col items-center min-h-screen pt-6 sm:justify-center sm:pt-0">
        <div class="flex flex-col items-center">
            <slot name="logo" />
        </div>

        <div class="w-full px-6 mt-4 overflow-hidden bg-white sm:max-w-md sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
