<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: [Object, Array, String, Number],
    options: {
        type: Array,
        required: true,
        default: () => []
    },
    label: {
        type: String,
        default: 'label'
    },
    trackBy: {
        type: String,
        default: 'id'
    },
    placeholder: {
        type: String,
        default: 'Select option'
    },
    searchable: {
        type: Boolean,
        default: true
    },
    disabled: {
        type: Boolean,
        default: false
    },
    multiple: {
        type: Boolean,
        default: false
    },
    closeOnSelect: {
        type: Boolean,
        default: true
    },
    taggable: {
        type: Boolean,
        default: false
    },
    tagPlaceholder: {
        type: String,
        default: 'Press enter to create a tag'
    },
    remote: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'search-change', 'tag']);

const isOpen = ref(false);
const searchQuery = ref('');
const selectRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const highlightedIndex = ref(-1);

const displayValue = computed(() => {
    if (!props.modelValue && props.modelValue !== 0) return '';

    if (props.multiple) {
        return props.modelValue.map(item => {
            if (typeof item !== 'object') {
                const option = props.options.find(o => String(typeof o === 'object' ? o[props.trackBy] : o) === String(item));
                return option ? (typeof option === 'object' ? option[props.label] : option) : item;
            }
            return item[props.label];
        }).join(', ');
    }

    if (typeof props.modelValue !== 'object') {
        const option = props.options.find(o => String(typeof o === 'object' ? o[props.trackBy] : o) === String(props.modelValue));
        return option ? (typeof option === 'object' ? option[props.label] : option) : props.modelValue;
    }

    return props.modelValue[props.label];
});

const filteredOptions = computed(() => {
    let options = props.options;

    if (props.remote) {
        return options;
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        options = props.options.filter(option => {
            const value = typeof option === 'object' ? option[props.label] : option;
            return String(value).toLowerCase().includes(query);
        });
    }

    if (props.taggable && searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        if (query.length > 0) {
            const exactMatch = props.options.some(option => {
                const value = typeof option === 'object' ? option[props.label] : option;
                return String(value).toLowerCase() === query;
            });

            if (!exactMatch) {
                return [
                    {
                        [props.label]: searchQuery.value,
                        [props.trackBy]: searchQuery.value, // Use name as ID for tags
                        isTag: true
                    },
                    ...options
                ];
            }
        }
    }

    return options;
});

const hasValue = computed(() => {
    if (props.multiple) {
        return props.modelValue && props.modelValue.length > 0;
    }
    return props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '';
});

const selectedOption = computed(() => {
    if (!hasValue.value) return null;
    if (typeof props.modelValue === 'object') return props.modelValue;
    return props.options.find(o => String(typeof o === 'object' ? o[props.trackBy] : o) === String(props.modelValue)) ?? null;
});

const toggleDropdown = () => {
    if (props.disabled) return;

    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        searchQuery.value = '';
        highlightedIndex.value = -1;
        emit('search-change', '');
        setTimeout(() => {
            if (props.searchable && searchInputRef.value) {
                searchInputRef.value.focus();
            }
        }, 50);
    }
};

const selectOption = (option) => {
    if (option && option.isTag) {
        emit('tag', option[props.label]);
        searchQuery.value = '';
        if (props.closeOnSelect) {
            isOpen.value = false;
        }
        return;
    }

    if (props.multiple) {
        const currentValue = props.modelValue || [];
        const optionId = typeof option === 'object' ? option[props.trackBy] : option;
        const index = currentValue.findIndex(item => {
            const itemId = typeof item === 'object' ? item[props.trackBy] : item;
            return String(itemId) === String(optionId);
        });

        if (index > -1) {
            const newValue = [...currentValue];
            newValue.splice(index, 1);
            emit('update:modelValue', newValue);
        } else {
            emit('update:modelValue', [...currentValue, option]);
        }

        if (!props.closeOnSelect) {
            searchQuery.value = '';
        }
    } else {
        const value = typeof option === 'object' ? option[props.trackBy] : option;
        emit('update:modelValue', value);
    }

    if (props.closeOnSelect) {
        isOpen.value = false;
    }

    searchQuery.value = '';
};

