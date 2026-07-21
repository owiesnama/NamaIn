<script setup>
    import { inject } from "vue";
    import CustomSelect from "@/Components/CustomSelect.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import SettingsSection from "@/Components/SettingsSection.vue";

    defineProps({
        cashAccounts: { type: Array, default: () => [] },
        bankAccounts: { type: Array, default: () => [] },
        salePoints: { type: Array, default: () => [] },
    });

    // Form is owned by the page (Show.vue) so Save can live in the sticky header.
    const form = inject("settingsForm");
</script>

<template>
    <SettingsSection
        id="pos"
        :title="__('Point of Sale')"
        :description="__('Defaults applied at the register — where money lands and which sale point is used.')"
    >
        <div class="space-y-6">
            <!-- Default cash account -->
            <div>
                <div class="flex items-center justify-between">
                    <InputLabel :value="__('Default cash account')" />
                    <button
                        v-if="form.pos_default_cash_account_id"
                        type="button"
                        class="text-xs text-secondary hover:text-primary"
                        @click="form.pos_default_cash_account_id = null"
                    >
                        {{ __('Clear') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ __('Cash sales with no sale-point drawer fall back to this account.') }}
                </p>
                <div class="mt-2 sm:max-w-md">
                    <CustomSelect
                        v-model="form.pos_default_cash_account_id"
                        :options="cashAccounts"
                        label="name"
                        track-by="id"
                        :placeholder="__('Select an account')"
                    />
                </div>
                <InputError
                    :message="form.errors.pos_default_cash_account_id"
                    class="mt-2"
                />
            </div>

            <!-- Default bank account -->
            <div>
                <div class="flex items-center justify-between">
                    <InputLabel :value="__('Default bank account')" />
                    <button
                        v-if="form.pos_default_bank_account_id"
                        type="button"
                        class="text-xs text-secondary hover:text-primary"
                        @click="form.pos_default_bank_account_id = null"
                    >
                        {{ __('Clear') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ __('Bank-transfer sales are recorded against this account when none is picked at checkout.') }}
                </p>
                <div class="mt-2 sm:max-w-md">
                    <CustomSelect
                        v-model="form.pos_default_bank_account_id"
                        :options="bankAccounts"
                        label="name"
                        track-by="id"
                        :placeholder="__('Select an account')"
                    />
                </div>
                <InputError
                    :message="form.errors.pos_default_bank_account_id"
                    class="mt-2"
                />
            </div>

            <!-- Default sale point -->
            <div>
                <div class="flex items-center justify-between">
                    <InputLabel :value="__('Default sale point')" />
                    <button
                        v-if="form.pos_default_sale_point_id"
                        type="button"
                        class="text-xs text-secondary hover:text-primary"
                        @click="form.pos_default_sale_point_id = null"
                    >
                        {{ __('Clear') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ __('The sale point pre-selected when opening a POS session.') }}
                </p>
                <div class="mt-2 sm:max-w-md">
                    <CustomSelect
                        v-model="form.pos_default_sale_point_id"
                        :options="salePoints"
                        label="name"
                        track-by="id"
                        :placeholder="__('Select a sale point')"
                    />
                </div>
                <InputError
                    :message="form.errors.pos_default_sale_point_id"
                    class="mt-2"
                />
            </div>
        </div>
    </SettingsSection>
</template>
