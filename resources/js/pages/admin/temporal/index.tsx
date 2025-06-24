import { RouteGuard } from '@/components/rbac/route-guard';
import { TemporalAccessRequest, TemporalAccessRequests } from '@/components/rbac/temporal-access-requests';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface TemporalAccessManagementProps {
    requests: TemporalAccessRequest[];
}

export default function TemporalAccessManagement({ requests }: TemporalAccessManagementProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Temporal Access', href: '/admin/temporal' },
    ];

    return (
        <RouteGuard permissions={['roles.approve_temporal', 'roles.deny_temporal']} requireAll={false}>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Temporal Access Management" />

                <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                    <TemporalAccessRequests requests={requests} onRequestUpdate={() => window.location.reload()} />
                </div>
            </AppLayout>
        </RouteGuard>
    );
}
