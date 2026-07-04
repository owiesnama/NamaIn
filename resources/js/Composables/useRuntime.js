import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Runtime seam S5 (Design 03 §2.2): exposes the runtime profile shared by
 * HandleInertiaRequests so the UI can gate cloud-only or local-only surfaces.
 */
export function useRuntime() {
    const page = usePage();

    const runtime = computed(() => page.props.runtime ?? 'cloud');
    const isLocal = computed(() => runtime.value === 'local');
    const isCloud = computed(() => !isLocal.value);

    return { runtime, isLocal, isCloud };
}
