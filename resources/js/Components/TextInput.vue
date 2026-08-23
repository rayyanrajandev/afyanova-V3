<script setup>
import { onMounted, ref } from 'vue';
import { cn } from '@/lib/utils';

const model = defineModel({
    type: [String, Number],
    required: true,
});

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
});

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus(), input });
</script>

<template>
    <input
        :class="cn(
            'block w-full rounded-md border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 shadow-2xs hover:border-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none text-xs transition-all px-3 py-2',
            props.class
        )"
        v-model="model"
        ref="input"
    />
</template>
