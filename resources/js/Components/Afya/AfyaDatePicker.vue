<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { cn } from '@/lib/utils';
import { 
    Calendar as CalendarIcon, 
    Clock, 
    ChevronLeft, 
    ChevronRight, 
    X, 
    ChevronDown 
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [String, Number, Date],
        default: '',
    },
    id: {
        type: String,
        default: () => `dp-${Math.random().toString(36).substring(2, 9)}`,
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    withTime: {
        type: Boolean,
        default: false,
    },
    min: {
        type: [String, Date],
        default: null,
    },
    max: {
        type: [String, Date],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    error: {
        type: [String, Boolean],
        default: null,
    },
    hint: {
        type: String,
        default: '',
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

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const containerRef = ref(null);
const yearsScrollRef = ref(null);
const openUpward = ref(false);

// Active selector mode: 'days' | 'months' | 'years'
const activePicker = ref('days');

// Internal selected date representation
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth());
const selectedDay = ref(new Date().getDate());
const selectedHours = ref('09');
const selectedMinutes = ref('00');
const hasSelection = ref(false);

// Active view navigation date (which month/year is shown in calendar)
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const shortMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const dayHeaders = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

// Parse incoming modelValue robustly (avoiding timezone shift issues)
const parseIncomingValue = (val) => {
    if (!val) {
        hasSelection.value = false;
        return;
    }

    const str = String(val).trim();
    // Matches YYYY-MM-DD, YYYY-MM-DDTHH:mm, YYYY-MM-DD HH:mm:ss, etc.
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2}))?/);

    if (match) {
        selectedYear.value = parseInt(match[1], 10);
        selectedMonth.value = parseInt(match[2], 10) - 1;
        selectedDay.value = parseInt(match[3], 10);
        selectedHours.value = match[4] ? String(match[4]).padStart(2, '0') : '09';
        selectedMinutes.value = match[5] ? String(match[5]).padStart(2, '0') : '00';
        
        viewYear.value = selectedYear.value;
        viewMonth.value = selectedMonth.value;
        hasSelection.value = true;
    } else {
        const d = new Date(str);
        if (!isNaN(d.getTime())) {
            selectedYear.value = d.getFullYear();
            selectedMonth.value = d.getMonth();
            selectedDay.value = d.getDate();
            selectedHours.value = String(d.getHours()).padStart(2, '0');
            selectedMinutes.value = String(d.getMinutes()).padStart(2, '0');

            viewYear.value = selectedYear.value;
            viewMonth.value = selectedMonth.value;
            hasSelection.value = true;
        } else {
            hasSelection.value = false;
        }
    }
};

watch(() => props.modelValue, (newVal) => {
    parseIncomingValue(newVal);
}, { immediate: true });

// Formatted display text in the trigger box
const formattedDisplay = computed(() => {
    if (!hasSelection.value) return '';

    const day = String(selectedDay.value).padStart(2, '0');
    const month = shortMonthNames[selectedMonth.value] || '';
    const year = selectedYear.value;

    if (props.withTime) {
        return `${day} ${month} ${year}, ${selectedHours.value}:${selectedMinutes.value}`;
    }

    return `${day} ${month} ${year}`;
});

const defaultPlaceholder = computed(() => {
    if (props.placeholder) return props.placeholder;
    return props.withTime ? 'Select date & time...' : 'Select date...';
});

// Navigation handlers
const prevMonth = () => {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
};

const nextMonth = () => {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
};

// Toggle Month Picker
const toggleMonthPicker = () => {
    activePicker.value = activePicker.value === 'months' ? 'days' : 'months';
};

