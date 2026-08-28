<script setup>
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Printer, X, TestTube2 } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    sample: {
        type: Object,
        required: true,
    },
    facilityName: {
        type: String,
        default: 'AfyaNova Diagnostics Lab',
    },
});

const emit = defineEmits(['close']);

const qrCodeDataUrl = ref('');

const generateQr = async () => {
    try {
        const payload = `LAB:${props.sample.sample_id || props.sample.id}|MRN:${props.sample.patient?.primary_mrn}|TEST:${props.sample.test_name || props.sample.test_type}|DT:${props.sample.collected_at || new Date().toISOString()}`;
        qrCodeDataUrl.value = await QRCode.toDataURL(payload, {
            width: 70,
            margin: 0,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Failed to generate QR code for specimen label', err);
    }
};

onMounted(() => {
    generateQr();
});

watch(() => props.sample, () => {
    generateQr();
});

const printLabel = () => {
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
        <div class="bg-card text-card-foreground rounded-xl shadow-2xl border border-border max-w-xs w-full p-4 print:p-0 print:border-none print:shadow-none print:max-w-none print:w-[50mm] space-y-4">
            
            <!-- Modal Header (Hidden during actual print) -->
            <div class="flex items-center justify-between pb-2 border-b border-border print:hidden">
                <div class="flex items-center gap-2">
                    <TestTube2 class="w-4 h-4 text-primary" />
                    <h3 class="font-bold text-xs text-foreground">Specimen Barcode Tube Label</h3>
                </div>
                <div class="flex items-center gap-1">
                    <Button variant="default" size="sm" class="h-7 text-xs gap-1.5" @click="printLabel">
                        <Printer class="w-3 h-3" />
                        <span>Print Label</span>
                    </Button>
                    <button @click="emit('close')" class="p-1 text-muted-foreground hover:text-foreground rounded-md">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Thermal Printable Tube Label Area (50mm x 25mm Standard Label Size) -->
            <div id="specimen-label-paper" class="bg-white text-black font-mono text-[9px] leading-tight p-2 rounded border border-slate-200 print:border-none print:p-0 select-none flex items-center gap-2 w-[50mm] min-h-[25mm]">
                
                <!-- Left QR / Matrix Code -->
                <div class="flex-shrink-0">
                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="Sample QR" class="w-14 h-14 border border-black/20" />
                </div>

                <!-- Right Metadata -->
                <div class="flex-1 space-y-0.5 overflow-hidden">
                    <div class="font-black text-[10px] truncate leading-tight uppercase">
                        {{ sample.patient?.first_name }} {{ sample.patient?.last_name }}
                    </div>
                    <div class="flex justify-between text-[8.5px] font-bold">
                        <span>MRN: {{ sample.patient?.primary_mrn || 'N/A' }}</span>
                        <span>{{ sample.patient?.gender }}</span>
                    </div>
                    <div class="font-bold text-[9px] truncate text-black uppercase">
                        {{ sample.test_name || sample.test_type || 'Lab Investigation' }}
                    </div>
                    <div class="flex justify-between text-[7.5px] text-slate-700">
                        <span>{{ sample.specimen_type || 'Whole Blood' }}</span>
                        <span>{{ formatDate(sample.collected_at) }} {{ formatTime(sample.collected_at) }}</span>
                    </div>
                    <div class="font-black text-[9px] tracking-wider pt-0.5 border-t border-black/40">
                        SID: {{ sample.sample_id || sample.id?.slice(0, 10) }}
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
    #specimen-label-paper, #specimen-label-paper * {
        visibility: visible;
    }
    #specimen-label-paper {
        position: absolute;
        left: 0;
        top: 0;
        width: 50mm;
        height: 25mm;
        margin: 0;
        padding: 2mm;
    }
}
</style>
