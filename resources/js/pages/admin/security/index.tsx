import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { Activity, AlertCircle, AlertTriangle, Ban, Eye, RefreshCw, Shield, Users } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { CartesianGrid, Cell, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface SecurityEvent {
    id: number;
    event_type: string;
    severity: number;
    ip_address: string | null;
    user_id: number | null;
    user_name: string | null;
    user_agent: string | null;
    details: Record<string, unknown>;
    created_at: string;
}

interface SecurityMetrics {
    threats_blocked_24h: number;
    threats_blocked_1h: number;
    blocked_ips_count: number;
    blocked_ips_list: Array<{
        ip: string;
        blocked_at: string;
        expires_at: string;
        trigger_event: string;
        is_active: boolean;
    }>;
    auth_events_24h: number;
    failed_auth_24h: number;
    event_breakdown: Record<string, number>;
    timeline_data: Array<{
        hour: string;
        timestamp: string;
        threats: number;
        auth_attempts: number;
        auth_success: number;
        total: number;
    }>;
    top_threat_ips: Array<{
        ip: string;
        count: number;
        blocked: boolean;
    }>;
    recent_events: SecurityEvent[];
    system_health: {
        cache_hit_ratio: number;
        avg_response_time: number;
        active_sessions: number;
        permission_checks_per_minute: number;
    };
    alerts: Array<{
        type: string;
        severity: string;
        message: string;
        count: number;
        created_at: string;
    }>;
}

interface SecurityDashboardProps {
    initialMetrics: SecurityMetrics;
}

export default function SecurityDashboard({ initialMetrics }: SecurityDashboardProps) {
    const [events, setEvents] = useState<SecurityEvent[]>(initialMetrics.recent_events || []);
    const [metrics, setMetrics] = useState<SecurityMetrics>(initialMetrics);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [connectionStatus, setConnectionStatus] = useState<'connected' | 'connecting' | 'disconnected'>('disconnected');
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Auto-refresh metrics every 30 seconds
    const refreshMetrics = useCallback(async () => {
        if (isRefreshing) return;

        try {
            setIsRefreshing(true);
            setError(null);

            const response = await fetch('/admin/security/metrics');
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            setMetrics(data.metrics);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to refresh metrics');
        } finally {
            setIsRefreshing(false);
        }
    }, [isRefreshing]);

    // Setup WebSocket connection with auto-reconnect
    useEffect(() => {
        // @ts-expect-error – Echo is provided globally via app scaffolding
        const echo = window.Echo;
        if (!echo) {
            setConnectionStatus('disconnected');
            return;
        }

        setConnectionStatus('connecting');

        const channel = echo.channel('security-events');

        channel.listen('.security.event', (data: SecurityEvent) => {
            setEvents((prev) => [data, ...prev.slice(0, 49)]); // keep last 50 events
            setConnectionStatus('connected');
        });

        // Handle connection errors with exponential backoff
        channel.error((error: unknown) => {
            console.error('WebSocket error:', error);
            setConnectionStatus('disconnected');

            // Attempt to reconnect after delay
            setTimeout(() => {
                setConnectionStatus('connecting');
            }, 5000);
        });

        // Cleanup
        return () => {
            echo.leave('security-events');
        };
    }, []);

    // Auto-refresh timer
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(refreshMetrics, 30000); // 30 seconds
        return () => clearInterval(interval);
    }, [autoRefresh, refreshMetrics]);

    // Get severity badge color
    const getSeverityColor = (severity: number): 'destructive' | 'secondary' | 'outline' | 'default' => {
        if (severity >= 5) return 'destructive';
        if (severity >= 4) return 'destructive';
        if (severity >= 3) return 'secondary';
        if (severity >= 2) return 'outline';
        return 'default';
    };

    // Get alert severity color
    const getAlertSeverityColor = (severity: string): 'destructive' | 'default' => {
        switch (severity) {
            case 'critical':
                return 'destructive';
            case 'high':
                return 'destructive';
            case 'medium':
                return 'destructive';
            case 'low':
                return 'default';
            default:
                return 'default';
        }
    };

    // Chart colors
    const CHART_COLORS = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];

    // Event breakdown for pie chart
    const eventBreakdownData = Object.entries(metrics.event_breakdown).map(([type, count], index) => ({
        name: type.replace('_', ' ').toUpperCase(),
        value: count,
        fill: CHART_COLORS[index % CHART_COLORS.length],
    }));

    return (
        <>
            <Head title="Security Dashboard" />

            <div className="min-h-screen space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Security Dashboard</h1>
                        <p className="text-muted-foreground">Real-time security monitoring and threat analysis</p>
                    </div>

                    <div className="flex items-center gap-4">
                        {/* Connection Status */}
                        <div className="flex items-center gap-2">
                            <div
                                className={`h-2 w-2 rounded-full ${
                                    connectionStatus === 'connected'
                                        ? 'bg-green-500'
                                        : connectionStatus === 'connecting'
                                          ? 'animate-pulse bg-yellow-500'
                                          : 'bg-red-500'
                                }`}
                            />
                            <span className="text-muted-foreground text-sm">
                                {connectionStatus === 'connected' ? 'Live' : connectionStatus === 'connecting' ? 'Connecting' : 'Disconnected'}
                            </span>
                        </div>

                        {/* Auto-refresh toggle */}
                        <Button variant="outline" size="sm" onClick={() => setAutoRefresh(!autoRefresh)}>
                            <Activity className={`mr-2 h-4 w-4 ${autoRefresh ? 'text-green-500' : ''}`} />
                            Auto-refresh {autoRefresh ? 'ON' : 'OFF'}
                        </Button>

                        {/* Manual refresh */}
                        <Button variant="outline" size="sm" onClick={refreshMetrics} disabled={isRefreshing}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Error Alert */}
                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {/* Alerts */}
                {metrics.alerts && metrics.alerts.length > 0 && (
                    <div className="space-y-2">
                        {metrics.alerts.map((alert, index) => (
                            <Alert key={index} variant={getAlertSeverityColor(alert.severity)}>
                                <AlertTriangle className="h-4 w-4" />
                                <AlertDescription>{alert.message}</AlertDescription>
                            </Alert>
                        ))}
                    </div>
                )}

                {/* Key Metrics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Threats Blocked (24h)</CardTitle>
                            <Shield className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.threats_blocked_24h}</div>
                            <p className="text-muted-foreground text-xs">{metrics.threats_blocked_1h} in the last hour</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Blocked IPs</CardTitle>
                            <Ban className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.blocked_ips_count}</div>
                            <p className="text-muted-foreground text-xs">Currently active blocks</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Auth Events (24h)</CardTitle>
                            <Users className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.auth_events_24h}</div>
                            <p className="text-muted-foreground text-xs">{metrics.failed_auth_24h} failures</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">System Health</CardTitle>
                            <Activity className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.system_health.cache_hit_ratio.toFixed(1)}%</div>
                            <p className="text-muted-foreground text-xs">Cache hit ratio</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Section */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Security Events Timeline */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Security Events Timeline (24h)</CardTitle>
                            <CardDescription>Hourly breakdown of security events</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={metrics.timeline_data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="hour" />
                                    <YAxis />
                                    <Tooltip />
                                    <Line type="monotone" dataKey="threats" stroke="#ef4444" strokeWidth={2} name="Threats" />
                                    <Line type="monotone" dataKey="auth_attempts" stroke="#3b82f6" strokeWidth={2} name="Auth Attempts" />
                                    <Line type="monotone" dataKey="auth_success" stroke="#10b981" strokeWidth={2} name="Successful Auth" />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    {/* Event Breakdown */}
                    {eventBreakdownData.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Event Types (24h)</CardTitle>
                                <CardDescription>Distribution of security events</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ResponsiveContainer width="100%" height={250}>
                                    <PieChart>
                                        <Pie
                                            data={eventBreakdownData}
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={80}
                                            dataKey="value"
                                            label={({ name, value }) => `${name}: ${value}`}
                                        >
                                            {eventBreakdownData.map((entry, index) => (
                                                <Cell key={`cell-${index}`} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                            </CardContent>
                        </Card>
                    )}

                    {/* Top Threat IPs */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Top Threat Sources</CardTitle>
                            <CardDescription>Most active threat IP addresses</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {metrics.top_threat_ips.slice(0, 8).map((ip, index) => (
                                    <div key={index} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            {ip.blocked ? <Ban className="h-4 w-4 text-red-500" /> : <Eye className="h-4 w-4 text-yellow-500" />}
                                            <span className="font-mono text-sm">{ip.ip}</span>
                                        </div>
                                        <Badge variant={ip.blocked ? 'destructive' : 'secondary'}>{ip.count} events</Badge>
                                    </div>
                                ))}
                                {metrics.top_threat_ips.length === 0 && <p className="text-muted-foreground text-sm">No threat sources detected</p>}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Events */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span>Recent Security Events</span>
                            <Badge variant="outline">{events.length} events</Badge>
                        </CardTitle>
                        <CardDescription>Live stream of security events</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="max-h-[400px] space-y-2 overflow-y-auto">
                            {events.map((event, index) => (
                                <div key={`${event.id}-${index}`} className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <Badge variant={getSeverityColor(event.severity)}>{event.event_type.replace('_', ' ')}</Badge>
                                        <span className="font-mono text-sm">{event.ip_address}</span>
                                        {event.user_name && <span className="text-muted-foreground text-sm">{event.user_name}</span>}
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-sm">{new Date(event.created_at).toLocaleTimeString()}</div>
                                    </div>
                                </div>
                            ))}
                            {events.length === 0 && (
                                <div className="flex items-center justify-center py-8">
                                    <p className="text-muted-foreground">Waiting for security events...</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* System Health Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>System Health Metrics</CardTitle>
                        <CardDescription>Performance and system status indicators</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-2">
                                <div className="text-muted-foreground text-sm">Cache Hit Ratio</div>
                                <div className="text-2xl font-bold text-green-600">{metrics.system_health.cache_hit_ratio.toFixed(1)}%</div>
                            </div>
                            <div className="space-y-2">
                                <div className="text-muted-foreground text-sm">Avg Response Time</div>
                                <div className="text-2xl font-bold text-blue-600">{metrics.system_health.avg_response_time.toFixed(1)}ms</div>
                            </div>
                            <div className="space-y-2">
                                <div className="text-muted-foreground text-sm">Active Sessions</div>
                                <div className="text-2xl font-bold text-purple-600">{metrics.system_health.active_sessions}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="text-muted-foreground text-sm">Permission Checks/min</div>
                                <div className="text-2xl font-bold text-orange-600">{metrics.system_health.permission_checks_per_minute}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