// Toggle Year Picker
const toggleYearPicker = async () => {
    activePicker.value = activePicker.value === 'years' ? 'days' : 'years';
    if (activePicker.value === 'years') {
        await nextTick();
        const activeBtn = document.getElementById(`afya-year-btn-${viewYear.value}`);
        if (activeBtn && yearsScrollRef.value) {
            activeBtn.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    }
};

const selectMonth = (idx) => {
    viewMonth.value = idx;
    selectedMonth.value = idx;
    activePicker.value = 'days';
    emitUpdate();
};

const selectYear = (yr) => {
    viewYear.value = yr;
    selectedYear.value = yr;
    activePicker.value = 'days';
    emitUpdate();
};

// Year Options (from 1920 to 2040)
const yearList = computed(() => {
    const years = [];
    const thisYear = new Date().getFullYear();
    for (let y = thisYear + 15; y >= 1920; y--) {
        years.push(y);
    }
    return years;
});

// Calendar grid matrix (42 cells)
const calendarDays = computed(() => {
    const year = viewYear.value;
    const month = viewMonth.value;

    const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; // Monday = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const days = [];

    // 1. Previous month trailing days
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const dateObj = new Date(year, month - 1, d);
        days.push({
            dayNumber: d,
            year: dateObj.getFullYear(),
            month: dateObj.getMonth(),
            isCurrentMonth: false,
            isSelected: hasSelection.value && isSelected(dateObj.getFullYear(), dateObj.getMonth(), d),
            isToday: isToday(dateObj.getFullYear(), dateObj.getMonth(), d),
            isDisabled: isDateDisabled(dateObj),
        });
    }

    // 2. Current month days
    for (let d = 1; d <= daysInMonth; d++) {
        const dateObj = new Date(year, month, d);
        days.push({
            dayNumber: d,
            year: year,
            month: month,
            isCurrentMonth: true,
            isSelected: hasSelection.value && isSelected(year, month, d),
            isToday: isToday(year, month, d),
            isDisabled: isDateDisabled(dateObj),
        });
    }

    // 3. Next month leading days (fill 42 cells)
    const remaining = 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        const dateObj = new Date(year, month + 1, d);
        days.push({
            dayNumber: d,
            year: dateObj.getFullYear(),
            month: dateObj.getMonth(),
            isCurrentMonth: false,
            isSelected: hasSelection.value && isSelected(dateObj.getFullYear(), dateObj.getMonth(), d),
            isToday: isToday(dateObj.getFullYear(), dateObj.getMonth(), d),
            isDisabled: isDateDisabled(dateObj),
        });
    }

    return days;
});

const isSelected = (y, m, d) => {
    return selectedYear.value === y && selectedMonth.value === m && selectedDay.value === d;
};

const isToday = (y, m, d) => {
    const now = new Date();
    return now.getFullYear() === y && now.getMonth() === m && now.getDate() === d;
};

const isDateDisabled = (d) => {
    if (props.min) {
        const minD = new Date(props.min);
        minD.setHours(0, 0, 0, 0);
        if (d < minD) return true;
    }
    if (props.max) {
        const maxD = new Date(props.max);
        maxD.setHours(23, 59, 59, 999);
        if (d > maxD) return true;
    }
    return false;
};

// Emit updated value to parent form
const emitUpdate = () => {
    const y = selectedYear.value;
    const m = String(selectedMonth.value + 1).padStart(2, '0');
    const d = String(selectedDay.value).padStart(2, '0');

    let output = '';
    if (props.withTime) {
        output = `${y}-${m}-${d}T${selectedHours.value}:${selectedMinutes.value}`;
    } else {
        output = `${y}-${m}-${d}`;
    }

    hasSelection.value = true;
    emit('update:modelValue', output);
    emit('change', output);
};

// Click Day
const selectDay = (cell) => {
    if (cell.isDisabled) return;

    selectedYear.value = cell.year;
    selectedMonth.value = cell.month;
    selectedDay.value = cell.dayNumber;

    emitUpdate();

    if (!props.withTime) {
        isOpen.value = false;
    }
};

const onTimeChange = () => {
    // Sanitize hours & minutes
    let h = parseInt(selectedHours.value, 10);
    let m = parseInt(selectedMinutes.value, 10);

    if (isNaN(h) || h < 0) h = 0;
    if (h > 23) h = 23;
    if (isNaN(m) || m < 0) m = 0;
    if (m > 59) m = 59;

    selectedHours.value = String(h).padStart(2, '0');
    selectedMinutes.value = String(m).padStart(2, '0');

    if (!hasSelection.value) {
        const now = new Date();
        selectedYear.value = now.getFullYear();
        selectedMonth.value = now.getMonth();
        selectedDay.value = now.getDate();
    }

    emitUpdate();
};

const setNow = () => {
    const now = new Date();
    selectedYear.value = now.getFullYear();
    selectedMonth.value = now.getMonth();
    selectedDay.value = now.getDate();
    selectedHours.value = String(now.getHours()).padStart(2, '0');
    selectedMinutes.value = String(now.getMinutes()).padStart(2, '0');
    viewYear.value = now.getFullYear();
    viewMonth.value = now.getMonth();
    activePicker.value = 'days';

    emitUpdate();
    isOpen.value = false;
};

const setToday = () => {
    const now = new Date();
    selectedYear.value = now.getFullYear();
    selectedMonth.value = now.getMonth();
    selectedDay.value = now.getDate();
    viewYear.value = now.getFullYear();
    viewMonth.value = now.getMonth();
    activePicker.value = 'days';

    emitUpdate();

    if (!props.withTime) {
        isOpen.value = false;
    }
};

