<template>
    <div
        class="fixed inset-0 z-50 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end"
    >
        <transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-class="translate-x-20 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-class="opacity-100 otranslate-x-0"
            leave-to-class="translate-x-20 opacity-0"
        >
            <div v-show="$page.props.flash.success && show" class="w-full max-w-sm px-2 py-3 rounded-lg shadow-lg bg-emerald-500 sm:p-3">
                <div class="flex items-center justify-between text-white">
                    <div class="flex items-center flex-1 w-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>

                        <p class="mx-2 font-medium tcate">
                            {{ $page.props.flash?.success }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>

        <transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-class="translate-x-20 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-class="opacity-100 otranslate-x-0"
            leave-to-class="translate-x-20 opacity-0"
        >
            <div v-show="$page.props.flash.error && show" class="w-full max-w-sm px-2 py-3 bg-red-500 rounded-lg shadow-lg sm:p-3">
                <div class="flex items-center justify-between text-white">
                    <div class="flex items-center flex-1 w-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>

                        <p class="mx-2 font-medium tcate">
                            {{ $page.props.flash?.error }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
export default {
  data() {
    return {
      show: false,
    }
  },
  watch: {
    '$page.props.flash': {
      handler() {
        this.show = true;

        setTimeout(() => {
            this.show = false;
        }, 2000);
      },
      deep: true,
    },
  },
}
</script>
