<script setup>
import { useForm } from "@inertiajs/vue3";
import DialogModal from "@/Components/DialogModal.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String, // 'customer' or 'supplier'
        required: true,
    },
});

const emit = defineEmits(["close", "created"]);

const form = useForm({
    name: "",
    address: "",
    phone_number: "",
});

const close = () => {
    form.reset();
    emit("close");
};

const submit = () => {
    const routeName = props.type === 'customer' ? 'customers.store' : 'suppliers.store';

    axios.post(route(routeName), form.data(), {
        headers: {
            'X-Quick-Add': 'true',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        emit("created", response.data.data);
        close();
    })
    .catch(error => {
        if (error.response?.data?.errors) {
            form.setError(error.response.data.errors);
        }
    });
};
</script>

<template>
    <DialogModal :show="show" @close="close">
        <template #title>
            {{ __("Add New") }} {{ type === 'customer' ? __("Customer") : __("Supplier") }}
        </template>

        <template #content>
            <div class="space-y-4">
                <div>
                    <InputLabel for="name" :value="__('Name')" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="phone_number" :value="__('Phone Number')" />
                    <TextInput
                        id="phone_number"
                        v-model="form.phone_number"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.phone_number" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="address" :value="__('Address')" />
                    <TextInput
                        id="address"
                        v-model="form.address"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.address" class="mt-2" />
                </div>
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="close">
                {{ __("Cancel") }}
            </SecondaryButton>

            <PrimaryButton
                class="ml-3"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="submit"
            >
                {{ __("Save") }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
