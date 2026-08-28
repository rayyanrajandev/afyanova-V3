<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        required: true,
    },
    options: {
        type: Array,
        required: true,
    },
    size: {
        type: String,
        default: 'default',
    },
    class: {
        type: String,
        default: '',
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const normalizedOptions = computed(() => {
    return props.options.map(opt => {
        if (typeof opt === 'object' && opt !== null) {
            return opt;
        }
        return { value: opt, label: String(opt) };
    });
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return {
                container: 'p-0.5 rounded-lg text-[10.5px]',
                button: 'py-1 px-2 rounded-md gap-1',
                icon: 'w-3 h-3',
            };
        case 'lg':
            return {
                container: 'p-1 rounded-xl text-xs',
                button: 'py-2 px-3.5 rounded-lg gap-2',
                icon: 'w-4 h-4',
            };
        default:
            return {
                container: 'p-0.5 rounded-lg text-xs',
                button: 'py-1.5 px-2.5 rounded-md gap-1.5',
                icon: 'w-3.5 h-3.5',
            };
    }
});

const selectOption = (opt) => {
    if (opt.disabled) return;
    emit('update:modelValue', opt.value);
    emit('change', opt.value);
};
</script>

<template>
    <div
        :class="cn(
            'inline-flex items-center bg-muted/40 border border-border/40 select-none shadow-2xs transition-all',
            fullWidth ? 'w-full grid' : '',
            sizeClasses.container,
            props.class
        )"
        :style="fullWidth ? { gridTemplateColumns: `repeat(${normalizedOptions.length}, minmax(0, 1fr))` } : {}"
        role="tablist"
    >
        <button
            v-for="opt in normalizedOptions"
            :key="String(opt.value)"
            type="button"
            role="tab"
            :aria-selected="modelValue === opt.value"
            :disabled="opt.disabled"
            :class="cn(
                'inline-flex items-center justify-center font-semibold transition-all whitespace-nowrap',
                sizeClasses.button,
                modelValue === opt.value
                    ? 'bg-background text-foreground shadow-2xs ring-1 ring-border/40 font-bold'
                    : 'text-muted-foreground hover:text-foreground hover:bg-background/40',
                opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
            )"
            @click="selectOption(opt)"
        >
            <component
                :is="opt.icon"
                v-if="opt.icon"
                :class="cn('shrink-0', sizeClasses.icon, opt.iconColor)"
            />
            <span class="truncate">{{ opt.label }}</span>
            <span
                v-if="opt.badge !== undefined"
                :class="cn(
                    'ml-1 px-1.5 py-0.2 rounded-full text-[9.5px] font-mono leading-none',
                    modelValue === opt.value
                        ? 'bg-primary/10 text-primary font-bold'
                        : 'bg-muted text-muted-foreground'
                )"
            >
                {{ opt.badge }}
            </span>
        </button>
    </div>
</template>
