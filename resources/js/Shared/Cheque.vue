<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { computed } from "vue";
    import InputLabel from "@/Components/InputLabel.vue";
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
    <div :class="cheque.is_credit  ? 'border-emerald-500' : 'border-red-500'" class="p-6 bg-white border-l-4 border-dashed rounded-lg shadow-md shadow-gray-200">
        <div class="flex items-center justify-between">
            <InputLabel
                :value="'#' + cheque.id + ' Payee'"
            />

            <p class="text-sm" :class="isBeyondDue ? 'text-red-500 font-semibold' : 'text-gray-500 font-medium'" v-text="cheque.due_for_humans"></p>
        </div>

        <h2 class="mt-1 text-lg font-semibold text-gray-800" v-text="cheque.payee.name"></h2>

        
        <div class="mt-4 sm:flex sm:items-end sm:justify-between" >
            <div>
                <InputLabel
                    value="Status"
                />
                <SelectBox
                    class="w-full mt-1 text-sm rounded-lg sm:w-36"
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
            
            <h3 :class="cheque.is_credit  ? 'text-emerald-500' : 'text-red-500'" class="mt-4 font-bold sm:mt-0" v-text="cheque.amount_formated"></h3>
        </div>
    </div>
</template>
