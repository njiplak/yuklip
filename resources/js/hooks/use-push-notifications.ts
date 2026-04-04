import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { SharedData } from '@/types';

type PushState = 'loading' | 'unsupported' | 'denied' | 'prompt' | 'subscribed';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

export function usePushNotifications() {
    const { vapidPublicKey } = usePage<SharedData>().props;
    const [state, setState] = useState<PushState>('loading');

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setState('unsupported');
            return;
        }

        if (Notification.permission === 'denied') {
            setState('denied');
            return;
        }

        navigator.serviceWorker.ready.then((registration) => {
            registration.pushManager.getSubscription().then((sub) => {
                setState(sub ? 'subscribed' : 'prompt');
            });
        });
    }, []);

    const subscribe = useCallback(async () => {
        if (!vapidPublicKey) return;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            setState('denied');
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey) as BufferSource,
        });

        const json = subscription.toJSON();

        await fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: json.keys,
            }),
        });

        setState('subscribed');
    }, [vapidPublicKey]);

    return { state, subscribe };
}
