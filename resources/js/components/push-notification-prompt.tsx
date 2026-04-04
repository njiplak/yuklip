import { Bell, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';

const DISMISSED_KEY = 'push-prompt-dismissed';

export function PushNotificationPrompt() {
    const { state, subscribe } = usePushNotifications();
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (state !== 'prompt') return;
        if (localStorage.getItem(DISMISSED_KEY)) return;
        setVisible(true);
    }, [state]);

    if (!visible) return null;

    function dismiss() {
        setVisible(false);
        localStorage.setItem(DISMISSED_KEY, '1');
    }

    async function handleEnable() {
        await subscribe();
        setVisible(false);
    }

    return (
        <div className="fixed right-4 bottom-4 z-50 flex max-w-sm items-start gap-3 rounded-lg border bg-card p-4 shadow-lg">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                <Bell className="h-5 w-5 text-primary" />
            </div>
            <div className="flex-1">
                <p className="text-sm font-medium">
                    Enable notifications
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Get instant alerts for new bookings, upsells, and
                    guest requests.
                </p>
                <div className="mt-3 flex gap-2">
                    <Button size="sm" onClick={handleEnable}>
                        Enable
                    </Button>
                    <Button size="sm" variant="ghost" onClick={dismiss}>
                        Not now
                    </Button>
                </div>
            </div>
            <button
                onClick={dismiss}
                className="text-muted-foreground hover:text-foreground"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
