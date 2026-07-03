import { ref } from "vue";

/**
 * The filter panel renders inline at lg+ (>=1024px) and as a slide-over drawer
 * below lg. It should start open on wide screens (where it costs no content
 * space) and closed on tablet/phone (where it would otherwise cover the data).
 *
 * @returns {import('vue').Ref<boolean>}
 */
export const useFilterSidebar = () => {
    const isDesktop =
        typeof window !== "undefined" &&
        window.matchMedia("(min-width: 1024px)").matches;

    return ref(isDesktop);
};
