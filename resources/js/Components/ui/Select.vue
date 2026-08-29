<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-vue-next';

const selectVariants = cva(
    'block w-full appearance-none rounded-md border border-slate-300 dark:border-border/80 bg-white dark:bg-card text-slate-900 dark:text-foreground shadow-2xs transition-all placeholder:text-muted-foreground hover:border-slate-400 dark:hover:border-border focus:outline-hidden focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer font-medium truncate',
    {
        variants: {
            size: {
                xs: 'h-6 text-[10.5px] pl-2 pr-7 rounded leading-tight',
                sm: 'h-7 text-xs pl-2.5 pr-8 rounded leading-normal',
                default: 'h-8 text-xs pl-2.5 pr-8 rounded-md leading-normal',
                lg: 'h-9 text-sm pl-3 pr-9 rounded-md leading-normal',
            },
        },
        defaultVariants: {
            size: 'default',
        },
    }
);

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, Object],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'default',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const formattedOptions = computed(() => {
    return props.options.map(opt => {
        if (typeof opt === 'object' && opt !== null) {
            return {
                label: opt.label ?? opt.name ?? opt.text ?? String(opt.value ?? opt.id),
                value: opt.value ?? opt.id ?? opt.name,
                disabled: !!opt.disabled,
            };
        }
        return {
            label: String(opt),
            value: opt,
            disabled: false,
        };
    });
});

const onChange = (event) => {
    const val = event.target.value;
    emit('update:modelValue', val);
    emit('change', val);
};
</script>

<template>
    <div class="relative inline-flex items-center w-full">
        <select
            :value="modelValue"
            :disabled="disabled"
            :class="cn(
                selectVariants({ size }),
                error && 'border-destructive focus:border-destructive focus:ring-destructive/20',
                props.class
            )"
            @change="onChange"
        >
            <option v-if="placeholder" value="" disabled :selected="!modelValue" class="bg-card text-muted-foreground py-1 px-2">
                {{ placeholder }}
            </option>

            <template v-if="options && options.length > 0">
                <option
                    v-for="opt in formattedOptions"
                    :key="opt.value"
                    :value="opt.value"
                    :disabled="opt.disabled"
                    class="bg-card text-foreground py-1 px-2"
                >
                    {{ opt.label }}
                </option>
            </template>
            <template v-else>
                <slot />
            </template>
        </select>

        <!-- Custom Dropdown Arrow -->
        <div class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground">
            <ChevronDown class="w-3.5 h-3.5 opacity-70" />
        </div>
    </div>
</template>