const isSelected = (option) => {
    if (!props.modelValue && props.modelValue !== 0) return false;

    if (props.multiple) {
        const optionId = typeof option === 'object' ? option[props.trackBy] : option;
        return props.modelValue.some(item => {
            const itemId = typeof item === 'object' ? item[props.trackBy] : item;
            return itemId === optionId;
        });
    }

    const optionId = typeof option === 'object' ? option[props.trackBy] : option;
    const valueId = typeof props.modelValue === 'object' ? props.modelValue[props.trackBy] : props.modelValue;
    return String(optionId) === String(valueId);
};

const handleClickOutside = (event) => {
    if (selectRef.value && !selectRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

const handleKeydown = (event) => {
    if (!isOpen.value) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleDropdown();
        }
        return;
    }

    switch (event.key) {
        case 'Escape':
            isOpen.value = false;
            break;
        case 'ArrowDown':
            event.preventDefault();
            highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1);
            scrollToHighlighted();
            break;
        case 'ArrowUp':
            event.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
            scrollToHighlighted();
            break;
        case 'Enter':
            if (isOpen.value) {
                event.preventDefault();
                if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
                    selectOption(filteredOptions.value[highlightedIndex.value]);
                }
            }
            break;
    }
};

const scrollToHighlighted = () => {
    if (dropdownRef.value && highlightedIndex.value >= 0) {
        const highlightedElement = dropdownRef.value.children[highlightedIndex.value];
        if (highlightedElement) {
            highlightedElement.scrollIntoView({ block: 'nearest' });
        }
    }
};

watch(searchQuery, (newValue) => {
    emit('search-change', newValue);
    highlightedIndex.value = 0;
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="selectRef" class="custom-select" :class="{ 'custom-select--disabled': disabled }">
        <div
            class="custom-select__trigger"
            :class="{ 'custom-select__trigger--open': isOpen }"
            @click.stop="toggleDropdown"
            @keydown="handleKeydown"
            tabindex="0"
        >
            <div class="custom-select__value">
                <slot name="singleLabel" :option="selectedOption" v-if="hasValue">
                    <span class="custom-select__selected">{{ displayValue }}</span>
                </slot>
                <span v-else class="custom-select__placeholder">{{ placeholder === 'Select option' ? __('Select option') : placeholder }}</span>
            </div>
            <div class="custom-select__arrow" :class="{ 'custom-select__arrow--open': isOpen }">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <Transition name="dropdown">
            <div v-if="isOpen" class="custom-select__dropdown">
                <div v-if="searchable" class="custom-select__search">
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            class="custom-select__search-input"
                            :placeholder="placeholder === 'Select option' ? __('Select option') : placeholder"
                            @keydown="handleKeydown"
                        />
                </div>

                <div ref="dropdownRef" class="custom-select__options">
                    <template v-if="filteredOptions.length > 0">
                        <div
                            v-for="(option, index) in filteredOptions"
                            :key="typeof option === 'object' ? option[trackBy] : option"
                            class="custom-select__option"
                            :class="{
                                'custom-select__option--selected': isSelected(option),
                                'custom-select__option--highlighted': index === highlightedIndex,
                                'custom-select__option--tag': option.isTag
                            }"
                            @click="selectOption(option)"
                            @mouseenter="highlightedIndex = index"
                        >
                            <slot name="option" :option="option" :index="index">
                                <template v-if="option.isTag">
                                    <span>{{ tagPlaceholder === 'Press enter to create a tag' ? __('Press enter to create a tag') : tagPlaceholder }}: <strong>{{ option[label] }}</strong></span>
                                </template>
                                <template v-else>
                                    <span>{{ typeof option === 'object' ? option[label] : option }}</span>
                                </template>
                            </slot>
                            <svg v-if="isSelected(option) && !option.isTag" class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </template>
                    <div v-else class="custom-select__no-results">
                        <slot name="noResult">
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">{{ __('No results found') }}</p>
                        </slot>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.custom-select {
    position: relative;
    width: 100%;
}

.custom-select--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.custom-select__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 42px;
    padding: 0.5rem 0.75rem;
    padding-inline-end: 2.5rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    background-color: white;
    cursor: pointer;
    transition: all 0.15s ease;
}

