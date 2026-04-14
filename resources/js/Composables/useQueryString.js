import { ref } from "vue";

export function useQueryString(keys) {
    const params = new URLSearchParams(window.location.search);
    if (Array.isArray(keys)) {
        return Object.fromEntries(keys.map(k => [k, ref(params.get(k))]));
    }
    return ref(params.get(keys));
}
