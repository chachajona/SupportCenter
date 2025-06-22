import { TicketVolumeOverview } from '@/components/dashboard/ticket-volume-overview';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Ticket Volume Overview Section */}
                <div className="space-y-2">
                    <h2 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Ticket Volume</h2>
                    <TicketVolumeOverview />
                </div>

                {/* Responsive Bento Grid Layout */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
                    {/* Main Feature Card - Large */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 row-span-2 aspect-square overflow-hidden rounded-xl border md:col-span-2 md:row-span-2">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Activity Feed - Wide */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 aspect-video overflow-hidden rounded-xl border md:col-span-2">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Quick Action Card - Square */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-1 aspect-square overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Notification Card - Square */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-1 aspect-square overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Performance Metrics - Tall */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 row-span-2 overflow-hidden rounded-xl border md:col-span-1 md:row-span-2">
                        <div className="aspect-[4/3] md:aspect-[1/2]">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </div>

                    {/* Recent Items - Medium */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-1 aspect-square overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* System Status - Medium */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-1 aspect-square overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Calendar/Events - Wide */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 aspect-video overflow-hidden rounded-xl border md:col-span-3">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    {/* Analytics Summary - Wide */}
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 aspect-video overflow-hidden rounded-xl border md:col-span-3">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
