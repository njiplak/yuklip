import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    CreditCard,
    Folder,
    Gift,
    LayoutGrid,
    MessageSquare,
    ScrollText,
    Settings,
} from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as dashboardIndex } from '@/routes/backoffice';
import { index as bookingIndex } from '@/routes/backoffice/concierge/booking';
import { index as offerIndex } from '@/routes/backoffice/concierge/offer';
import { index as transactionIndex } from '@/routes/backoffice/concierge/transaction';
import { index as upsellLogIndex } from '@/routes/backoffice/concierge/upsell-log';
import { index as systemLogIndex } from '@/routes/backoffice/concierge/system-log';
import { index as settingIndex } from '@/routes/backoffice/setting/setting';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboardIndex(),
        icon: LayoutGrid,
    },
    {
        title: 'Bookings',
        href: bookingIndex(),
        icon: CalendarCheck,
    },
    {
        title: 'Offers',
        href: offerIndex(),
        icon: Gift,
    },
    {
        title: 'Upsell Logs',
        href: upsellLogIndex(),
        icon: MessageSquare,
    },
    {
        title: 'Transactions',
        href: transactionIndex(),
        icon: CreditCard,
    },
    {
        title: 'System Logs',
        href: systemLogIndex(),
        icon: ScrollText,
    },
    {
        title: 'Settings',
        href: settingIndex(),
        icon: Settings,
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
                            <Link href={dashboardIndex()} prefetch>
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
