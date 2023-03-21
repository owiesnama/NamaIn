<script setup>
    defineProps({
        cheque:{
            type: Object,
            required: true
        }
    })
</script>
<template>
    <div
        class="border p-3 bg-white rounded border-l-2 mb-2"
        :class="[cheque.is_credit ? 'border-l-green-500' : 'border-l-red-500']"
    >
        <div class="flex justify-between items-center">
            <div>
                <strong>Payee:</strong
                ><i
                    class="mx-1"
                    v-text="cheque.payee.name"
                ></i>
            </div>
            <div>
                Due:
                <span
                    class="font-semibold"
                    v-text="cheque.due"
                ></span>
            </div>
        </div>
        <div>
            <strong>Amount: </strong>
            <span v-text="cheque.amount_formated"></span>
        </div>
    </div>
</template>
