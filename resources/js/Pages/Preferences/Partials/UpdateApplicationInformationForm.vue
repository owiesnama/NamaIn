<script setup>
    import { computed, inject, watch } from "vue";
    import CurrencySelect from "@/Components/CurrencySelect.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import LogoUploader from "@/Components/LogoUploader.vue";
    import NumberField from "@/Components/NumberField.vue";
    import SettingsSection from "@/Components/SettingsSection.vue";
    import ToggleField from "@/Components/ToggleField.vue";

    // Form is owned by the page (Show.vue) so Save can live in the sticky header.
    // This partial only renders the fields bound to that shared form.
    const form = inject("settingsForm");

    const isFlexible = computed(
        () => form.inventory_strategy === "free_form"
    );

    // Overselling is only meaningful under the flexible strategy. Reset it when
    // the user switches to strict (change only — never on initial load, so an
    // untouched form stays clean).
    watch(
        () => form.inventory_strategy,
        (strategy) => {
            if (strategy !== "free_form") {
                form.allow_overselling = false;
            }
        }
    );

    // Resolve the stored logo path to a URL the browser can load. Bare disk
    // paths (e.g. "logos/x.png") are served from the public storage symlink.
    const currentLogoUrl = computed(() => {
        const path = preferences("logo");
        if (!path) {
            return "/images/logo.svg";
        }
        if (path.startsWith("http") || path.startsWith("/")) {
            return path;
        }
        return `/storage/${path}`;
    });

    const cardClass = (selected) => [
        "rounded-xl border bg-surface transition-colors",
        selected
            ? "border-emerald-500 ring-1 ring-inset ring-emerald-500"
            : "border-line hover:border-line-strong",
    ];
</script>

<template>
    <!-- Identity -->
    <SettingsSection
        id="identity"
        :title="__('Identity')"
        :description="__('Your logo and the header printed on invoices.')"
    >
        <div class="space-y-6">
            <!-- Logo -->
            <div>
                <InputLabel
                    for="logo"
                    :value="__('Logo')"
                />
                <div class="mt-2">
                    <LogoUploader
                        v-model="form.logo"
                        :current-url="currentLogoUrl"
                    />
                </div>
                <InputError
                    :message="form.errors.logo"
                    class="mt-2"
                />
            </div>

            <!-- Invoices Headline -->
            <div>
                <InputLabel
                    for="invoicesHeadline"
                    :value="__('Invoices Headline')"
                />
                <textarea
                    id="invoicesHeadline"
                    v-model="form.invoicesHeadline"
                    name="invoicesHeadline"
                    class="w-full h-32 px-3 py-2 mt-1 text-primary bg-surface border border-line rounded-lg placeholder-disabled focus:border-emerald-300 dark:focus:border-emerald-600 focus:ring focus:ring-emerald-200 dark:focus:ring-emerald-800 focus:ring-opacity-50"
                ></textarea>
                <InputError
                    :message="form.errors.invoicesHeadline"
                    class="mt-2"
                />
            </div>
        </div>
    </SettingsSection>

    <!-- Pricing & currency -->
    <SettingsSection
        id="pricing"
        :title="__('Pricing & currency')"
        :description="__('Default profit margin and the system-wide currency.')"
    >
        <div class="space-y-6">
            <!-- Margin percentage -->
            <div>
                <InputLabel
                    for="pecentage"
                    :value="__('Margin Revenu Percentage')"
                />
                <div class="mt-1">
                    <NumberField
                        id="pecentage"
                        v-model="form.pecentage"
                        suffix="%"
                        placeholder="60"
                        class="sm:max-w-xs"
                    />
                </div>
                <InputError
                    :message="form.errors.pecentage"
                    class="mt-2"
                />
            </div>

            <!-- Currency -->
            <div>
                <InputLabel
                    for="currency"
                    :value="__('Currency')"
                />
                <div class="mt-1">
                    <CurrencySelect
                        id="currency"
                        v-model="form.currency"
                    />
                </div>
                <InputError
                    :message="form.errors.currency"
                    class="mt-2"
                />
            </div>
        </div>
    </SettingsSection>

    <!-- Inventory policy -->
    <SettingsSection
        id="inventory"
        :title="__('Inventory policy')"
        :description="__('How stock is tracked, and whether selling below zero is allowed.')"
    >
        <p class="text-sm text-secondary">
            {{ __("Choose how you want to manage your inventory.") }}
        </p>

        <div class="mt-3 space-y-3">
            <!-- Strict: whole card is the hit target -->
            <label
                class="block cursor-pointer p-4"
                :class="cardClass(form.inventory_strategy === 'purchase_driven')"
            >
                <span class="flex items-start gap-x-3">
                    <input
                        v-model="form.inventory_strategy"
                        type="radio"
                        value="purchase_driven"
                        class="mt-0.5 h-4 w-4 text-emerald-600 border-line focus:ring-emerald-200"
                    />
                    <span>
                        <span class="block text-sm font-medium text-primary">
                            {{ __("Purchase-driven (strict)") }}
                        </span>
                        <span class="block text-xs text-secondary">
                            {{ __("Stock enters only through purchase invoices. Sales are blocked when stock runs out.") }}
                        </span>
                    </span>
                </span>
            </label>

            <!-- Flexible: selectable header + nested overselling sub-row -->
            <div
                class="overflow-hidden"
                :class="cardClass(isFlexible)"
            >
                <label class="flex cursor-pointer items-start gap-x-3 p-4">
                    <input
                        v-model="form.inventory_strategy"
                        type="radio"
                        value="free_form"
                        class="mt-0.5 h-4 w-4 text-emerald-600 border-line focus:ring-emerald-200"
                    />
                    <span>
                        <span class="block text-sm font-medium text-primary">
                            {{ __("Free-form (flexible)") }}
                        </span>
                        <span class="block text-xs text-secondary">
                            {{ __("Adjust quantities freely without purchase invoices.") }}
                        </span>
                    </span>
                </label>

                <!-- Nested: only meaningful under flexible. Kept mounted (muted +
                     disabled) under strict to avoid a layout jump. -->
                <div class="border-t border-line bg-surface-sunken px-4 py-3">
                    <ToggleField
                        v-model="form.allow_overselling"
                        :disabled="!isFlexible"
                        :label="__('Allow overselling')"
                        :description="__('Allow selling below zero (overselling). Negative balances are surfaced for later reconciliation.')"
                    />
                </div>
            </div>
        </div>

        <InputError
            :message="form.errors.inventory_strategy"
            class="mt-2"
        />
        <InputError
            :message="form.errors.allow_overselling"
            class="mt-2"
        />
    </SettingsSection>

    <!-- Notifications -->
    <SettingsSection
        id="notifications"
        :title="__('Notifications')"
        :description="__('Alerts sent to keep you informed about your inventory.')"
    >
        <ToggleField
            v-model="form.alerts"
            :label="__('Alerts')"
            :description="__('Send Notifications When Stocks Running Out')"
        />
        <InputError
            :message="form.errors.alerts"
            class="mt-2"
        />
    </SettingsSection>
</template>
