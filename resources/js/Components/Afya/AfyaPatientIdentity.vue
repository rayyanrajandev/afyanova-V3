<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    patient: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
});

const initials = computed(() => {
    const fn = props.patient?.first_name?.[0] || '';
    const ln = props.patient?.last_name?.[0] || '';
    return `${fn}${ln}`.toUpperCase() || 'PT';
});

const genderCode = computed(() => {
    const g = props.patient?.gender?.toLowerCase() || '';
    if (g.startsWith('m')) return 'M';
    if (g.startsWith('f')) return 'F';
    return props.patient?.gender || '—';
});
</script>

<template>
    <div
        :class="cn(
            'p-2 bg-card rounded border border-border/80 text-xs space-y-1.5 shadow-2xs',
            props.class
        )"
    >
        <!-- Primary Row: Avatar + Name (Left) & Status Badge / Slot (Right) -->
        <div class="flex items-center justify-between gap-1.5 min-w-0 w-full">
            <div class="flex items-center space-x-1.5 min-w-0 flex-1 overflow-hidden">
                <!-- Initials Avatar -->
                <div
                    class="w-6 h-6 rounded bg-primary/10 text-primary font-bold flex items-center justify-center text-[10px] flex-shrink-0 border border-primary/20"
                    aria-hidden="true"
                >
                    {{ initials }}
                </div>

                <!-- Full Name -->
                <span class="font-bold text-foreground truncate text-xs block min-w-0">
                    {{ patient.first_name }} {{ patient.last_name }}
                </span>
            </div>

            <!-- Optional Status Badge or Action Slot -->
            <div v-if="$slots.default" class="flex-shrink-0">
                <slot />
            </div>
        </div>

        <!-- Secondary Metadata Row: MRN (Left) & Demographics (Right) -->
        <div class="flex items-center justify-between text-[10px] text-muted-foreground pt-1 border-t border-border/50">
            <span class="bg-muted px-1.5 py-0.2 rounded border border-border/60 text-foreground font-mono font-semibold truncate max-w-[130px]">
                {{ patient.primary_mrn || patient.mrn }}
            </span>
            <div class="flex items-center space-x-1.5 flex-shrink-0 text-[10px]">
                <span class="font-medium">{{ patient.age || '—' }}y ({{ genderCode }})</span>
                <span class="text-border">·</span>
                <span class="font-semibold text-rose-700">Blood: {{ patient.blood_group || 'O+' }}</span>
            </div>
        </div>
    </div>
</template>
