import { ref } from 'vue';
import { debounce } from 'lodash';
import axios from 'axios';

export function useAsyncOptions(url, { debounceMs = 300 } = {}) {
    const options = ref([]);
    const loading = ref(false);
    const hasMore = ref(true);
    const page = ref(1);
    const search = ref('');

    let abortController = null;

    const fetch = async (append = false) => {
        if (loading.value) return;
        if (append && !hasMore.value) return;

        loading.value = true;

        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        try {
            const response = await axios.get(url, {
                params: {
                    search: search.value || undefined,
                    page: page.value,
                },
                signal: abortController.signal,
            });

            const data = response.data;
            const newItems = data.data || [];

            if (append) {
                options.value = [...options.value, ...newItems];
            } else {
                options.value = newItems;
            }

            hasMore.value = !!data.next_page_url;
        } catch (e) {
            if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
                console.error('useAsyncOptions fetch error:', e);
            }
        } finally {
            loading.value = false;
        }
    };

    const loadMore = () => {
        if (!hasMore.value || loading.value) return;
        page.value++;
        fetch(true);
    };

    const debouncedSearch = debounce((query) => {
        search.value = query;
        page.value = 1;
        hasMore.value = true;
        fetch(false);
    }, debounceMs);

    const onSearch = (query) => {
        debouncedSearch(query);
    };

    // Initial load
    fetch(false);

    return {
        options,
        loading,
        hasMore,
        loadMore,
        onSearch,
    };
}
