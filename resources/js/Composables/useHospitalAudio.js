/**
 * useHospitalAudio: Pure Web Audio API Chime Synthesizer
 * Generates instant acoustic feedback for hospital workflows without external audio files.
 */

let audioCtx = null;

const getAudioContext = () => {
    if (!audioCtx && typeof window !== 'undefined') {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
};

export function useHospitalAudio() {
    /**
     * Play a smooth single chime frequency
     */
    const playTone = (freq, startTime, duration, type = 'sine', maxGain = 0.2) => {
        const ctx = getAudioContext();
        if (!ctx) return;

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, startTime);

        // Envelope: smooth attack, gentle exponential decay
        gain.gain.setValueAtTime(0.0001, startTime);
        gain.gain.exponentialRampToValueAtTime(maxGain, startTime + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(startTime);
        osc.stop(startTime + duration + 0.05);
    };

    /**
     * Classic 2/3-tone Hospital/Airport lobby chime (C5 - E5 - G5)
     */
    const playQueueCallChime = () => {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        playTone(523.25, now, 0.45, 'sine', 0.25); // C5
        playTone(659.25, now + 0.25, 0.55, 'sine', 0.25); // E5
        playTone(783.99, now + 0.50, 0.85, 'sine', 0.28); // G5
    };

    /**
     * Phlebotomy intake subtle double ping
     */
    const playPhlebotomyChime = () => {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        playTone(659.25, now, 0.3, 'sine', 0.18); // E5
        playTone(880.00, now + 0.15, 0.5, 'sine', 0.22); // A5
    };

    /**
     * High-priority Critical Alert Siren (for panic lab values / STAT triage)
     */
    const playCriticalAlarm = () => {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        // Pulse 1
        playTone(880.00, now, 0.15, 'triangle', 0.35);
        playTone(440.00, now + 0.18, 0.18, 'triangle', 0.30);
        // Pulse 2
        playTone(880.00, now + 0.40, 0.15, 'triangle', 0.35);
        playTone(440.00, now + 0.58, 0.25, 'triangle', 0.30);
    };

    /**
     * Cashier payment settlement affirmative chime
     */
    const playSuccessCashierTone = () => {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        playTone(523.25, now, 0.2, 'sine', 0.2);
        playTone(659.25, now + 0.12, 0.2, 'sine', 0.22);
        playTone(1046.50, now + 0.24, 0.6, 'sine', 0.25); // C6
    };

    return {
        playQueueCallChime,
        playPhlebotomyChime,
        playCriticalAlarm,
        playSuccessCashierTone,
    };
}
