<script setup>
import { computed } from 'vue';
import { 
    FlaskConical, 
    AlertTriangle, 
    CheckCircle2, 
    Clock, 
    ShieldAlert, 
    UserCheck,
    Barcode,
    FileText
} from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    canEnterResults: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['enterResults']);

const isCompleted = computed(() => props.item.status === 'Completed');
const isPending = computed(() => props.item.status === 'Pending' || props.item.status === 'Sample Collected');

const formatNumber = (num) => Number(num || 0).toLocaleString('en-US');

const getParameterStatus = (param, val) => {
    if (val === undefined || val === null || val === '') return 'pending';
    
    if (isNumeric(val)) {
        const numVal = parseFloat(val);
        if (param.panic_low !== null && param.panic_low !== undefined && numVal <= parseFloat(param.panic_low)) {
            return 'panic';
        }
        if (param.panic_high !== null && param.panic_high !== undefined && numVal >= parseFloat(param.panic_high)) {
            return 'panic';
        }
        if (param.min !== null && param.min !== undefined && numVal < parseFloat(param.min)) {
            return 'low';
        }
        if (param.max !== null && param.max !== undefined && numVal > parseFloat(param.max)) {
            return 'high';
        }
        return 'normal';
    } else {
        if (param.critical_value && String(val).toLowerCase().includes('positive')) {
            return 'panic';
        }
        return 'normal';
    }
};

const isNumeric = (val) => !isNaN(parseFloat(val)) && isFinite(val);
</script>

