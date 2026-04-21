import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarCheck,
    CreditCard,
    Gift,
    Globe,
    LayoutDashboard,
    Megaphone,
    Menu,
    ScrollText,
    Settings,
} from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import backoffice from '@/routes/backoffice';
import type { SharedData } from '@/types';
import type { LucideIcon } from 'lucide-react';

type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
};

type NavSection = {
    label: string;
    items: NavItem[];
};

export function BottomNav() {
    const [moreOpen, setMoreOpen] = useState(false);
    const currentUrl = usePage<SharedData>().url;

    function isActive(href: string) {
        return currentUrl === href || currentUrl.startsWith(href + '/');
    }

    const primaryItems: NavItem[] = [
        { title: 'Dashboard', href: backoffice.index.url(), icon: LayoutDashboard },
        { title: 'Bookings', href: backoffice.concierge.booking.index.url(), icon: CalendarCheck },
        { title: 'Transactions', href: backoffice.concierge.transaction.index.url(), icon: CreditCard },
    ];

    const sections: NavSection[] = [
        {
            label: 'Menu',
            items: [
                { title: 'Dashboard', href: backoffice.index.url(), icon: LayoutDashboard },
            ],
        },
        {
            label: 'Concierge',
            items: [
                { title: 'Bookings', href: backoffice.concierge.booking.index.url(), icon: CalendarCheck },
                { title: 'Offers', href: backoffice.concierge.offer.index.url(), icon: Gift },
                { title: 'Transactions', href: backoffice.concierge.transaction.index.url(), icon: CreditCard },
                { title: 'Upsell Logs', href: backoffice.concierge.upsellLog.index.url(), icon: Megaphone },
                { title: 'Reports', href: backoffice.concierge.report.index.url(), icon: BarChart3 },
                { title: 'System Logs', href: backoffice.concierge.systemLog.index.url(), icon: ScrollText },
                { title: 'Webhook Logs', href: backoffice.concierge.webhookLog.index.url(), icon: Globe },
            ],
        },
        {
            label: 'Settings',
            items: [
                { title: 'Settings', href: backoffice.setting.setting.index.url(), icon: Settings },
            ],
        },
    ];

    const primaryHrefs = new Set(primaryItems.map((item) => item.href));
    const moreIsActive = sections
        .flatMap((s) => s.items)
        .some((item) => !primaryHrefs.has(item.href) && isActive(item.href));

    return (
        <>
            <nav className="fixed inset-x-0 bottom-0 z-50 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden">
                <div className="flex items-center justify-around">
                    {primaryItems.map((item) => {
                        const Icon = item.icon;
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] transition-colors',
                                    active
                                        ? 'text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <Icon className="size-5" strokeWidth={active ? 2.5 : 2} />
                                <span>{item.title}</span>
                            </Link>
                        );
                    })}
                    <button
                        type="button"
                        onClick={() => setMoreOpen(true)}
                        className={cn(
                            'flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] transition-colors',
                            moreIsActive || moreOpen
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        <Menu className="size-5" strokeWidth={moreIsActive || moreOpen ? 2.5 : 2} />
                        <span>More</span>
                    </button>
                </div>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    showCloseButton={false}
                    className="rounded-t-2xl pt-3 pb-[env(safe-area-inset-bottom)]"
                >
                    <SheetHeader className="sr-only">
                        <SheetTitle>Navigation</SheetTitle>
                    </SheetHeader>
                    <div className="mx-auto h-1 w-10 rounded-full bg-muted" />
                    <div className="space-y-4 px-1 pb-2">
                        {sections.map((section) => (
                            <div key={section.label}>
                                <p className="mb-1 px-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {section.label}
                                </p>
                                <div className="space-y-0.5">
                                    {section.items.map((item) => {
                                        const Icon = item.icon;
                                        const active = isActive(item.href);
                                        return (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={() => setMoreOpen(false)}
                                                className={cn(
                                                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                                                    active
                                                        ? 'bg-accent font-medium text-accent-foreground'
                                                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                                )}
                                            >
                                                <Icon className="size-4" />
                                                {item.title}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
