<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Camera, X, RefreshCw, Flashlight, AlertCircle, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Scan Barcode with Camera',
    },
});

const emit = defineEmits(['close', 'scan']);

const videoRef = ref(null);
const canvasRef = ref(null);
const stream = ref(null);
const hasCameraError = ref(false);
const errorMessage = ref('');
const isScanning = ref(false);
const detectedCode = ref(null);
const facingMode = ref('environment'); // 'environment' (back) or 'user' (front)
const isTorchOn = ref(false);
const hasTorchSupport = ref(false);
let scanInterval = null;
let barcodeDetector = null;

// Synthetic Audio Beep via Web Audio API (Zero external assets needed)
const playBeep = () => {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1760, ctx.currentTime); // 1760 Hz high-pitch hospital scanner beep
        gain.setValueAtTime(0.3, ctx.currentTime);
        gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.12);
    } catch (e) {
        console.warn('Audio feedback failed:', e);
    }
};

// Haptic Vibration Feedback
const triggerVibration = () => {
    if ('vibrate' in navigator) {
        try {
            navigator.vibrate([60, 40, 60]);
        } catch (e) {
            // Ignored
        }
    }
};

const startCamera = async () => {
    hasCameraError.value = false;
    errorMessage.value = '';
    detectedCode.value = null;

    // Check BarcodeDetector native API support
    if ('BarcodeDetector' in window) {
        try {
            const formats = await window.BarcodeDetector.getSupportedFormats();
            barcodeDetector = new window.BarcodeDetector({
                formats: formats.length > 0 ? formats : ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e']
            });
        } catch (e) {
            barcodeDetector = null;
        }
    }

    try {
        const constraints = {
            video: {
                facingMode: { ideal: facingMode.value },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        };

        const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        stream.value = mediaStream;

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            isScanning.value = true;
            startDetectionLoop();
        }

        // Check flashlight / torch capability
        const track = mediaStream.getVideoTracks()[0];
        const capabilities = track.getCapabilities ? track.getCapabilities() : {};
        hasTorchSupport.value = !!capabilities.torch;
    } catch (err) {
        console.error('Camera access error:', err);
        hasCameraError.value = true;
        if (err.name === 'NotAllowedError') {
            errorMessage.value = 'Camera permission was denied. Please allow camera access in your browser settings.';
        } else if (err.name === 'NotFoundError') {
            errorMessage.value = 'No camera found on this device.';
        } else {
            errorMessage.value = 'Unable to start camera. Please verify camera permissions.';
        }
    }
};

const stopCamera = () => {
    isScanning.value = false;
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
};

const toggleTorch = async () => {
    if (!stream.value || !hasTorchSupport.value) return;
    const track = stream.value.getVideoTracks()[0];
    try {
        isTorchOn.value = !isTorchOn.value;
        await track.applyConstraints({
            advanced: [{ torch: isTorchOn.value }]
        });
    } catch (e) {
        console.warn('Torch toggle failed:', e);
    }
};

const switchCamera = () => {
    stopCamera();
    facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
    startCamera();
};

const startDetectionLoop = () => {
    if (scanInterval) clearInterval(scanInterval);

    scanInterval = setInterval(async () => {
        if (!isScanning.value || !videoRef.value || videoRef.value.readyState < 2) return;

        if (barcodeDetector) {
            try {
                const barcodes = await barcodeDetector.detect(videoRef.value);
                if (barcodes && barcodes.length > 0) {
                    const rawVal = barcodes[0].rawValue;
                    handleDetectedBarcode(rawVal);
                }
            } catch (err) {
                // Frame processing error ignored
            }
        }
    }, 150);
};

const handleDetectedBarcode = (code) => {
    if (!code || detectedCode.value) return;
    detectedCode.value = code;
    playBeep();
    triggerVibration();

    setTimeout(() => {
        emit('scan', code);
        closeModal();
    }, 400);
};

const closeModal = () => {
    stopCamera();
    emit('close');
};

