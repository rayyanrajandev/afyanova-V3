<script setup>
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Printer, X } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    facilityName: {
        type: String,
        default: 'AfyaNova Health System',
    },
    facilityAddress: {
        type: String,
        default: 'P.O. Box 4050, Dar es Salaam, Tanzania',
    },
    facilityPhone: {
        type: String,
        default: '+255 22 212 3456',
    },
    cashierName: {
        type: String,
        default: 'Front Desk Cashier',
    },
});

const emit = defineEmits(['close']);

const qrCodeDataUrl = ref('');

const generateQr = async () => {
    try {
        const payload = `AFYANOVA-INV:${props.invoice.invoice_number || props.invoice.id}|AMT:${props.invoice.total_amount}|PAID:${props.invoice.paid_amount || props.invoice.total_amount}|DT:${props.invoice.created_at || new Date().toISOString()}`;
        qrCodeDataUrl.value = await QRCode.toDataURL(payload, {
            width: 110,
            margin: 1,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Failed to generate QR code for thermal receipt', err);
    }
};

onMounted(() => {
    generateQr();
});

watch(() => props.invoice, () => {
    generateQr();
});

const printReceipt = () => {
    window.print();
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');

const formatDate = (dateStr) => {
    if (!dateStr) return new Date().toLocaleDateString('en-GB');
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 print:p-0 print:bg-white print:static print:inset-auto">
        <div class="bg-card text-card-foreground rounded-xl shadow-2xl border border-border max-w-sm w-full p-4 print:p-0 print:border-none print:shadow-none print:max-w-none print:w-[80mm] space-y-4">
            
            <!-- Modal Header (Hidden during actual print) -->
            <div class="flex items-center justify-between pb-2 border-b border-border print:hidden">
                <div class="flex items-center gap-2">
                    <Printer class="w-4 h-4 text-primary" />
                    <h3 class="font-bold text-xs text-foreground">80mm POS Thermal Receipt</h3>
                </div>
                <div class="flex items-center gap-1">
                    <Button variant="default" size="sm" class="h-7 text-xs gap-1.5" @click="printReceipt">
                        <Printer class="w-3 h-3" />
                        <span>Print Receipt</span>
                    </Button>
                    <button @click="emit('close')" class="p-1 text-muted-foreground hover:text-foreground rounded-md">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Thermal Printable Paper Area (80mm Width optimized) -->
            <div id="thermal-receipt-paper" class="bg-white text-black font-mono text-[11px] leading-tight p-3 rounded border border-slate-200 print:border-none print:p-0 select-none">
                
                <!-- Facility Header -->
                <div class="text-center space-y-0.5 pb-2 border-b border-dashed border-black/60">
                    <div class="font-black text-sm uppercase tracking-tight">{{ facilityName }}</div>
                    <div class="text-[9.5px]">{{ facilityAddress }}</div>
                    <div class="text-[9.5px]">Tel: {{ facilityPhone }}</div>
                    <div class="text-[10px] font-bold mt-1 uppercase">Official Payment Receipt</div>
                </div>

                <!-- Invoice & Patient Metadata -->
                <div class="py-2 space-y-0.5 border-b border-dashed border-black/60 text-[10px]">
                    <div class="flex justify-between">
                        <span>Receipt No:</span>
                        <span class="font-bold">{{ invoice.invoice_number || invoice.id?.slice(0, 8) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Date/Time:</span>
                        <span>{{ formatDate(invoice.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Patient MRN:</span>
                        <span class="font-bold">{{ invoice.patient?.primary_mrn || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Patient Name:</span>
                        <span class="font-bold truncate max-w-[150px]">{{ invoice.patient?.first_name }} {{ invoice.patient?.last_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Payment Mode:</span>
                        <span class="font-bold uppercase">{{ invoice.payment_mode || 'Cash' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cashier:</span>
                        <span>{{ cashierName }}</span>
                    </div>
                </div>

                <!-- Itemized Breakdown -->
                <div class="py-2 border-b border-dashed border-black/60 space-y-1">
                    <div class="flex justify-between font-bold text-[9.5px] uppercase border-b border-black/30 pb-0.5">
                        <span class="w-1/2">Item Description</span>
                        <span class="w-1/6 text-center">Qty</span>
                        <span class="w-1/3 text-right">Amount (TZS)</span>
                    </div>
                    
                    <div v-for="(item, idx) in invoice.items || []" :key="idx" class="flex justify-between text-[10px]">
                        <span class="w-1/2 truncate">{{ item.description || item.name || 'Service Fee' }}</span>
                        <span class="w-1/6 text-center">{{ item.quantity || 1 }}</span>
                        <span class="w-1/3 text-right font-bold">{{ formatCurrency(item.total_price || item.amount) }}</span>
                    </div>

                    <div v-if="!invoice.items || invoice.items.length === 0" class="flex justify-between text-[10px]">
                        <span class="w-1/2">Consultation / Services</span>
                        <span class="w-1/6 text-center">1</span>
                        <span class="w-1/3 text-right font-bold">{{ formatCurrency(invoice.total_amount) }}</span>
                    </div>
                </div>

                <!-- Totals & Balance -->
                <div class="py-2 border-b border-dashed border-black/60 space-y-0.5 text-[10px]">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span>TZS {{ formatCurrency(invoice.subtotal || invoice.total_amount) }}</span>
                    </div>
                    <div v-if="invoice.discount_amount > 0" class="flex justify-between text-black">
                        <span>Discount:</span>
                        <span>-TZS {{ formatCurrency(invoice.discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-xs font-black pt-1 border-t border-black/30">
                        <span>TOTAL PAID:</span>
                        <span>TZS {{ formatCurrency(invoice.paid_amount || invoice.total_amount) }}</span>
                    </div>
                    <div v-if="invoice.balance > 0" class="flex justify-between font-bold">
                        <span>Balance Remaining:</span>
                        <span>TZS {{ formatCurrency(invoice.balance) }}</span>
                    </div>
                </div>

                <!-- QR Code Verification & Footer -->
                <div class="pt-2 text-center space-y-1">
                    <div class="flex justify-center">
                        <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="Receipt QR" class="w-20 h-20" />
                    </div>
                    <div class="text-[9px] uppercase tracking-wider font-bold">Thank You For Choosing Us</div>
                    <div class="text-[8px] text-slate-600">Quick Verification · AfyaNova v3.0 Electronic Health Suite</div>
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
    #thermal-receipt-paper, #thermal-receipt-paper * {
        visibility: visible;
    }
    #thermal-receipt-paper {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0;
        padding: 4mm;
    }
}
</style>
