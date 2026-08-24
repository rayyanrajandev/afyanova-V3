<script setup>
import { ref, computed, onMounted } from 'vue';
import { cn } from '@/lib/utils';
import { Eye, EyeOff, AlertCircle } from '@lucide/vue';

const model = defineModel({
    type: [String, Number],
    default: '',
});

const props = defineProps({
    id: {
        type: String,
        default: () => `input-${Math.random().toString(36).substring(2, 9)}`,
    },
    type: {
        type: String,
        default: 'text',
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    icon: {
        type: [Object, Function],
        default: null,
    },
    trailingIcon: {
        type: [Object, Function],
        default: null,
    },
    error: {
        type: [String, Boolean],
        default: null,
    },
    hint: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    autofocus: {
        type: Boolean,
        default: false,
    },
    autocomplete: {
        type: String,
        default: undefined,
    },
    size: {
        type: String,
        default: 'default', // 'sm' | 'default' | 'lg'
    },
    allowPasswordToggle: {
        type: Boolean,
        default: true,
    },
    class: {
        type: String,
        default: '',
    },
    wrapperClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['click:trailing']);

const inputRef = ref(null);
const showPassword = ref(false);

const computedType = computed(() => {
    if (props.type === 'password' && props.allowPasswordToggle) {
        return showPassword.value ? 'text' : 'password';
    }
    return props.type;
});

const isPasswordType = computed(() => props.type === 'password' && props.allowPasswordToggle);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-7 text-xs px-2.5';
        case 'lg':
            return 'h-10 text-sm px-3.5';
        case 'default':
        default:
            return 'h-8.5 text-xs px-3';
    }
});

const paddingClasses = computed(() => {
    const hasLeading = !!props.icon;
    const hasTrailing = !!props.trailingIcon || isPasswordType.value;

    if (hasLeading && hasTrailing) {
        return 'pl-9 pr-9';
    }
    if (hasLeading) {
        return 'pl-9 pr-3';
    }
    if (hasTrailing) {
        return 'pl-3 pr-9';
    }
    return 'px-3';
});

onMounted(() => {
    if (props.autofocus && inputRef.value) {
        inputRef.value.focus();
    }
});

defineExpose({
    focus: () => inputRef.value?.focus(),
    select: () => inputRef.value?.select(),
    input: inputRef,
});
</script>

<template>
    <div :class="cn('w-full space-y-1', wrapperClass)">
        
        <!-- Label Header -->
        <div v-if="label || $slots.label" class="flex items-center justify-between">
            <label 
                :for="id" 
                class="block text-xs font-semibold text-foreground select-none"
            >
                <slot name="label">
                    {{ label }}
                    <span v-if="required" class="text-rose-500 font-bold ml-0.5">*</span>
                </slot>
            </label>
            <slot name="corner" />
        </div>

        <!-- Input Wrapper -->
        <div class="relative rounded-md flex items-center">
            
            <!-- Leading Icon -->
            <div 
                v-if="icon || $slots.leading" 
                class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500"
            >
                <slot name="leading">
                    <component :is="icon" class="w-4 h-4 flex-shrink-0" />
                </slot>
            </div>

            <!-- Native Input Element -->
            <input
                :id="id"
                ref="inputRef"
                :type="computedType"
                v-model="model"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="typeof error === 'string' && error ? 'true' : undefined"
                :aria-describedby="(typeof error === 'string' && error) || hint ? `${id}-description` : undefined"
                :class="cn(
                    'block w-full rounded-md border bg-white dark:bg-slate-900/90 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 shadow-2xs transition-all outline-none',
                    'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600',
                    'focus:border-primary focus:ring-2 focus:ring-primary/20',
                    sizeClasses,
                    paddingClasses,
                    disabled && 'bg-slate-50 dark:bg-slate-900 text-slate-400 cursor-not-allowed border-slate-200 dark:border-slate-800',
                    error && 'border-rose-500 text-rose-900 dark:text-rose-200 focus:border-rose-500 focus:ring-rose-500/20 placeholder:text-rose-300 dark:placeholder:text-rose-400/50',
                    props.class
                )"
            />

            <!-- Trailing Action / Password Toggle / Trailing Icon -->
            <div 
                v-if="isPasswordType || trailingIcon || $slots.trailing"
                class="absolute inset-y-0 right-0 pr-2.5 flex items-center"
            >
                <button
                    v-if="isPasswordType"
                    type="button"
                    tabindex="-1"
                    @click="showPassword = !showPassword"
                    class="text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none p-0.5 rounded transition-colors"
                >
                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                </button>
                <button
                    v-else-if="trailingIcon"
                    type="button"
                    @click="emit('click:trailing')"
                    class="text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none p-0.5 rounded transition-colors"
                >
                    <component :is="trailingIcon" class="w-4 h-4" />
                </button>
                <slot name="trailing" />
            </div>

        </div>

        <!-- Error Message or Hint -->
        <div v-if="typeof error === 'string' && error" :id="`${id}-description`" role="alert" class="flex items-center gap-1 text-[11px] font-medium text-rose-600 dark:text-rose-400 pt-0.5">
            <AlertCircle class="w-3.5 h-3.5 flex-shrink-0" />
            <span>{{ error }}</span>
        </div>
        <div v-else-if="hint" :id="`${id}-description`" class="text-[11px] text-muted-foreground pt-0.5">
            {{ hint }}
        </div>

    </div>
</template>
