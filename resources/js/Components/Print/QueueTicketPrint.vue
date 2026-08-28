<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Printer, X, Ticket } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    facilityName: {
        type: String,
        default: 'AfyaNova Health System',
    },
});

const emit = defineEmits(['close']);

const qrCodeDataUrl = ref('');

const rawStation = computed(() => {
    return props.ticket.current_service_point || props.ticket.service_point || 'Triage';
});

const servicePointInfo = computed(() => {
    const sp = rawStation.value;
    const encType = props.ticket.encounter?.encounter_type;

    switch (sp) {
        case 'Triage':
            return {
                title: 'Triage & Consultation',
                swahili: 'Mapokezi & Vipimo vya Awali',
                desk: 'Triage Desk (Vipimo vya Awali)',
            };
        case 'Doctor':
            return {
                title: 'Doctor Consultation',
                swahili: 'Chumba cha Daktari (OPD)',
                desk: 'Doctor Room (Chumba cha Daktari)',
            };
        case 'Procedure':
            if (encType === 'Treatment_Followup' || encType === 'Injection Revisit') {
                return {
                    title: 'Treatment Follow-Up',
                    swahili: 'Chumba cha Sindano (Day 2–5)',
                    desk: 'Chumba cha Sindano na Vidonda',
                };
            }
            return {
                title: 'Injection & Dressing',
                swahili: 'Chumba cha Sindano na Vidonda',
                desk: 'Procedure Room (Chumba cha Sindano)',
            };
        case 'Lab':
            return {
                title: 'Laboratory & Phlebotomy',
                swahili: 'Maabara (Vipimo & Damu)',
                desk: 'Laboratory Desk (Maabara)',
            };
        case 'Pharmacy':
            return {
                title: 'Pharmacy Dispensary',
                swahili: 'Duka la Dawa (Pharmacy)',
                desk: 'Pharmacy Window (Dirisha la Dawa)',
            };
        case 'Cashier':
            return {
                title: 'Cashier & Billing',
                swahili: 'Mapokezi & Malipo ya Bili',
                desk: 'Cashier Window (Dirisha la Malipo)',
            };
        default:
            return {
                title: sp,
                swahili: sp + ' Desk',
                desk: sp,
            };
    }
});

const serviceSummary = computed(() => {
    const enc = props.ticket.encounter;
    if (!enc) return null;

    if (enc.lab_orders?.[0]?.items?.length) {
        const names = enc.lab_orders[0].items.map(i => i.lab_test?.name || 'Lab Test').join(', ');
        return names.length > 35 ? names.substring(0, 32) + '...' : names;
    }
    if (enc.procedure_orders?.[0]?.catalog?.name) {
        return enc.procedure_orders[0].catalog.name;
    }
    if (enc.encounter_type === 'Treatment_Followup' || enc.encounter_type === 'Injection Revisit') {
        return 'Multi-Day Course Follow-Up (Prepaid)';
    }
    if (enc.encounter_type === 'Pharmacy_OTC') {
        return 'OTC Direct Pharmacy Dispensing';
    }
    if (enc.reason_for_visit) {
        return enc.reason_for_visit;
    }
    return null;
});

const paymentMode = computed(() => {
    return props.ticket.encounter?.billing_type || 
           props.ticket.encounter?.payment_mode || 
           props.ticket.payment_mode || 
           'Cash';
});

