<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { computed } from "vue";
    import SelectBox from "./SelectBox.vue";

    const props = defineProps({
        cheque: {
            type: Object,
            required: true,
        },
        chequeStatus: {
            type: Object,
        },
    });

    let isBeyondDue = computed(() => {
        return new Date(props.cheque.due) <= new Date();
    });

    let form = useForm({
        status: null,
    });

    let submit = () => {
        form.put(route("cheques.updateStatus", props.cheque.id));
    };
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

            <div class="flex space-x-2">
                <div>
                    <SelectBox
                        class="w-52"
                        @change="submit"
                        v-model="form.status"
                    >
                        <option
                            v-for="(key, status) in chequeStatus"
                            :key="key"
                            :value="key"
                            v-text="status"
                        ></option>
                    </SelectBox>
                </div>
                <span
                    class="border rounded py-1 px-2 font-light w-44 text-center"
                    :class="[
                        isBeyondDue
                            ? 'text-red-500 border-red-400'
                            : 'text-gray-900 border-gray-100',
                    ]"
                    v-text="cheque.due_for_humans"
                ></span>
            </div>
        </div>
        <div>
            <strong>Amount: </strong>
            <span v-text="cheque.amount_formated"></span>
        </div>
    </div>
</template>
