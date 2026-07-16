<script setup>
    import { provide } from "vue";
    import { useForm } from "@inertiajs/vue3";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import SettingsLayout from "@/Layouts/SettingsLayout.vue";
    import UpdateApplicationInformationForm from "@/Pages/Preferences/Partials/UpdateApplicationInformationForm.vue";
    import ActionMessage from "@/Components/ActionMessage.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";

    const truthy = (value) => [true, 1, "1", "true"].includes(value);

    // Provided to the fields partial so it can bind to the shared form without
    // mutating a prop (the page keeps ownership for the header Save + dirty state).

    // Form ownership lives at the page level so the Save action can render in
    // the sticky header (out of the page body entirely) while the fields stay
    // in the content column.
    const form = useForm({
        // Null until a new file is picked, so an unchanged save never re-POSTs
        // the stored path (which would fail the `image` rule); the action's
        // null-skip then preserves the existing logo.
        logo: null,
        invoicesHeadline: preferences("invoicesHeadline"),
        alerts: truthy(preferences("alerts", true)),
        currency: preferences("currency", "SDG"),
        pecentage: preferences("pecentage", 60),
        inventory_strategy: preferences("inventory_strategy", "purchase_driven"),
        allow_overselling: truthy(preferences("allow_overselling", false)),
    });

    provide("settingsForm", form);

    const save = () => {
        // allow_overselling only applies under free-form; purchase-driven always blocks.
        if (form.inventory_strategy !== "free_form") {
            form.allow_overselling = false;
        }

        form.post(route("preferences.update"), {
            preserveScroll: true,
            // Rebase the dirty baseline onto the saved values so the header's
            // "unsaved changes" indicator clears after a successful save.
            onSuccess: () => form.defaults(),
        });
    };

    // Section anchors — the nav targets become the <SettingsSection> cards in Phase 2.
    const sections = [
        { id: "identity", label: __("Identity") },
        { id: "pricing", label: __("Pricing & currency") },
        { id: "inventory", label: __("Inventory policy") },
        { id: "notifications", label: __("Notifications") },
    ];
</script>

<template>
    <AppLayout :title="__('Organization Settings')">
        <form @submit.prevent="save">
            <SettingsLayout
                :title="__('Organization Settings')"
                :subtitle="__('Update your Application information and Basic Data')"
            >
                <template #header-actions>
                    <ActionMessage :on="form.recentlySuccessful">
                        {{ __("Saved.") }}
                    </ActionMessage>

                    <!-- Dirty indicator: only while there are unsaved edits. -->
                    <span
                        v-if="form.isDirty"
                        class="inline-flex items-center gap-x-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                        {{ __("Unsaved changes") }}
                    </span>

                    <!-- Muted-looking when there's nothing to save, but still
                         clickable; only hard-disabled while a save is in flight. -->
                    <PrimaryButton
                        type="submit"
                        :class="{
                            'opacity-25': form.processing,
                            'opacity-50': !form.isDirty && !form.processing,
                        }"
                        :disabled="form.processing"
                    >
                        {{ __("Save") }}
                    </PrimaryButton>
                </template>

                <!-- Section navigation (anchors wired to real sections in Phase 2). -->
                <template #nav>
                    <nav
                        class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible"
                    >
                        <a
                            v-for="section in sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="shrink-0 rounded-lg px-3 py-2 text-sm text-secondary transition-colors duration-200 hover:bg-surface-sunken hover:text-primary"
                        >
                            {{ section.label }}
                        </a>
                    </nav>
                </template>

                <!-- Fields read the shared form via inject("settingsForm"). -->
                <UpdateApplicationInformationForm />
            </SettingsLayout>
        </form>
    </AppLayout>
</template>
