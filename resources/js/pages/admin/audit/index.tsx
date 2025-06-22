import { PermissionGate } from '@/components/rbac/permission-gate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePickerWithRange } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { AuditFilters, AuditStats, PermissionAudit } from '@/types/rbac';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Clock, Download, Eye, Filter, RefreshCw, Search, Shield, User } from 'lucide-react';
import React, { Suspense, lazy, useCallback, useState } from 'react';

interface AuditLogProps {
    audits: PermissionAudit[];
    stats: AuditStats;
    filters: AuditFilters;
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const AuditDetailDialog = lazy(() => import('@/components/rbac/audit-detail-dialog').then((m) => ({ default: m.AuditDetailDialog })));

export default function AuditLog({ audits, stats, filters, pagination }: AuditLogProps) {
    const [selectedAudit, setSelectedAudit] = useState<PermissionAudit | null>(null);
    const [localFilters, setLocalFilters] = useState<AuditFilters>(filters);
    const [isExporting, setIsExporting] = useState(false);

    // Debounce search to avoid excessive API calls
    const debouncedFilters = useDebounce(localFilters, 500);

    const applyFilters = useCallback(() => {
        router.get(
            '/admin/audit',
            { ...debouncedFilters },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }, [debouncedFilters]);

    React.useEffect(() => {
        applyFilters();
    }, [applyFilters]);

    const getActionBadgeVariant = (action: string) => {
        switch (action) {
            case 'granted':
                return 'default';
            case 'revoked':
                return 'destructive';
            case 'modified':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    const getRiskLevel = (audit: PermissionAudit): 'low' | 'medium' | 'high' => {
        if (audit.action === 'granted' && audit.permission?.name.includes('admin')) return 'high';
        if (audit.action === 'granted' && audit.permission?.name.includes('delete')) return 'medium';
        if (audit.action === 'revoked') return 'high';
        return 'low';
    };

    const getRiskBadge = (level: 'low' | 'medium' | 'high') => {
        const variants = {
            low: 'default' as const,
            medium: 'secondary' as const,
            high: 'destructive' as const,
        };
        return <Badge variant={variants[level]}>{level.toUpperCase()}</Badge>;
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const response = await fetch('/admin/audit/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(localFilters),
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `audit-log-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }
        } catch (error) {
            console.error('Export failed:', error);
        } finally {
            setIsExporting(false);
        }
    };

    const clearFilters = () => {
        setLocalFilters({});
    };

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audit Logs', href: '/admin/audit' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Security Audit Log</h1>
                            <p className="text-muted-foreground">Monitor and review all permission and role changes</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button variant="outline" onClick={() => router.reload()} className="gap-2">
                                <RefreshCw className="h-4 w-4" />
                                Refresh
                            </Button>

                            <PermissionGate permission="audit.export">
                                <Button variant="outline" onClick={handleExport} disabled={isExporting} className="gap-2">
                                    <Download className="h-4 w-4" />
                                    {isExporting ? 'Exporting...' : 'Export CSV'}
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Events</CardTitle>
                                <Shield className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_events.toLocaleString()}</div>
                                <p className="text-muted-foreground text-xs">All time</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Recent Activity</CardTitle>
                                <Clock className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.recent_events}</div>
                                <p className="text-muted-foreground text-xs">Last 24 hours</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">High Risk Events</CardTitle>
                                <AlertTriangle className="text-destructive h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-destructive text-2xl font-bold">{stats.high_risk_events}</div>
                                <p className="text-muted-foreground text-xs">Requires attention</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Active Users</CardTitle>
                                <User className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.active_users}</div>
                                <p className="text-muted-foreground text-xs">Made changes today</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Filter className="h-4 w-4" />
                                Filters
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Search User</label>
                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                        <Input
                                            placeholder="Search by username or email..."
                                            value={localFilters.user || ''}
                                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, user: e.target.value }))}
                                            className="pl-8"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Action Type</label>
                                    <Select
                                        value={localFilters.action || ''}
                                        onValueChange={(value: string) =>
                                            setLocalFilters((prev) => ({
                                                ...prev,
                                                action: value === 'all' ? undefined : (value as 'granted' | 'revoked' | 'modified'),
                                            }))
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All actions" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All actions</SelectItem>
                                            <SelectItem value="granted">Granted</SelectItem>
                                            <SelectItem value="revoked">Revoked</SelectItem>
                                            <SelectItem value="modified">Modified</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Date Range</label>
                                    <DatePickerWithRange
                                        from={localFilters.date_from ? new Date(localFilters.date_from) : undefined}
                                        to={localFilters.date_to ? new Date(localFilters.date_to) : undefined}
                                        onSelect={(range: { from?: Date; to?: Date } | undefined) => {
                                            setLocalFilters((prev) => ({
                                                ...prev,
                                                date_from: range?.from?.toISOString().split('T')[0],
                                                date_to: range?.to?.toISOString().split('T')[0],
                                            }));
                                        }}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Actions</label>
                                    <Button variant="outline" onClick={clearFilters} className="w-full">
                                        Clear Filters
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Audit Events */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Audit Events</CardTitle>
                            <CardDescription>
                                Showing {audits.length} of {pagination.total} events
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {audits.length > 0 ? (
                                    audits.map((audit) => {
                                        const riskLevel = getRiskLevel(audit);
                                        return (
                                            <div
                                                key={audit.id}
                                                className={cn(
                                                    'hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4 transition-colors',
                                                    riskLevel === 'high' && 'border-destructive/20 bg-destructive/5',
                                                )}
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="flex flex-col">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant={getActionBadgeVariant(audit.action)}>{audit.action.toUpperCase()}</Badge>
                                                            {getRiskBadge(riskLevel)}
                                                        </div>
                                                        <div className="text-muted-foreground mt-1 text-sm">
                                                            {new Date(audit.created_at).toLocaleString()}
                                                        </div>
                                                    </div>

                                                    <div className="flex-1">
                                                        <div className="font-medium">{audit.user?.name || 'Unknown User'}</div>
                                                        <div className="text-muted-foreground text-sm">
                                                            {audit.permission && <>Permission: {audit.permission.display_name}</>}
                                                            {audit.role && <>Role: {audit.role.display_name}</>}
                                                        </div>
                                                        {audit.reason && <div className="text-muted-foreground text-sm">Reason: {audit.reason}</div>}
                                                    </div>

                                                    <div className="text-muted-foreground text-sm">
                                                        By: {audit.performed_by_user?.name || 'System'}
                                                    </div>
                                                </div>

                                                <PermissionGate permission="audit.view_details">
                                                    <Button variant="ghost" size="sm" onClick={() => setSelectedAudit(audit)} className="gap-2">
                                                        <Eye className="h-4 w-4" />
                                                        Details
                                                    </Button>
                                                </PermissionGate>
                                            </div>
                                        );
                                    })
                                ) : (
                                    <div className="text-muted-foreground py-8 text-center">No audit events found matching your criteria.</div>
                                )}
                            </div>

                            {/* Pagination */}
                            {pagination.last_page > 1 && (
                                <div className="mt-6 flex items-center justify-between">
                                    <div className="text-muted-foreground text-sm">
                                        Page {pagination.current_page} of {pagination.last_page}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pagination.current_page === 1}
                                            onClick={() => router.get('/admin/audit', { ...localFilters, page: pagination.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pagination.current_page === pagination.last_page}
                                            onClick={() => router.get('/admin/audit', { ...localFilters, page: pagination.current_page + 1 })}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Suspense fallback={null}>
                <AuditDetailDialog audit={selectedAudit} open={!!selectedAudit} onClose={() => setSelectedAudit(null)} />
            </Suspense>
        </AppLayout>
    );
}
