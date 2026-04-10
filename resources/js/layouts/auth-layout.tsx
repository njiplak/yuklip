import { Toaster } from '@/components/ui/sonner';

export default function AuthLayout({
    children,
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="flex min-h-svh flex-col lg:flex-row">
            <div className="hidden lg:flex lg:w-1/2 flex-col items-center justify-center bg-neutral-900 text-white">
                <div className="max-w-md text-center">
                    <h1 className="text-3xl font-bold tracking-tight">Yasmine.ai</h1>
                    <p className="mt-2 text-lg text-neutral-400">Intelligent vacation rental management</p>
                    <blockquote className="mt-8 text-sm leading-relaxed text-neutral-300">
                        "Your AI-powered property management console."
                    </blockquote>
                </div>
            </div>
            <div className="flex min-h-svh flex-1 flex-col p-4 lg:min-h-0 lg:w-1/2">
                <Toaster position="bottom-right" richColors />
                {children}
            </div>
        </div>
    );
}
