<script setup>
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Printer, X, Bed } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    patient: {
        type: Object,
        required: true,
    },
    admission: {
        type: Object,
        default: () => ({}),
    },
    facilityName: {
        type: String,
        default: 'AfyaNova Hospital',
    },
});

const emit = defineEmits(['close']);

const qrCodeDataUrl = ref('');

const generateQr = async () => {
    try {
        const payload = `WRISTBAND|MRN:${props.patient.primary_mrn}|NAME:${props.patient.first_name} ${props.patient.last_name}|DOB:${props.patient.dob}|BG:${props.patient.blood_group || 'UNK'}|WARD:${props.admission?.ward?.name || 'General'}`;
        qrCodeDataUrl.value = await QRCode.toDataURL(payload, {
            width: 75,
            margin: 0,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Failed to generate QR code for wristband', err);
    }
};

onMounted(() => {
    generateQr();
});

watch(() => props.patient, () => {
    generateQr();
});

const printWristband = () => {
    window.print();
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 print:p-0 print:bg-white print:static print:inset-auto">
        <div class="bg-card text-card-foreground rounded-xl shadow-2xl border border-border max-w-lg w-full p-4 print:p-0 print:border-none print:shadow-none print:max-w-none print:w-[250mm] space-y-4">
            
            <!-- Modal Header (Hidden during actual print) -->
            <div class="flex items-center justify-between pb-2 border-b border-border print:hidden">
                <div class="flex items-center gap-2">
                    <Bed class="w-4 h-4 text-primary" />
                    <h3 class="font-bold text-xs text-foreground">Inpatient Bedside Patient Wristband (250mm x 25mm)</h3>
                </div>
                <div class="flex items-center gap-1">
                    <Button variant="default" size="sm" class="h-7 text-xs gap-1.5" @click="printWristband">
                        <Printer class="w-3 h-3" />
                        <span>Print Wristband</span>
                    </Button>
                    <button @click="emit('close')" class="p-1 text-muted-foreground hover:text-foreground rounded-md">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Thermal Printable Wristband Area (250mm x 25mm Linear Band) -->
            <div id="patient-wristband-paper" class="bg-white text-black font-mono text-[10px] leading-tight p-2.5 rounded border border-slate-300 print:border-none print:p-0 select-none flex items-center justify-between w-full h-[25mm] overflow-hidden">
                
                <!-- Facility & Band Anchor Left -->
                <div class="w-24 border-r border-dashed border-black/50 pr-2 space-y-0.5">
                    <div class="font-black text-[9px] uppercase leading-tight">{{ facilityName }}</div>
                    <div class="text-[8px] font-bold uppercase text-slate-700">INPATIENT ID</div>
                    <div class="text-[7.5px] text-slate-500">Do Not Remove</div>
                </div>

                <!-- Main Patient Demographics & Identification -->
                <div class="flex-1 px-3 space-y-0.5">
                    <div class="flex items-baseline justify-between">
                        <div class="font-black text-xs uppercase tracking-tight">
                            {{ patient.first_name }} {{ patient.middle_name || '' }} {{ patient.last_name }}
                        </div>
                        <div class="font-black text-xs">
                            BG: {{ patient.blood_group || 'O+' }}
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[9px]">
                        <span>MRN: <strong class="font-bold">{{ patient.primary_mrn }}</strong></span>
                        <span>DOB: {{ formatDate(patient.dob) }} ({{ patient.gender }})</span>
                        <span>Ward: <strong class="font-bold">{{ admission?.ward?.name || 'Inpatient' }} / Bed {{ admission?.bed?.bed_number || '1' }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 text-[8.5px] text-rose-700 font-bold">
                        <span>ALLERGIES: {{ patient.allergies || 'NKDA (None Known)' }}</span>
                    </div>
                </div>

                <!-- Right QR Code for Bedside Scanner Verification -->
                <div class="shrink-0 pl-2 border-l border-dashed border-black/50">
                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="Wristband QR" class="w-14 h-14" />
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
    #patient-wristband-paper, #patient-wristband-paper * {
        visibility: visible;
    }
    #patient-wristband-paper {
        position: absolute;
        left: 0;
        top: 0;
        width: 250mm;
        height: 25mm;
        margin: 0;
        padding: 2mm 4mm;
    }
}
</style>
