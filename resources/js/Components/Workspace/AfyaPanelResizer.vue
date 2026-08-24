<script setup>
import { ref } from 'vue';

const props = defineProps({
    side: {
        type: String,
        default: 'left', // 'left' or 'right'
    },
    modelValue: {
        type: Number,
        required: true,
    },
    min: {
        type: Number,
        default: 200,
    },
    max: {
        type: Number,
        default: 450,
    },
    step: {
        type: Number,
        default: 10,
    },
});

const emit = defineEmits(['update:modelValue', 'resize-start', 'resize-end']);

const isDragging = ref(false);
let animFrameId = null;

const startDrag = (event) => {
    event.preventDefault();
    isDragging.value = true;
    emit('resize-start');

    const startX = event.clientX || (event.touches && event.touches[0].clientX);
    const startWidth = props.modelValue;
    let pendingWidth = startWidth;

    const onMove = (moveEvent) => {
        const currentX = moveEvent.clientX || (moveEvent.touches && moveEvent.touches[0].clientX);
        if (!currentX) return;

        let delta = currentX - startX;
        if (props.side === 'right') {
            delta = -delta;
        }

        pendingWidth = Math.min(Math.max(startWidth + delta, props.min), props.max);

        // Hardware-accelerated 120fps requestAnimationFrame throttling
        if (animFrameId === null) {
            animFrameId = requestAnimationFrame(() => {
                emit('update:modelValue', pendingWidth);
                animFrameId = null;
            });
        }
    };

    const onEnd = () => {
        if (animFrameId !== null) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }
        emit('update:modelValue', pendingWidth);
        isDragging.value = false;
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onEnd);
        window.removeEventListener('touchmove', onMove);
        window.removeEventListener('touchend', onEnd);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        emit('resize-end');
    };

    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('mouseup', onEnd);
    window.addEventListener('touchmove', onMove, { passive: true });
    window.addEventListener('touchend', onEnd);
};

const handleKeyDown = (event) => {
    let delta;
    if (event.key === 'ArrowLeft') {
        delta = props.side === 'left' ? -props.step : props.step;
    } else if (event.key === 'ArrowRight') {
        delta = props.side === 'left' ? props.step : -props.step;
    } else if (event.key === 'Home') {
        emit('update:modelValue', props.min);
        return;
    } else if (event.key === 'End') {
        emit('update:modelValue', props.max);
        return;
    } else {
        return;
    }

    event.preventDefault();
    const newWidth = Math.min(Math.max(props.modelValue + delta, props.min), props.max);
    emit('update:modelValue', newWidth);
};
</script>

<template>
    <div
        role="separator"
        tabindex="0"
        aria-orientation="vertical"
        :aria-label="`${side === 'left' ? 'Sidebar' : 'Context panel'} width resizer`"
        :aria-valuenow="modelValue"
        :aria-valuemin="min"
        :aria-valuemax="max"
        @mousedown="startDrag"
        @touchstart="startDrag"
        @keydown="handleKeyDown"
        class="w-1.5 hover:w-2 -mx-0.5 relative z-20 cursor-col-resize group flex items-center justify-center transition-all select-none focus:outline-none focus:ring-1 focus:ring-ring will-change-transform"
        :class="{ 'bg-primary w-2': isDragging, 'bg-transparent hover:bg-primary/20': !isDragging }"
    >
        <!-- Resizer handle indicator -->
        <div class="h-8 w-0.5 rounded bg-border group-hover:bg-primary transition-colors" :class="{ 'bg-primary-foreground': isDragging }"></div>
    </div>
</template>
