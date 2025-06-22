import { PermissionGate } from '@/components/rbac/permission-gate';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Activity, AlertTriangle, Download, RefreshCw, Shield, TrendingUp, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    BarChart as RechartsBarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface AnalyticsData {
    overview: {
        total_users: number;
        total_roles: number;
        total_permissions: number;
        active_sessions: number;
        permission_changes_24h: number;
        security_events_24h: number;
        average_response_time: number;
        cache_hit_ratio: number;
    };
    roleDistribution: Array<{ name: string; value: number; color: string }>;
    permissionUsage: Array<{ name: string; count: number; percentage: number }>;
    activityTrends: Array<{ date: string; logins: number; permission_changes: number; security_events: number }>;
    performanceMetrics: Array<{ timestamp: string; response_time: number; cache_hits: number; active_users: number }>;
    securityEvents: Array<{ type: string; count: number; severity: 'low' | 'medium' | 'high' | 'critical' }>;
    departmentAnalytics: Array<{ department: string; users: number; roles: number; activities: number }>;
    complianceMetrics: {
        gdpr_compliance_score: number;
        soc2_readiness: number;
        owasp_alignment: number;
        audit_coverage: number;
    };
}

interface RBACAnalyticsDashboardProps {
    analytics: AnalyticsData;
    timeRange: string;
}

