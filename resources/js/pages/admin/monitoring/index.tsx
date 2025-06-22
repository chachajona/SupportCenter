import { PermissionGate } from '@/components/rbac/permission-gate';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Activity, AlertTriangle, Clock, Download, Server, Zap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface PermissionCheck {
    id: string;
    user_id: number;
    user_name: string;
    permission: string;
    resource: string;
    result: 'granted' | 'denied';
    response_time: number;
    ip_address: string;
    timestamp: string;
    department?: string;
}

interface SystemMetrics {
    cpu_usage: number;
    memory_usage: number;
    cache_hit_ratio: number;
    active_connections: number;
    queries_per_second: number;
    average_response_time: number;
}

interface SecurityEvent {
    id: string;
    type: 'failed_login' | 'permission_denied' | 'suspicious_activity' | 'rate_limit_exceeded';
    severity: 'low' | 'medium' | 'high' | 'critical';
    user_id?: number;
    user_name?: string;
    details: string;
    ip_address: string;
    timestamp: string;
}

interface RealTimeData {
    recent_checks: PermissionCheck[];
    system_metrics: SystemMetrics;
    security_events: SecurityEvent[];
    performance_history: Array<{
        timestamp: string;
        response_time: number;
        checks_per_second: number;
        cache_hits: number;
    }>;
    alerts: Array<{
        id: string;
        type: 'warning' | 'error' | 'info';
        message: string;
        timestamp: string;
    }>;
}

interface RealtimeMonitoringProps {
    initialData: RealTimeData;
}

