import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Plan entitlements for the current tenant, mirroring usePermissions().
 *
 * `hasFeature` gates boolean capabilities; `limit`/`remaining`/`atLimit` read
 * numeric caps. Usage counts are not shared globally — pass the `used` value
 * (fetched lazily per page) into `remaining`/`atLimit`.
 */
export function useFeatures() {
    const page = usePage();

    const features = computed(() => page.props.entitlements?.features ?? {});
    const limits = computed(() => page.props.entitlements?.limits ?? {});

    const hasFeature = (feature) => features.value[feature] === true;

    const limit = (feature) => (feature in limits.value ? limits.value[feature] : null);

    const isUnlimited = (feature) => limit(feature) === null;

    const remaining = (feature, used) => {
        const cap = limit(feature);
        return cap === null ? null : Math.max(0, cap - used);
    };

    const atLimit = (feature, used) => {
        const cap = limit(feature);
        return cap !== null && used >= cap;
    };

    return { hasFeature, limit, isUnlimited, remaining, atLimit, features, limits };
}