export default function RBACAnalyticsDashboard({ analytics, timeRange: initialTimeRange }: RBACAnalyticsDashboardProps) {
    const [timeRange, setTimeRange] = useState(initialTimeRange || '7d');
    const [activeTab, setActiveTab] = useState('overview');
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [data, setData] = useState<AnalyticsData>(analytics);

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analytics', href: '/admin/analytics' }];

    useEffect(() => {
        if (!autoRefresh) return;

        const refresh = async () => {
            try {
                const res = await fetch(`/admin/analytics/refresh?range=${timeRange}`);
                if (res.ok) {
                    const payload = await res.json();
                    // Merge selective keys; keep others intact
                    setData((prev) => ({
                        ...prev,
                        overview: {
                            ...prev.overview,
                            ...payload.overview,
                        },
                    }));
                }
            } catch {
                // Ignore network failure – will retry next tick
            }
        };

        const interval = setInterval(refresh, 60_000); // 60 s
        return () => clearInterval(interval);
    }, [autoRefresh, timeRange]);

    const handleTimeRangeChange = (newRange: string) => {
        setTimeRange(newRange);
        router.get(window.location.pathname, { range: newRange }, { preserveState: true });
    };

    const handleExport = () => {
        window.open(`/admin/analytics/export?range=${timeRange}`, '_blank');
    };

    const getComplianceColor = (score: number) => {
        if (score >= 90) return 'text-green-600';
        if (score >= 75) return 'text-yellow-600';
        return 'text-red-600';
    };

    const getSeverityColor = (severity: string) => {
        switch (severity) {
            case 'critical':
                return 'bg-red-500';
            case 'high':
                return 'bg-orange-500';
            case 'medium':
                return 'bg-yellow-500';
            case 'low':
                return 'bg-blue-500';
            default:
                return 'bg-gray-500';
        }
    };

    const formatNumber = (num: number) => {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RBAC Analytics Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">RBAC Analytics Dashboard</h1>
                            <p className="text-muted-foreground">Comprehensive insights into your access control system</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Select value={timeRange} onValueChange={handleTimeRangeChange}>
                                <SelectTrigger className="w-[120px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1d">Last 24h</SelectItem>
                                    <SelectItem value="7d">Last 7 days</SelectItem>
                                    <SelectItem value="30d">Last 30 days</SelectItem>
                                    <SelectItem value="90d">Last 90 days</SelectItem>
                                </SelectContent>
                            </Select>

                            <Button
                                variant="outline"
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                className={`gap-2 ${autoRefresh ? 'bg-green-50 text-green-700' : ''}`}
                            >
                                <Activity className="h-4 w-4" />
                                {autoRefresh ? 'Auto-refresh On' : 'Auto-refresh Off'}
                            </Button>

                            <PermissionGate permission="analytics.export">
                                <Button variant="outline" className="gap-2" onClick={handleExport}>
                                    <Download className="h-4 w-4" />
                                    Export Report
                                </Button>
                            </PermissionGate>

                            <PermissionGate permission="analytics.refresh">
                                <Button variant="outline" onClick={() => router.reload({ only: ['analytics'] })} className="gap-2">
                                    <RefreshCw className="h-4 w-4" />
                                    Refresh
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>

                    <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                        <TabsList className="grid w-full grid-cols-6">
                            <TabsTrigger value="overview">Overview</TabsTrigger>
                            <TabsTrigger value="users">Users & Roles</TabsTrigger>
                            <TabsTrigger value="permissions">Permissions</TabsTrigger>
                            <TabsTrigger value="security">Security</TabsTrigger>
                            <TabsTrigger value="performance">Performance</TabsTrigger>
                            <TabsTrigger value="compliance">Compliance</TabsTrigger>
                        </TabsList>

                        <TabsContent value="overview" className="space-y-6">
                            {/* Key Metrics Overview */}
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                                        <Users className="h-4 w-4 text-blue-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{formatNumber(data.overview.total_users)}</div>
                                        <p className="text-muted-foreground text-xs">Active in system</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Active Sessions</CardTitle>
                                        <Activity className="h-4 w-4 text-green-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{formatNumber(data.overview.active_sessions)}</div>
                                        <p className="text-muted-foreground text-xs">Currently online</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Permission Changes</CardTitle>
                                        <Shield className="h-4 w-4 text-amber-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{data.overview.permission_changes_24h}</div>
                                        <p className="text-muted-foreground text-xs">Last 24 hours</p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Response Time</CardTitle>
                                        <TrendingUp className="h-4 w-4 text-purple-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{data.overview.average_response_time}ms</div>
                                        <p className="text-muted-foreground text-xs">Average latency</p>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* System Health Alerts */}
                            {data.overview.cache_hit_ratio < 95 && (
                                <Alert>
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        Cache hit ratio is below optimal threshold ({data.overview.cache_hit_ratio}%). Consider reviewing cache
                                        configuration.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Activity Trends Chart */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Activity Trends</CardTitle>
                                    <CardDescription>System activity over the selected time period</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={300}>
                                        <AreaChart data={data.activityTrends}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="date" />
                                            <YAxis />
                                            <Tooltip />
                                            <Area type="monotone" dataKey="logins" stackId="1" stroke="#8884d8" fill="#8884d8" />
                                            <Area type="monotone" dataKey="permission_changes" stackId="1" stroke="#82ca9d" fill="#82ca9d" />
                                            <Area type="monotone" dataKey="security_events" stackId="1" stroke="#ffc658" fill="#ffc658" />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="users" className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                {/* Role Distribution */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Role Distribution</CardTitle>
                                        <CardDescription>User distribution across different roles</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={300}>
                                            <PieChart>
                                                <Pie
                                                    data={data.roleDistribution}
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={80}
                                                    fill="#8884d8"
                                                    dataKey="value"
                                                    label={({ name, value }) => `${name}: ${value}`}
                                                >
                                                    {data.roleDistribution.map((entry, index) => (
                                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                </Card>

                                {/* Department Analytics */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Department Analytics</CardTitle>
                                        <CardDescription>Activity breakdown by department</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {data.departmentAnalytics.map((dept, index) => (
                                                <div key={index} className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">{dept.department}</span>
                                                        <div className="text-muted-foreground flex items-center gap-4 text-sm">
                                                            <span>{dept.users} users</span>
                                                            <span>{dept.roles} roles</span>
                                                            <span>{dept.activities} activities</span>
                                                        </div>
                                                    </div>
                                                    <Progress
                                                        value={
                                                            (dept.activities / Math.max(...data.departmentAnalytics.map((d) => d.activities))) * 100
                                                        }
                                                        className="h-2"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        <TabsContent value="permissions" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Permission Usage Analysis</CardTitle>
                                    <CardDescription>Most frequently used permissions</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={400}>
                                        <RechartsBarChart data={data.permissionUsage}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="name" angle={-45} textAnchor="end" height={100} />
                                            <YAxis />
                                            <Tooltip />
                                            <Bar dataKey="count" fill="#8884d8" />
                                        </RechartsBarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="security" className="space-y-6">
                            <div className="grid gap-6">
                                {/* Security Events Overview */}
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    {data.securityEvents.map((event, index) => (
                                        <Card key={index}>
                                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                                <CardTitle className="text-sm font-medium">{event.type}</CardTitle>
                                                <div className={`h-3 w-3 rounded-full ${getSeverityColor(event.severity)}`} />
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{event.count}</div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            event.severity === 'critical' || event.severity === 'high' ? 'destructive' : 'secondary'
                                                        }
                                                    >
                                                        {event.severity.toUpperCase()}
                                                    </Badge>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>

                                {/* Security Alert */}
                                {data.securityEvents.some((e) => e.severity === 'critical' && e.count > 0) && (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>Critical security events detected. Immediate attention required.</AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        </TabsContent>

                        <TabsContent value="performance" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Performance Metrics</CardTitle>
                                    <CardDescription>System performance over time</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={400}>
                                        <LineChart data={data.performanceMetrics}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="timestamp" />
                                            <YAxis yAxisId="left" />
                                            <YAxis yAxisId="right" orientation="right" />
                                            <Tooltip />
                                            <Legend />
                                            <Line yAxisId="left" type="monotone" dataKey="response_time" stroke="#8884d8" name="Response Time (ms)" />
                                            <Line yAxisId="right" type="monotone" dataKey="active_users" stroke="#82ca9d" name="Active Users" />
                                            <Line yAxisId="right" type="monotone" dataKey="cache_hits" stroke="#ffc658" name="Cache Hits %" />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="compliance" className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Compliance Scores</CardTitle>
                                        <CardDescription>Current compliance status across frameworks</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">GDPR Compliance</span>
                                                <span className={`font-bold ${getComplianceColor(data.complianceMetrics.gdpr_compliance_score)}`}>
                                                    {data.complianceMetrics.gdpr_compliance_score}%
                                                </span>
                                            </div>
                                            <Progress value={data.complianceMetrics.gdpr_compliance_score} className="h-2" />
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">SOC 2 Readiness</span>
                                                <span className={`font-bold ${getComplianceColor(data.complianceMetrics.soc2_readiness)}`}>
                                                    {data.complianceMetrics.soc2_readiness}%
                                                </span>
                                            </div>
                                            <Progress value={data.complianceMetrics.soc2_readiness} className="h-2" />
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">OWASP Alignment</span>
                                                <span className={`font-bold ${getComplianceColor(data.complianceMetrics.owasp_alignment)}`}>
                                                    {data.complianceMetrics.owasp_alignment}%
                                                </span>
                                            </div>
                                            <Progress value={data.complianceMetrics.owasp_alignment} className="h-2" />
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">Audit Coverage</span>
                                                <span className={`font-bold ${getComplianceColor(data.complianceMetrics.audit_coverage)}`}>
                                                    {data.complianceMetrics.audit_coverage}%
                                                </span>
                                            </div>
                                            <Progress value={data.complianceMetrics.audit_coverage} className="h-2" />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Compliance Recommendations</CardTitle>
                                        <CardDescription>Actions to improve compliance scores</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {data.complianceMetrics.gdpr_compliance_score < 90 && (
                                            <div className="border-l-4 border-yellow-500 pl-4">
                                                <h4 className="font-medium">GDPR Improvement</h4>
                                                <p className="text-muted-foreground text-sm">
                                                    Review data retention policies and user consent mechanisms.
                                                </p>
                                            </div>
                                        )}

                                        {data.complianceMetrics.soc2_readiness < 85 && (
                                            <div className="border-l-4 border-orange-500 pl-4">
                                                <h4 className="font-medium">SOC 2 Preparation</h4>
                                                <p className="text-muted-foreground text-sm">Enhance access controls and monitoring capabilities.</p>
                                            </div>
                                        )}

                                        {data.complianceMetrics.owasp_alignment < 80 && (
                                            <div className="border-l-4 border-red-500 pl-4">
                                                <h4 className="font-medium">OWASP Security</h4>
                                                <p className="text-muted-foreground text-sm">
                                                    Address authentication and authorization vulnerabilities.
                                                </p>
                                            </div>
                                        )}

                                        {data.complianceMetrics.audit_coverage < 95 && (
                                            <div className="border-l-4 border-blue-500 pl-4">
                                                <h4 className="font-medium">Audit Enhancement</h4>
                                                <p className="text-muted-foreground text-sm">Increase audit log coverage and retention periods.</p>
                                            </div>
                                        )}
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
