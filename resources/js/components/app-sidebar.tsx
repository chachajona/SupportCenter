import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useRBAC } from '@/contexts/RBACContext';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Activity, AlertTriangle, BarChart, BookOpen, Clock, Folder, HelpCircle, LayoutGrid, Shield, TicketIcon, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { hasPermission } = useRBAC();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    // Helpdesk navigation items with RBAC integration
    const helpdeskNavItems: NavItem[] = [
        {
            title: 'Tickets',
            href: '/tickets',
            icon: TicketIcon,
            permission: 'tickets.view_own',
        },
        {
            title: 'Knowledge Base',
            href: '/knowledge',
            icon: BookOpen,
            permission: 'knowledge.view_articles',
        },
        {
            title: 'Analytics',
            href: '/analytics',
            icon: BarChart,
            permission: 'analytics.view_department_analytics',
        },
    ].filter((item) => !item.permission || hasPermission(item.permission));

    // Admin navigation items
    const adminNavItems: NavItem[] = [
        {
            title: 'Analytics',
            href: '/admin/analytics',
            icon: BarChart,
            permission: 'analytics.view_all_analytics',
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
    ].filter((item) => !item.permission || hasPermission(item.permission));

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: Folder,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: HelpCircle,
        },
    ];

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
                {helpdeskNavItems.length > 0 && <NavMain items={helpdeskNavItems} groupLabel="Helpdesk" />}
                {adminNavItems.length > 0 && <NavMain items={adminNavItems} groupLabel="Administration" />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