.dark .custom-select__trigger {
    border-color: rgb(55 65 81);
    background-color: rgb(17 24 39);
}

.custom-select__trigger:hover:not(.custom-select--disabled .custom-select__trigger) {
    border-color: rgb(156 163 175);
}

.dark .custom-select__trigger:hover:not(.custom-select--disabled .custom-select__trigger) {
    border-color: rgb(75 85 99);
}

.custom-select__trigger--open {
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.dark .custom-select__trigger--open {
    border-color: rgb(52 211 153);
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1);
}

.custom-select__trigger:focus {
    outline: none;
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.dark .custom-select__trigger:focus {
    border-color: rgb(52 211 153);
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1);
}

.custom-select__value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.custom-select__selected {
    color: rgb(17 24 39);
    font-size: 0.875rem;
}

.dark .custom-select__selected {
    color: rgb(243 244 246);
}

.custom-select__placeholder {
    color: rgb(156 163 175);
    font-size: 0.875rem;
}

.dark .custom-select__placeholder {
    color: rgb(107 114 128);
}

.custom-select__arrow {
    position: absolute;
    inset-inline-end: 0.75rem;
    display: flex;
    align-items: center;
    color: rgb(156 163 175);
    transition: transform 0.2s ease;
    pointer-events: none;
}

.dark .custom-select__arrow {
    color: rgb(107 114 128);
}

.custom-select__arrow--open {
    transform: rotate(180deg);
}

.custom-select__dropdown {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    z-index: 9999;
    background-color: white;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    max-height: 300px;
    overflow: hidden;
}

.dark .custom-select__dropdown {
    background-color: rgb(17 24 39);
    border-color: rgb(55 65 81);
}

.custom-select__search {
    padding: 0.5rem;
    border-bottom: 1px solid rgb(229 231 235);
}

.dark .custom-select__search {
    border-bottom-color: rgb(55 65 81);
}

.custom-select__search-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background-color: transparent;
    color: rgb(17 24 39);
}

.dark .custom-select__search-input {
    border-color: rgb(55 65 81);
    color: rgb(243 244 246);
}

.custom-select__search-input:focus {
    outline: none;
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
}

.dark .custom-select__search-input:focus {
    border-color: rgb(52 211 153);
    box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.1);
}

.custom-select__search-input::placeholder {
    color: rgb(156 163 175);
}

.dark .custom-select__search-input::placeholder {
    color: rgb(107 114 128);
}

.custom-select__options {
    max-height: 200px;
    overflow-y: auto;
}

.custom-select__option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: rgb(17 24 39);
    cursor: pointer;
    transition: background-color 0.1s ease;
}

.dark .custom-select__option {
    color: rgb(243 244 246);
}

.custom-select__option:hover,
.custom-select__option--highlighted {
    background-color: rgb(249 250 251);
    color: rgb(16 185 129);
}

.dark .custom-select__option:hover,
.dark .custom-select__option--highlighted {
    background-color: rgb(31 41 55);
    color: rgb(52 211 153);
}

.custom-select__option--tag {
    color: rgb(16 185 129);
    font-weight: 500;
}

.dark .custom-select__option--tag {
    color: rgb(52 211 153);
}

.custom-select__option--selected {
    background-color: rgb(236 253 245);
    color: rgb(5 150 105);
    font-weight: 600;
}

.dark .custom-select__option--selected {
    background-color: rgba(6, 78, 59, 0.4);
    color: rgb(52 211 153);
    font-weight: 600;
}

.custom-select__no-results {
    padding: 1rem;
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-enter-from {
    opacity: 0;
    transform: translateY(-4px);
}

.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
