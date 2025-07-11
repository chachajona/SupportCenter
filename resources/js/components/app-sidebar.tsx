import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Activity, AlertTriangle, BarChart, BookOpen, BrainIcon, Clock, Folder, LayoutGrid, Shield, Users, WorkflowIcon } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'AI Features',
        href: '/ai-dashboard',
        icon: BrainIcon,
    },
    {
        title: 'Workflows',
        href: '/workflows',
        icon: WorkflowIcon,
    },
    {
        title: 'Analytics',
        href: '/admin/analytics',
        icon: BarChart,
    },
    {
        title: 'Audit',
        href: '/admin/audit',
        icon: Shield,
    },
    {
        title: 'Monitoring',
        href: '/admin/monitoring',
        icon: Activity,
    },
    {
        title: 'Emergency',
        href: '/admin/emergency',
        icon: AlertTriangle,
    },
    {
        title: 'Temporal Access',
        href: '/admin/temporal',
        icon: Clock,
    },
    {
        title: 'Roles',
        href: '/admin/roles',
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
