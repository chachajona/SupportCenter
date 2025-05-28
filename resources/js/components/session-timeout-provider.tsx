import { useSessionTimeout } from '@/hooks/use-session-timeout';
import { ReactNode } from 'react';

interface SessionTimeoutProviderProps {
    children: ReactNode;
}

export function SessionTimeoutProvider({ children }: SessionTimeoutProviderProps) {
    // Only enable session timeout for authenticated users
    useSessionTimeout({
        timeoutMinutes: 30, // Should match SESSION_IDLE_TIMEOUT
        warningMinutes: 5,
    });

    return <>{children}</>;
}