const generateQr = async () => {
    try {
        const payload = `AFYANOVA-TICKET:${props.ticket.ticket_number || props.ticket.id}|MRN:${props.ticket.patient?.primary_mrn}|STATION:${rawStation.value}|TIME:${props.ticket.created_at || new Date().toISOString()}`;
        qrCodeDataUrl.value = await QRCode.toDataURL(payload, {
            width: 90,
            margin: 1,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Failed to generate QR code for queue ticket', err);
    }
};

onMounted(() => {
    generateQr();
});

watch(() => props.ticket, () => {
    generateQr();
});

const printTicket = () => {
    window.print();
};

const formatTime = (dateStr) => {
    if (!dateStr) return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? dateStr : d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};

const formatDate = (dateStr) => {
    if (!dateStr) return new Date().toLocaleDateString('en-GB');
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 print:p-0 print:bg-white print:static print:inset-auto">
        <div class="bg-card text-card-foreground rounded-xl shadow-2xl border border-border max-w-xs w-full p-4 print:p-0 print:border-none print:shadow-none print:max-w-none print:w-[80mm] space-y-4">
            
            <!-- Modal Header (Hidden during actual print) -->
            <div class="flex items-center justify-between pb-2 border-b border-border print:hidden">
                <div class="flex items-center gap-2">
                    <Ticket class="w-4 h-4 text-primary" />
                    <h3 class="font-bold text-xs text-foreground">Queue Paper Ticket</h3>
                </div>
                <div class="flex items-center gap-1">
                    <Button variant="default" size="sm" class="h-7 text-xs gap-1.5" @click="printTicket">
                        <Printer class="w-3 h-3" />
                        <span>Print Ticket</span>
                    </Button>
                    <button class="p-1 text-muted-foreground hover:text-foreground rounded-md" @click="emit('close')">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Thermal Printable Paper Area (80mm Width optimized) -->
            <div id="queue-ticket-paper" class="bg-white text-black font-mono text-[11px] leading-tight p-3 rounded border border-slate-200 print:border-none print:p-0 select-none text-center">
                
                <!-- Facility Header -->
                <div class="pb-1.5 border-b border-dashed border-black/60">
                    <div class="font-black text-xs uppercase tracking-tight">{{ facilityName }}</div>
                    <div class="text-[9px] text-slate-700">Patient Queue Token · Mapokezi</div>
                </div>

                <!-- Big Ticket Number -->
                <div class="py-3 border-b border-dashed border-black/60 space-y-1">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-800">Your Turn Number</div>
                    <div class="font-black text-4xl tracking-tighter">{{ ticket.ticket_number || 'A-001' }}</div>
                    <div class="space-y-0.5 pt-0.5">
                        <div class="text-xs font-black uppercase px-2 py-0.5 bg-black/5 rounded inline-block border border-black/15">
                            {{ servicePointInfo.title }}
                        </div>
                        <div class="text-[9.5px] font-bold text-slate-800">
                            {{ servicePointInfo.swahili }}
                        </div>
                    </div>
                </div>

                <!-- Metadata Details -->
                <div class="py-2 border-b border-dashed border-black/60 text-[10px] text-left space-y-1">
                    <div class="flex justify-between">
                        <span>Patient MRN:</span>
                        <span class="font-bold">{{ ticket.patient?.primary_mrn || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Patient Name:</span>
                        <span class="font-bold truncate max-w-[140px]">{{ ticket.patient?.first_name }} {{ ticket.patient?.last_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Destination:</span>
                        <span class="font-bold truncate max-w-[140px] text-right">{{ servicePointInfo.desk }}</span>
                    </div>
                    <div v-if="serviceSummary" class="flex justify-between">
                        <span>Service / Item:</span>
                        <span class="font-bold truncate max-w-[140px] text-right">{{ serviceSummary }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Payment Mode:</span>
                        <span class="font-bold uppercase">{{ paymentMode }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Priority Level:</span>
                        <span class="font-bold uppercase">{{ ticket.priority || 'Routine' }}</span>
                    </div>
                    <div class="flex justify-between text-slate-700 pt-0.5 border-t border-dotted border-black/30">
                        <span>Date & Time:</span>
                        <span>{{ formatDate(ticket.created_at) }} {{ formatTime(ticket.created_at) }}</span>
                    </div>
                </div>

                <!-- QR Code & Waiting Guidance -->
                <div class="pt-2 space-y-1">
                    <div class="flex justify-center">
                        <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="Ticket QR" class="w-16 h-16" />
                    </div>
                    <div class="text-[9px] font-bold leading-tight text-slate-900">
                        Tafadhali subiri namba yako iitwe kwenye skrini ya ukumbini.
                    </div>
                    <div class="text-[8px] text-slate-600">
                        Please watch the lobby display screen for your turn.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #queue-ticket-paper, #queue-ticket-paper * {
        visibility: visible;
    }
    #queue-ticket-paper {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0;
        padding: 4mm;
    }
}
</style>
