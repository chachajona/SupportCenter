import { TicketVolumeOverview } from '@/components/dashboard/ticket-volume-overview';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { RocketIcon, TargetIcon, WorkflowIcon } from 'lucide-react';

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
                {/* AI & Automation Platform Banner */}
                <Card className="border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 dark:border-blue-800 dark:from-blue-950/20 dark:to-purple-950/20">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center space-x-2 text-blue-800 dark:text-blue-200">
                                    <RocketIcon className="h-6 w-6" />
                                    <span>🚀 AI & Automation Platform</span>
                                </CardTitle>
                                <CardDescription className="text-blue-600 dark:text-blue-300">
                                    Advanced AI features and workflow automation are now operational
                                </CardDescription>
                            </div>
                            <Badge className="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">Live & Testable</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Link href="/ai-dashboard">
                                <Card className="cursor-pointer border-green-200 bg-green-50 transition-shadow hover:shadow-md dark:border-green-800 dark:bg-green-950/20">
                                    <CardContent className="p-4">
                                        <div className="flex items-center space-x-3">
                                            <TargetIcon className="h-8 w-8 text-green-600 dark:text-green-400" />
                                            <div>
                                                <h3 className="font-semibold text-green-800 dark:text-green-200">AI Features</h3>
                                                <p className="text-sm text-green-600 dark:text-green-300">
                                                    Test AI categorization, semantic search & smart routing
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                            <Link href="/workflows">
                                <Card className="cursor-pointer border-purple-200 bg-purple-50 transition-shadow hover:shadow-md dark:border-purple-800 dark:bg-purple-950/20">
                                    <CardContent className="p-4">
                                        <div className="flex items-center space-x-3">
                                            <WorkflowIcon className="h-8 w-8 text-purple-600 dark:text-purple-400" />
                                            <div>
                                                <h3 className="font-semibold text-purple-800 dark:text-purple-200">Workflow Automation</h3>
                                                <p className="text-sm text-purple-600 dark:text-purple-300">
                                                    Visual drag-and-drop automation designer
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* Ticket Volume Overview Section */}
                <div className="space-y-2">
                    <h2 className="text-foreground text-lg font-semibold">Ticket Volume</h2>
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
