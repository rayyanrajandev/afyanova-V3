<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-vue-next';

const selectVariants = cva(
    'flex w-full appearance-none rounded-md border border-slate-300 dark:border-border/80 bg-white dark:bg-card pl-2.5 pr-8 text-slate-900 dark:text-foreground shadow-2xs transition-all placeholder:text-muted-foreground hover:border-slate-400 dark:hover:border-border focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer font-medium select-none',
    {
        variants: {
            size: {
                sm: 'h-7 text-[11px] py-0.5 rounded',
                default: 'h-8 text-xs py-1 rounded-md',
                lg: 'h-9 text-xs py-1.5 rounded-md',
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
            <option v-if="placeholder" value="" disabled :selected="!modelValue">
                {{ placeholder }}
            </option>

            <template v-if="options && options.length > 0">
                <option
                    v-for="opt in formattedOptions"
                    :key="opt.value"
                    :value="opt.value"
                    :disabled="opt.disabled"
                >
                    {{ opt.label }}
                </option>
            </template>
            <template v-else>
                <slot />
            </template>
        </select>

        <!-- Custom Dropdown Arrow -->
        <div class="pointer-events-none absolute right-2.5 flex items-center text-muted-foreground">
            <ChevronDown class="w-3.5 h-3.5 opacity-70" />
        </div>
    </div>
</template>