<template>
    <div 
        class="rounded-lg border transition-all overflow-hidden bg-card text-card-foreground shadow-2xs"
        :class="[
            item.has_critical_value 
                ? 'border-rose-500/80 dark:border-rose-500/60 bg-rose-500/5' 
                : 'border-border/70 hover:border-border'
        ]"
    >
        <!-- Header Strip -->
        <div class="p-3 bg-muted/40 border-b border-border/50 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div 
                    class="w-7 h-7 rounded-md flex items-center justify-center shrink-0"
                    :class="item.has_critical_value ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-primary/10 text-primary'"
                >
                    <FlaskConical class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-foreground truncate">
                            {{ item.lab_test?.name || 'Diagnostic Investigation' }}
                        </span>
                        <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-muted font-semibold text-muted-foreground">
                            {{ item.lab_test?.test_code }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] text-muted-foreground mt-0.5">
                        <span>{{ item.lab_test?.category }}</span>
                        <span>·</span>
                        <span>Specimen: {{ item.lab_test?.specimen_type }}</span>
                        <span v-if="item.specimen_barcode" class="font-mono flex items-center gap-0.5 text-primary/80">
                            <Barcode class="w-3 h-3 inline" /> {{ item.specimen_barcode }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Status Pill & Actions -->
            <div class="flex items-center gap-2">
                <!-- Critical Panic Alert Banner Pill -->
                <span 
                    v-if="item.has_critical_value"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-rose-600 text-white animate-pulse shadow-xs tracking-wide uppercase"
                >
                    <ShieldAlert class="w-3 h-3" />
                    <span>CRITICAL PANIC VALUE</span>
                </span>

                <!-- Completed Status -->
                <span 
                    v-else-if="item.status === 'Completed'"
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"
                >
                    <CheckCircle2 class="w-3 h-3" />
                    <span>Completed & Verified</span>
                </span>

                <!-- In Progress / Testing on Analyzer -->
                <span 
                    v-else-if="item.status === 'Testing' || item.status === 'Sample Collected' || item.status === 'In Progress'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/20"
                >
                    <FlaskConical class="w-3 h-3 text-sky-600 dark:text-sky-400 animate-pulse" />
                    <span>In Testing on Analyzer</span>
                </span>

                <!-- Awaiting Specimen Collection in Lab -->
                <span 
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20"
                >
                    <Clock class="w-3 h-3" />
                    <span>Awaiting Specimen (Lab)</span>
                </span>

                <!-- Only show Enter POC Result if explicitly permitted -->
                <Button 
                    v-if="!isCompleted && canEnterResults"
                    variant="outline" 
                    size="sm" 
                    class="h-7 px-2.5 text-[11px] font-semibold gap-1 text-primary border-primary/30 hover:bg-primary/10"
                    @click="$emit('enterResults', item)"
                >
                    <span>Enter POC Result</span>
                </Button>
            </div>
        </div>

        <!-- Panic Alert Warning Callout -->
        <div v-if="item.has_critical_value" class="px-3 py-2 bg-rose-500/10 border-b border-rose-500/20 flex items-center gap-2 text-rose-700 dark:text-rose-300 text-xs">
            <AlertTriangle class="w-4 h-4 shrink-0 text-rose-600 dark:text-rose-400" />
            <div class="leading-tight text-[11px]">
                <strong class="font-bold">Life-Threatening Value Alert:</strong> One or more test parameters are outside critical safety thresholds. Prompt clinical intervention is indicated.
            </div>
        </div>

        <!-- Results Parameters Table (If Results Available) -->
        <div v-if="item.results && Object.keys(item.results).length > 0" class="p-0 overflow-x-auto">
            <Table class="text-xs">
                <TableHeader>
                    <TableRow class="bg-muted/20 text-[10px] uppercase font-bold text-muted-foreground">
                        <TableHead class="py-1.5 px-3">Parameter</TableHead>
                        <TableHead class="py-1.5 px-3">Result Finding</TableHead>
                        <TableHead class="py-1.5 px-3">Ref. Range / Normal</TableHead>
                        <TableHead class="py-1.5 px-3">Panic Thresholds</TableHead>
                        <TableHead class="py-1.5 px-3 text-right">Evaluation</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow 
                        v-for="param in (item.lab_test?.parameters || [])" 
                        :key="param.name"
                        class="hover:bg-muted/10 transition-colors"
                        :class="{ 'bg-rose-500/10 font-bold': getParameterStatus(param, item.results[param.name]) === 'panic' }"
                    >
                        <!-- Parameter Name -->
                        <TableCell class="py-2 px-3 font-medium text-foreground">
                            {{ param.name }}
                        </TableCell>

                        <!-- Result Finding -->
                        <TableCell class="py-2 px-3 font-mono font-bold">
                            <span 
                                :class="[
                                    getParameterStatus(param, item.results[param.name]) === 'panic'
                                        ? 'text-rose-600 dark:text-rose-400 text-sm'
                                        : (getParameterStatus(param, item.results[param.name]) === 'high' || getParameterStatus(param, item.results[param.name]) === 'low'
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-foreground')
                                ]"
                            >
                                {{ item.results[param.name] ?? '—' }}
                                <span v-if="param.unit" class="text-[10px] font-normal text-muted-foreground ml-1">{{ param.unit }}</span>
                            </span>
                        </TableCell>

                        <!-- Reference Range -->
                        <TableCell class="py-2 px-3 font-mono text-[11px] text-muted-foreground">
                            <span v-if="param.min !== undefined && param.max !== undefined">
                                {{ param.min }} – {{ param.max }} {{ param.unit }}
                            </span>
                            <span v-else-if="param.normal">
                                {{ param.normal }}
                            </span>
                            <span v-else>—</span>
                        </TableCell>

                        <!-- Panic Thresholds -->
                        <TableCell class="py-2 px-3 font-mono text-[10px] text-rose-600/80 dark:text-rose-400/80">
                            <span v-if="param.panic_low || param.panic_high">
                                <span v-if="param.panic_low">&lt; {{ param.panic_low }}</span>
                                <span v-if="param.panic_low && param.panic_high"> or </span>
                                <span v-if="param.panic_high">&gt; {{ param.panic_high }}</span>
                            </span>
                            <span v-else-if="param.critical_value">
                                {{ param.critical_value }}
                            </span>
                            <span v-else class="text-muted-foreground/40">—</span>
                        </TableCell>

                        <!-- Evaluation Status Badge -->
                        <TableCell class="py-2 px-3 text-right">
                            <span 
                                v-if="getParameterStatus(param, item.results[param.name]) === 'panic'"
                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white"
                            >
                                CRITICAL
                            </span>
                            <span 
                                v-else-if="getParameterStatus(param, item.results[param.name]) === 'high'"
                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400"
                            >
                                HIGH ▲
                            </span>
                            <span 
                                v-else-if="getParameterStatus(param, item.results[param.name]) === 'low'"
                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400"
                            >
                                LOW ▼
                            </span>
                            <span 
                                v-else-if="getParameterStatus(param, item.results[param.name]) === 'normal'"
                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400"
                            >
                                NORMAL
                            </span>
                            <span v-else class="text-[10px] text-muted-foreground">Pending</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Waiting / Specimen Pending State -->
        <div v-else class="p-4 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-1.5 bg-muted/10">
            <Clock class="w-4 h-4 text-muted-foreground/60 animate-spin" />
            <p>Specimen processing at Diagnostic Pathology Bench. Results will stream automatically.</p>
        </div>

        <!-- Remarks and Sign-off Footer -->
        <div v-if="item.technician_remarks || item.performed_by" class="p-2.5 bg-muted/30 border-t border-border/50 flex flex-wrap items-center justify-between gap-2 text-[10px] text-muted-foreground">
            <div v-if="item.technician_remarks" class="flex items-center gap-1.5 min-w-0">
                <FileText class="w-3.5 h-3.5 text-primary shrink-0" />
                <span class="font-medium text-foreground">Technician Remarks:</span>
                <span class="italic truncate">{{ item.technician_remarks }}</span>
            </div>
            <div v-if="item.performed_by" class="flex items-center gap-1 font-mono text-[10px] ml-auto">
                <UserCheck class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                <span>Verified by {{ item.performed_by.name }}</span>
            </div>
        </div>
    </div>
</template>
