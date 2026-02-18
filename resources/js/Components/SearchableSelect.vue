<template>
    <Combobox
        v-model="selected"
        nullable
        :by="(a, b) => (a?.id ?? null) === (b?.id ?? null)"
        as="div"
        class="relative"
        @update:modelValue="onSelect"
        v-slot="{ open }"
    >
        <div class="relative" :data-dropdown-open="open">
            <div
                class="relative flex items-center rounded-lg border border-slate-200 bg-white text-left focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-400/50 transition-colors"
            >
                <ComboboxInput
                    :display-value="(item) => getDisplayValue(item)"
                    :placeholder="placeholder"
                    class="flex-1 min-w-0 border-0 py-2 pl-3 pr-2 text-sm text-slate-900 placeholder-slate-400 focus:ring-0 focus:outline-none rounded-l-lg"
                    autocomplete="off"
                    @change="query = $event.target.value"
                    @focus="emit('focus')"
                />
                <ComboboxButton
                    type="button"
                    class="flex-shrink-0 flex items-center py-2 pr-3 pl-1 text-slate-400"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                    </svg>
                </ComboboxButton>
            </div>
            <TransitionRoot
                leave="transition ease-in duration-100"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <ComboboxOptions
                    :class="['absolute mt-1 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg focus:outline-none', optionsMaxHeightClass]"
                    style="z-index: 2147483647"
                >
                    <ComboboxOption
                        v-for="option in filteredOptions"
                        :key="option.id"
                        :value="option"
                        as="template"
                        v-slot="{ active, selected: isSelected }"
                    >
                        <li
                            :class="[
                                'relative cursor-pointer select-none py-2 pl-3 pr-9',
                                active ? 'bg-slate-100 text-slate-900' : 'text-slate-700',
                            ]"
                        >
                            <span :class="['block truncate', isSelected ? 'font-medium' : 'font-normal']">
                                {{ option.name }}
                            </span>
                            <span
                                v-if="isSelected"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-600"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </li>
                    </ComboboxOption>
                    <li
                        v-if="loading"
                        class="py-2 pl-3 pr-9 text-slate-500 flex items-center gap-2"
                    >
                        <svg class="animate-spin h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        Loading...
                    </li>
                    <li
                        v-else-if="filteredOptions.length === 0"
                        class="py-2 pl-3 pr-9 text-slate-500"
                    >
                        No results found.
                    </li>
                </ComboboxOptions>
            </TransitionRoot>
        </div>
    </Combobox>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
} from '@headlessui/vue';
import { TransitionRoot } from '@headlessui/vue';

const props = defineProps({
    modelValue: {
        type: [Object, null],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    fetchOptions: {
        type: Function,
        default: null,
    },
    fetchDebounceMs: {
        type: Number,
        default: 250,
    },
    placeholder: {
        type: String,
        default: 'Select...',
    },
    optionsMaxHeightClass: {
        type: String,
        default: 'max-h-60',
    },
    /** When true, the first option is always shown in the list even when the user's search would filter it out (e.g. for "+ Add new ..." actions). */
    keepFirstOptionInFilter: {
        type: Boolean,
        default: false,
    },
    /** If the selected option's id matches this value, the input is shown empty (e.g. for "+ Add new UOM" action-only options). */
    optionIdToHideFromInput: {
        type: [String, Number],
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'select', 'focus']);

const selected = ref(props.modelValue);
const query = ref('');
const asyncOptions = ref([]);
const loading = ref(false);
let fetchTimeout = null;

watch(() => props.modelValue, (val) => {
    selected.value = val;
}, { immediate: true });

function runFetchOptions() {
    if (!props.fetchOptions || typeof props.fetchOptions !== 'function') return;
    const q = query.value.trim();
    loading.value = true;
    asyncOptions.value = [];
    props.fetchOptions(q)
        .then((items) => {
            asyncOptions.value = Array.isArray(items) ? items : [];
        })
        .catch(() => {
            asyncOptions.value = [];
        })
        .finally(() => {
            loading.value = false;
        });
}

watch(query, (val) => {
    if (!props.fetchOptions) return;
    if (fetchTimeout) clearTimeout(fetchTimeout);
    if (!val.trim()) {
        asyncOptions.value = [];
        return;
    }
    fetchTimeout = setTimeout(runFetchOptions, props.fetchDebounceMs);
}, { immediate: false });

const effectiveOptions = computed(() => {
    if (props.fetchOptions) return asyncOptions.value;
    return props.options;
});

const filteredOptions = computed(() => {
    if (props.fetchOptions) return effectiveOptions.value;
    const options = props.options;
    if (!query.value.trim()) return options;
    const q = query.value.toLowerCase();
    const filtered = options.filter((opt) => (opt.name || '').toLowerCase().includes(q));
    if (props.keepFirstOptionInFilter && options.length > 0) {
        const first = options[0];
        const rest = filtered.filter((o) => (o.id ?? o.name) !== (first.id ?? first.name));
        return [first, ...rest];
    }
    return filtered;
});

function getDisplayValue(item) {
    if (!item) return '';
    if (props.optionIdToHideFromInput != null && (item.id ?? item.name) === props.optionIdToHideFromInput) return '';
    return (item.name ?? item.id ?? '') || '';
}

function onSelect(value) {
    selected.value = value;
    query.value = '';
    emit('update:modelValue', value);
    emit('select', value);
}
</script>
