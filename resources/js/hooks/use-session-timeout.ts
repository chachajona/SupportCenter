import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

interface UseSessionTimeoutOptions {
    timeoutMinutes: number;
    warningMinutes?: number;
}

export function useSessionTimeout({ timeoutMinutes, warningMinutes = 5 }: UseSessionTimeoutOptions) {
    const lastActivityRef = useRef<number>(Date.now());
    const timeoutRef = useRef<NodeJS.Timeout | null>(null);
    const warningShownRef = useRef<boolean>(false);

    const updateActivity = useCallback(() => {
        lastActivityRef.current = Date.now();
        warningShownRef.current = false;
    }, []);

    const checkTimeout = useCallback(() => {
        const now = Date.now();
        const timeSinceActivity = now - lastActivityRef.current;
        const timeoutMs = timeoutMinutes * 60 * 1000;
        const warningMs = Math.max(0, (timeoutMinutes - warningMinutes) * 60 * 1000);

        // Show warning if approaching timeout
        if (timeSinceActivity >= warningMs && !warningShownRef.current) {
            warningShownRef.current = true;
            const remainingMinutes = Math.ceil((timeoutMs - timeSinceActivity) / 60000);

            // You can customize this warning method
            if (
                window.confirm(
                    `Your session will expire in ${remainingMinutes} minutes due to inactivity. ` +
                        'Click OK to stay logged in, or Cancel to logout now.',
                )
            ) {
                updateActivity();
            } else {
                router.post('/logout');
                return;
            }
        }

        // Logout if timeout exceeded
        if (timeSinceActivity >= timeoutMs) {
            router.post('/logout');
            return;
        }
    }, [timeoutMinutes, warningMinutes, updateActivity]);

    useEffect(() => {
        // Activity event listeners
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

        events.forEach((event) => {
            document.addEventListener(event, updateActivity, true);
        });

        // Check timeout every minute
        timeoutRef.current = setInterval(checkTimeout, 60000);

        return () => {
            events.forEach((event) => {
                document.removeEventListener(event, updateActivity, true);
            });

            if (timeoutRef.current) {
                clearInterval(timeoutRef.current);
            }
        };
    }, [updateActivity, checkTimeout]);

    return {
        updateActivity,
        getLastActivity: () => lastActivityRef.current,
    };
}