export default function RealtimeMonitoring({ initialData }: RealtimeMonitoringProps) {
    const [data] = useState<RealTimeData>(initialData);
    const [isLive, setIsLive] = useState(true);
    const intervalRef = useRef<NodeJS.Timeout | null>(null);

    // Initialize WebSocket connection for real-time updates
    useEffect(() => {
        if (!isLive) return;

        // In a production environment, use WebSocket for real-time updates
        // For this demo, we'll use polling
        intervalRef.current = setInterval(() => {
            router.reload({ only: ['initialData'] });
        }, 2000); // Update every 2 seconds

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
            }
        };
    }, [isLive]);

    const toggleMonitoring = () => {
        setIsLive(!isLive);
    };

    const getStatusBadge = (result: string) => {
        return result === 'granted' ? (
            <Badge variant="default" className="bg-green-100 text-green-800">
                Granted
            </Badge>
        ) : (
            <Badge variant="destructive">Denied</Badge>
        );
    };

    const getSeverityColor = (severity: string) => {
        switch (severity) {
            case 'critical':
                return 'border-red-500 bg-red-50';
            case 'high':
                return 'border-orange-500 bg-orange-50';
            case 'medium':
                return 'border-yellow-500 bg-yellow-50';
            case 'low':
                return 'border-blue-500 bg-blue-50';
            default:
                return 'border-gray-500 bg-gray-50';
        }
    };

    const getMetricStatus = (value: number, threshold: number, inverted = false) => {
        const isHigh = inverted ? value < threshold : value > threshold;
        return isHigh ? 'text-red-600' : 'text-green-600';
    };

    const formatTimestamp = (timestamp: string) => {
        return new Date(timestamp).toLocaleTimeString();
    };

    const exportLogs = () => {
        const logs = {
            permission_checks: data.recent_checks,
            security_events: data.security_events,
            system_metrics: data.system_metrics,
            exported_at: new Date().toISOString(),
        };

        const blob = new Blob([JSON.stringify(logs, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `rbac-monitoring-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Monitoring', href: '/admin/monitoring' } as BreadcrumbItem]}>
            <Head title="Real-time Permission Monitoring" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Real-time Permission Monitoring</h1>
                            <p className="text-muted-foreground">Live monitoring of permission checks and system security</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2">
                                <Switch checked={isLive} onCheckedChange={toggleMonitoring} id="live-monitoring" />
                                <label htmlFor="live-monitoring" className="text-sm font-medium">
                                    Live Monitoring
                                </label>
                                {isLive && (
                                    <div className="flex items-center gap-1">
                                        <div className="h-2 w-2 animate-pulse rounded-full bg-green-500" />
                                        <span className="text-xs text-green-600">Live</span>
                                    </div>
                                )}
                            </div>

                            <PermissionGate permission="monitoring.export">
                                <Button variant="outline" onClick={exportLogs} className="gap-2">
                                    <Download className="h-4 w-4" />
                                    Export Logs
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>

                    {/* System Status Cards */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Response Time</CardTitle>
                                <Clock className="h-4 w-4 text-blue-600" />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${getMetricStatus(data.system_metrics.average_response_time, 10)}`}>
                                    {data.system_metrics.average_response_time}ms
                                </div>
                                <p className="text-muted-foreground text-xs">Average permission check</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Cache Hit Ratio</CardTitle>
                                <Zap className="h-4 w-4 text-green-600" />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${getMetricStatus(data.system_metrics.cache_hit_ratio, 95, true)}`}>
                                    {data.system_metrics.cache_hit_ratio}%
                                </div>
                                <p className="text-muted-foreground text-xs">Cache efficiency</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Active Connections</CardTitle>
                                <Server className="h-4 w-4 text-purple-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{data.system_metrics.active_connections}</div>
                                <p className="text-muted-foreground text-xs">Concurrent users</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Queries/sec</CardTitle>
                                <Activity className="h-4 w-4 text-amber-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{data.system_metrics.queries_per_second}</div>
                                <p className="text-muted-foreground text-xs">Database load</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* System Alerts */}
                    {data.alerts.length > 0 && (
                        <div className="space-y-2">
                            {data.alerts.map((alert) => (
                                <Alert key={alert.id} variant={alert.type === 'error' ? 'destructive' : 'default'}>
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        <span className="font-medium">{formatTimestamp(alert.timestamp)}</span> - {alert.message}
                                    </AlertDescription>
                                </Alert>
                            ))}
                        </div>
                    )}

                    <Tabs defaultValue="permission-checks" className="space-y-6">
                        <TabsList>
                            <TabsTrigger value="permission-checks">Permission Checks</TabsTrigger>
                            <TabsTrigger value="security-events">Security Events</TabsTrigger>
                            <TabsTrigger value="performance">Performance</TabsTrigger>
                        </TabsList>

                        <TabsContent value="permission-checks" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Live Permission Checks</CardTitle>
                                    <CardDescription>
                                        Real-time view of permission verification requests
                                        {isLive && <span className="ml-2 text-green-600">● Live</span>}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="max-h-96 space-y-2 overflow-y-auto">
                                        {data.recent_checks.map((check) => (
                                            <div
                                                key={check.id}
                                                className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 text-sm"
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="text-muted-foreground font-mono text-xs">{formatTimestamp(check.timestamp)}</div>
                                                    <div className="font-medium">{check.user_name}</div>
                                                    <div className="text-muted-foreground">{check.permission}</div>
                                                    <div className="text-muted-foreground text-xs">{check.ip_address}</div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="font-mono text-xs">{check.response_time}ms</div>
                                                    {getStatusBadge(check.result)}
                                                </div>
                                            </div>
                                        ))}
                                        {data.recent_checks.length === 0 && (
                                            <p className="text-muted-foreground py-8 text-center">No recent permission checks</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="security-events" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Security Events</CardTitle>
                                    <CardDescription>Real-time security monitoring and alerts</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="max-h-96 space-y-3 overflow-y-auto">
                                        {data.security_events.map((event) => (
                                            <div key={event.id} className={`rounded-lg border-l-4 p-4 ${getSeverityColor(event.severity)}`}>
                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{event.type.replace(/_/g, ' ').toUpperCase()}</span>
                                                            <Badge
                                                                variant={
                                                                    event.severity === 'critical' || event.severity === 'high'
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {event.severity.toUpperCase()}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground text-sm">{event.details}</p>
                                                        {event.user_name && (
                                                            <p className="text-muted-foreground text-xs">
                                                                User: {event.user_name} • IP: {event.ip_address}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">{formatTimestamp(event.timestamp)}</div>
                                                </div>
                                            </div>
                                        ))}
                                        {data.security_events.length === 0 && (
                                            <p className="text-muted-foreground py-8 text-center">No security events detected</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="performance" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Performance Trends</CardTitle>
                                    <CardDescription>Real-time performance metrics over time</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={300}>
                                        <LineChart data={data.performance_history}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="timestamp" tickFormatter={(value: string) => new Date(value).toLocaleTimeString()} />
                                            <YAxis yAxisId="left" />
                                            <YAxis yAxisId="right" orientation="right" />
                                            <Tooltip labelFormatter={(value: string) => new Date(value).toLocaleString()} />
                                            <Line
                                                yAxisId="left"
                                                type="monotone"
                                                dataKey="response_time"
                                                stroke="#8884d8"
                                                name="Response Time (ms)"
                                                strokeWidth={2}
                                            />
                                            <Line
                                                yAxisId="right"
                                                type="monotone"
                                                dataKey="checks_per_second"
                                                stroke="#82ca9d"
                                                name="Checks/sec"
                                                strokeWidth={2}
                                            />
                                            <Line
                                                yAxisId="right"
                                                type="monotone"
                                                dataKey="cache_hits"
                                                stroke="#ffc658"
                                                name="Cache Hits %"
                                                strokeWidth={2}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>

                            {/* System Resource Usage */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>CPU Usage</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span>Current</span>
                                                <span className={`font-bold ${getMetricStatus(data.system_metrics.cpu_usage, 80)}`}>
                                                    {data.system_metrics.cpu_usage}%
                                                </span>
                                            </div>
                                            <div className="h-2 w-full rounded-full bg-gray-200">
                                                <div
                                                    className={`h-2 rounded-full ${data.system_metrics.cpu_usage > 80 ? 'bg-red-500' : 'bg-green-500'}`}
                                                    style={{ width: `${data.system_metrics.cpu_usage}%` }}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Memory Usage</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span>Current</span>
                                                <span className={`font-bold ${getMetricStatus(data.system_metrics.memory_usage, 85)}`}>
                                                    {data.system_metrics.memory_usage}%
                                                </span>
                                            </div>
                                            <div className="h-2 w-full rounded-full bg-gray-200">
                                                <div
                                                    className={`h-2 rounded-full ${data.system_metrics.memory_usage > 85 ? 'bg-red-500' : 'bg-green-500'}`}
                                                    style={{ width: `${data.system_metrics.memory_usage}%` }}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}