onMounted(() => {
    if (props.isOpen) {
        startCamera();
    }
});

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-black/90 backdrop-blur-md z-60 flex items-center justify-center p-4">
        <div class="bg-card border border-border/80 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col animate-in fade-in-0 zoom-in-95 duration-150">
            <!-- Header -->
            <div class="p-3.5 px-4 bg-muted/40 border-b border-border/60 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Camera class="w-4 h-4 text-primary" />
                    <span class="font-bold text-sm text-foreground">{{ title }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        v-if="hasTorchSupport"
                        type="button"
                        @click="toggleTorch"
                        class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors"
                        :class="isTorchOn ? 'text-amber-500 bg-amber-500/10' : ''"
                        title="Toggle Flashlight"
                    >
                        <Flashlight class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        @click="switchCamera"
                        class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors"
                        title="Switch Camera (Front/Back)"
                    >
                        <RefreshCw class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Viewfinder Area -->
            <div class="relative bg-black aspect-4/3 flex items-center justify-center overflow-hidden">
                <!-- Video Stream -->
                <video
                    ref="videoRef"
                    playsinline
                    muted
                    class="w-full h-full object-cover"
                ></video>

                <!-- Reticle / Target Scanner Frame -->
                <div v-if="!hasCameraError && !detectedCode" class="absolute inset-0 flex items-center justify-center pointer-events-none p-8">
                    <div class="relative w-64 h-36 border-2 border-primary/70 rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                        <!-- Corner Markers -->
                        <div class="absolute -top-1 -left-1 w-4 h-4 border-t-2 border-l-2 border-primary"></div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 border-t-2 border-r-2 border-primary"></div>
                        <div class="absolute -bottom-1 -left-1 w-4 h-4 border-b-2 border-l-2 border-primary"></div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 border-b-2 border-r-2 border-primary"></div>

                        <!-- Animated Laser Scanner Line -->
                        <div class="laser-line"></div>
                    </div>
                </div>

                <!-- Detected Code Success Banner -->
                <div v-if="detectedCode" class="absolute inset-0 bg-emerald-950/80 backdrop-blur-xs flex flex-col items-center justify-center gap-2 p-4 text-center animate-in zoom-in-95">
                    <CheckCircle2 class="w-12 h-12 text-emerald-400 animate-bounce" />
                    <div class="font-mono text-sm font-bold text-white bg-black/60 px-3 py-1 rounded-md border border-emerald-500/40">
                        {{ detectedCode }}
                    </div>
                    <div class="text-xs text-emerald-200">Barcode Detected! Adding item...</div>
                </div>

                <!-- Error State -->
                <div v-if="hasCameraError" class="absolute inset-0 bg-background/95 p-6 flex flex-col items-center justify-center text-center gap-2">
                    <AlertCircle class="w-10 h-10 text-rose-500 mb-1" />
                    <div class="font-bold text-sm text-foreground">Camera Access Failed</div>
                    <div class="text-xs text-muted-foreground">{{ errorMessage }}</div>
                    <button
                        type="button"
                        @click="startCamera"
                        class="mt-2 px-3 py-1.5 bg-primary text-primary-foreground text-xs font-bold rounded-lg hover:bg-primary/90 transition-colors"
                    >
                        Try Again
                    </button>
                </div>
            </div>

            <!-- Instructions Footer -->
            <div class="p-3 bg-muted/20 border-t border-border/60 text-center text-xs text-muted-foreground">
                Point your phone camera at any standard 1D Barcode (Code 128, EAN, UPC) or 2D QR Code.
            </div>
        </div>
    </div>
</template>

<style scoped>
.laser-line {
    position: absolute;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0) 0%, #10b981 50%, rgba(16, 185, 129, 0) 100%);
    box-shadow: 0 0 8px #10b981;
    animation: scanAnimation 2s infinite ease-in-out;
}

@keyframes scanAnimation {
    0% {
        top: 5%;
        opacity: 0.8;
    }
    50% {
        top: 90%;
        opacity: 1;
    }
    100% {
        top: 5%;
        opacity: 0.8;
    }
}
</style>
