import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.user?.permissions ?? []);
    const role = computed(() => page.props.user?.role ?? null);

    const can = (permission) => permissions.value.includes(permission);
    const hasRole = (...roles) => roles.includes(role.value?.slug);
    const isOwner = () => hasRole('owner');

    return { can, hasRole, isOwner, permissions, role };
}
