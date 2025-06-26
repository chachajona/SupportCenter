import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface SecurityEvent {
    id: number;
    event_type: string;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
}

export default function SecurityDashboard() {
    const [events, setEvents] = useState<SecurityEvent[]>([]);

    useEffect(() => {
        // @ts-expect-error – Echo is provided globally via app scaffolding
        const echo = window.Echo;
        if (!echo) return;

        echo.channel('security-events').listen('.security.event', (data: SecurityEvent) => {
            setEvents((prev) => [data, ...prev.slice(0, 49)]); // keep last 50 events
        });

        return () => {
            echo.leave('security-events');
        };
    }, []);

    return (
        <>
            <Head title="Security Monitoring" />
            <div className="space-y-6 p-6">
                <h1 className="text-3xl font-bold">Real-Time Security Monitoring</h1>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            Recent Events <Badge>{events.length}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="max-h-[600px] divide-y overflow-y-auto">
                        {events.map((event) => (
                            <div key={event.id} className="py-3">
                                <p className="font-medium">{event.event_type}</p>
                                <p className="text-muted-foreground text-sm">
                                    {event.ip_address} · {new Date(event.created_at).toLocaleString()}
                                </p>
                            </div>
                        ))}
                        {events.length === 0 && <p className="text-sm">Waiting for events...</p>}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
