import { ref } from "vue";
export function useQueryString(key) {
    const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop),
    });
    return ref(params[key]);
}
