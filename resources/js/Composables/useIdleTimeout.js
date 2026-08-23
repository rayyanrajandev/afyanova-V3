import { ref, onMounted, onUnmounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * useIdleTimeout Composable
 *
 * Tracks user activity (mouse, keyboard, touch, scroll) and warns the user
 * before forcing a logout due to inactivity. This is especially vital for
 * shared clinical workstations (HIPAA / GDPR / Ministry guidelines).
 *
 * @param {Object} options
 * @param {number} options.timeoutMinutes Total idle duration before logout (default: 30)
 * @param {number} options.warningMinutes Warning duration before logout (default: 2)
 */
export function useIdleTimeout(options = {}) {
    const timeoutMinutes = options.timeoutMinutes ?? 30;
    const warningMinutes = options.warningMinutes ?? 2;

    const timeoutMs = timeoutMinutes * 60 * 1000;
    const warningMs = warningMinutes * 60 * 1000;

    const lastActivity = ref(Date.now());
    const isWarningVisible = ref(false);
    const remainingSeconds = ref(warningMinutes * 60);

    let checkInterval = null;
    let throttleTimeout = null;

    const page = usePage();
    const isAuthenticated = computed(() => !!page.props.auth?.user);

    const recordActivity = () => {
        if (!throttleTimeout) {
            throttleTimeout = setTimeout(() => {
                throttleTimeout = null;
            }, 1000); // Throttle activity checks to once per second

            lastActivity.value = Date.now();
            if (isWarningVisible.value) {
                // If warning was showing and user interacts, dismiss it
                isWarningVisible.value = false;
            }
        }
    };

    const extendSession = () => {
        lastActivity.value = Date.now();
        isWarningVisible.value = false;
        // Lightweight Inertia reload to touch the backend session
        router.reload({
            only: ['auth'],
            preserveScroll: true,
            preserveState: true,
        });
    };

    const forceLogout = () => {
        if (checkInterval) clearInterval(checkInterval);
        router.post(route('logout'), {}, {
            onFinish: () => {
                window.location.href = route('login') + '?reason=idle_timeout';
            },
        });
    };

    const checkIdle = () => {
        if (!isAuthenticated.value) return;

        const now = Date.now();
        const elapsed = now - lastActivity.value;
        const remaining = timeoutMs - elapsed;

        if (remaining <= 0) {
            forceLogout();
        } else if (remaining <= warningMs) {
            isWarningVisible.value = true;
            remainingSeconds.value = Math.max(0, Math.ceil(remaining / 1000));
        } else {
            isWarningVisible.value = false;
        }
    };

    const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];

    onMounted(() => {
        if (!isAuthenticated.value) return;

        activityEvents.forEach((event) => {
            window.addEventListener(event, recordActivity, { passive: true });
        });

        checkInterval = setInterval(checkIdle, 1000);
    });

    onUnmounted(() => {
        activityEvents.forEach((event) => {
            window.removeEventListener(event, recordActivity);
        });

        if (checkInterval) clearInterval(checkInterval);
        if (throttleTimeout) clearTimeout(throttleTimeout);
    });

    return {
        isWarningVisible,
        remainingSeconds,
        extendSession,
        forceLogout,
    };
}