const clearValue = (e) => {
    e.stopPropagation();
    hasSelection.value = false;
    emit('update:modelValue', '');
    emit('change', '');
    isOpen.value = false;
};

// Toggle popover & calculate placement
const toggleOpen = async () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        activePicker.value = 'days';
        await nextTick();
        if (containerRef.value) {
            const rect = containerRef.value.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            openUpward.value = spaceBelow < 340 && rect.top > 340;
        }
    }
};

// Outside click handling (uses composedPath to prevent premature closing when internal buttons unmount)
const handleClickOutside = (e) => {
    const path = e.composedPath ? e.composedPath() : [];
    if (containerRef.value && !path.includes(containerRef.value)) {
        isOpen.value = false;
        activePicker.value = 'days';
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="containerRef" :class="cn('relative w-full space-y-1', wrapperClass)">
        
        <!-- Label Header -->
        <div v-if="label || $slots.label" class="flex items-center justify-between">
            <label 
                :for="id" 
                class="block font-bold text-[10px] uppercase text-muted-foreground select-none"
            >
                <slot name="label">{{ label }}</slot>
                <span v-if="required" class="text-destructive ml-0.5">*</span>
            </label>
        </div>

        <!-- Input Trigger Box -->
        <div class="relative">
            <div
                :id="id"
                role="button"
                tabindex="0"
                :class="cn(
                    'flex h-8 w-full items-center justify-between rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs text-slate-900 dark:text-slate-100 shadow-2xs transition-all cursor-pointer select-none font-mono',
                    disabled ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-800' : 'hover:border-slate-400 dark:hover:border-slate-600 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20',
                    error ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : '',
                    isOpen ? 'border-primary ring-2 ring-primary/20' : '',
                    props.class
                )"
                @click="toggleOpen"
                @keydown.enter="toggleOpen"
                @keydown.space="toggleOpen"
            >
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <component 
                        :is="withTime ? Clock : CalendarIcon" 
                        class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" 
                    />
                    <span 
                        class="truncate" 
                        :class="hasSelection ? 'text-slate-900 dark:text-slate-100 font-semibold' : 'text-slate-400 dark:text-slate-500'"
                    >
                        {{ formattedDisplay || defaultPlaceholder }}
                    </span>
                </div>

                <!-- Clear / Chevron Controls -->
                <div class="flex items-center gap-1 shrink-0 ml-1 text-slate-400 dark:text-slate-500">
                    <button
                        v-if="hasSelection && !disabled"
                        type="button"
                        class="p-0.5 rounded hover:text-slate-700 dark:hover:text-slate-200 transition cursor-pointer"
                        title="Clear date"
                        @click="clearValue"
                    >
                        <X class="w-3 h-3" />
                    </button>
                    <ChevronDown class="w-3 h-3 transition-transform duration-150" :class="isOpen ? 'rotate-180 text-primary' : ''" />
                </div>
            </div>
        </div>

        <!-- Popover Calendar -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute z-[9999] w-[276px] rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl p-2.5 space-y-2 text-slate-900 dark:text-slate-100"
                :class="openUpward ? 'bottom-full mb-1' : 'top-full mt-1'"
            >
                <!-- Navigation Header with Month & Year Select Buttons -->
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="p-1 rounded text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:text-slate-100 transition cursor-pointer"
                        title="Previous Month"
                        @click="prevMonth"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <div class="flex items-center gap-1.5 font-bold text-xs">
                        <!-- Month Dropdown Trigger Button -->
                        <button
                            type="button"
                            class="flex items-center gap-1 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 transition cursor-pointer"
                            :class="activePicker === 'months' ? 'border-primary ring-1 ring-primary/20 text-primary' : ''"
                            @click="toggleMonthPicker"
                        >
                            <span>{{ monthNames[viewMonth] }}</span>
                            <ChevronDown class="w-3 h-3 text-slate-400" />
                        </button>

                        <!-- Year Dropdown Trigger Button -->
                        <button
                            type="button"
                            class="flex items-center gap-1 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-mono transition cursor-pointer"
                            :class="activePicker === 'years' ? 'border-primary ring-1 ring-primary/20 text-primary' : ''"
                            @click="toggleYearPicker"
                        >
                            <span>{{ viewYear }}</span>
                            <ChevronDown class="w-3 h-3 text-slate-400" />
                        </button>
                    </div>

                    <button
                        type="button"
                        class="p-1 rounded text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:text-slate-100 transition cursor-pointer"
                        title="Next Month"
                        @click="nextMonth"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <!-- 1. Month Picker Panel -->
                <div v-if="activePicker === 'months'" class="grid grid-cols-3 gap-1.5 py-1">
                    <button
                        v-for="(mName, idx) in shortMonthNames"
                        :key="mName"
                        type="button"
                        :class="cn(
                            'py-2 px-1 text-xs font-semibold rounded-md transition text-center cursor-pointer',
                            idx === viewMonth 
                                ? 'bg-primary text-white font-bold shadow-2xs' 
                                : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200'
                        )"
                        @click="selectMonth(idx)"
                    >
                        {{ mName }}
                    </button>
                </div>

                <!-- 2. Year Picker Panel -->
                <div 
                    v-else-if="activePicker === 'years'" 
                    ref="yearsScrollRef"
                    class="max-h-48 overflow-y-auto grid grid-cols-3 gap-1.5 p-1 pr-2"
                >
                    <button
                        v-for="yr in yearList"
                        :key="yr"
                        :id="`afya-year-btn-${yr}`"
                        type="button"
                        :class="cn(
                            'py-1.5 px-1 text-xs font-mono font-semibold rounded-md transition text-center cursor-pointer',
                            yr === viewYear 
                                ? 'bg-primary text-white font-bold shadow-2xs' 
                                : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200'
                        )"
                        @click="selectYear(yr)"
                    >
                        {{ yr }}
                    </button>
                </div>

                <!-- 3. Days View Matrix -->
                <div v-else class="space-y-1">
                    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">
                        <div v-for="d in dayHeaders" :key="d" class="py-0.5">
                            {{ d }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-0.5 text-center text-xs">
                        <button
                            v-for="(cell, idx) in calendarDays"
                            :key="idx"
                            type="button"
                            :disabled="cell.isDisabled"
                            :class="cn(
                                'h-7 w-7 mx-auto rounded flex items-center justify-center font-medium transition text-xs select-none cursor-pointer',
                                !cell.isCurrentMonth ? 'text-slate-300 dark:text-slate-600' : 'text-slate-800 dark:text-slate-200',
                                cell.isToday && !cell.isSelected ? 'border border-primary text-primary font-bold' : '',
                                cell.isSelected ? 'bg-primary text-white font-bold shadow-2xs' : 'hover:bg-slate-100 dark:hover:bg-slate-800',
                                cell.isDisabled ? 'opacity-20 cursor-not-allowed hover:bg-transparent' : ''
                            )"
                            @click="selectDay(cell)"
                        >
                            {{ cell.dayNumber }}
                        </button>
                    </div>
                </div>

                <!-- Time Stepper Section (when withTime is true) -->
                <div v-if="withTime && activePicker === 'days'" class="pt-1.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1">
                        <Clock class="w-3 h-3 text-primary" />
                        <span>Time</span>
                    </span>

                    <div class="flex items-center gap-1 font-mono">
                        <input
                            v-model="selectedHours"
                            type="number"
                            min="0"
                            max="23"
                            class="h-6 w-10 text-center text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded p-0 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-primary focus:border-primary"
                            @input="onTimeChange"
                            @change="onTimeChange"
                        />
                        <span class="text-slate-400 font-bold">:</span>
                        <input
                            v-model="selectedMinutes"
                            type="number"
                            min="0"
                            max="59"
                            step="5"
                            class="h-6 w-10 text-center text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded p-0 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-primary focus:border-primary"
                            @input="onTimeChange"
                            @change="onTimeChange"
                        />
                    </div>
                </div>

                <!-- Footer Quick Actions -->
                <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px]">
                    <div class="flex items-center gap-1">
                        <button
                            v-if="withTime"
                            type="button"
                            class="px-2 py-0.5 rounded text-[11px] font-bold bg-primary/10 text-primary hover:bg-primary/20 transition cursor-pointer"
                            title="Select current date and exact time"
                            @click="setNow"
                        >
                            Now
                        </button>
                        <button
                            type="button"
                            class="px-2 py-0.5 rounded text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                            @click="setToday"
                        >
                            Today
                        </button>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            v-if="hasSelection"
                            type="button"
                            class="px-1.5 py-0.5 rounded text-[10px] text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition cursor-pointer"
                            @click="clearValue"
                        >
                            Clear
                        </button>
                        <button
                            v-if="withTime"
                            type="button"
                            class="px-2.5 py-0.5 rounded text-[11px] font-semibold bg-primary text-white hover:bg-primary/90 transition shadow-2xs cursor-pointer"
                            @click="isOpen = false"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Hint or Error -->
        <div v-if="error || hint" class="text-[11px]">
            <p v-if="error && typeof error === 'string'" class="text-destructive">
                {{ error }}
            </p>
            <p v-else-if="hint" class="text-muted-foreground">
                {{ hint }}
            </p>
        </div>
    </div>
</template>
