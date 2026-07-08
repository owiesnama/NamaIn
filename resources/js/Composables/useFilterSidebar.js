import { ref } from "vue";

/**
 * The filter panel is a slide-over drawer at every breakpoint. It stays closed
 * until the user opens it from the "Filters" button so it never covers the data
 * on load; applied filters remain visible as removable chips above the content.
 *
 * @returns {import('vue').Ref<boolean>}
 */
export const useFilterSidebar = () => {
    return ref(false);
};
