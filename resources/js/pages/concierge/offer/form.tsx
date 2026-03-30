import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { FormResponse } from '@/lib/constant';
import { index, store, update } from '@/routes/backoffice/concierge/offer';
import type { Offer } from '@/types/offer';

const categories = ['wellness', 'dining', 'experience', 'transport'];

type Props = { offer?: Offer };

export default function OfferForm({ offer }: Props) {
    const { data, setData, post, put, errors, processing } = useForm({
        offer_code: offer?.offer_code ?? '',
        title: offer?.title ?? '',
        description: offer?.description ?? '',
        category: offer?.category ?? categories[0],
        timing_rule: offer?.timing_rule ?? '',
        price: offer?.price ?? '',
        currency: offer?.currency ?? 'MAD',
        is_active: offer?.is_active ?? true,
        max_sends_per_stay: offer?.max_sends_per_stay ?? 1,
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (offer) {
            put(update(offer.id).url, FormResponse);
        } else {
            post(store().url, FormResponse);
        }
    };

    return (
        <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <h1 className="text-xl font-semibold">{offer ? 'Edit Offer' : 'New Offer'}</h1>
            <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label>Offer Code</Label>
                        <Input value={data.offer_code} onChange={(e) => setData('offer_code', e.target.value)} />
                        <InputError message={errors?.offer_code} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Title</Label>
                        <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        <InputError message={errors?.title} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Category</Label>
                        <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {categories.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.category} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Timing Rule</Label>
                        <Input value={data.timing_rule} onChange={(e) => setData('timing_rule', e.target.value)} placeholder="e.g. arrival_day, day_2, day_1_before_checkout" />
                        <p className="text-xs text-muted-foreground">Use: arrival_day, day_2, day_3, day_4, day_1_before_checkout</p>
                        <InputError message={errors?.timing_rule} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Price</Label>
                        <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                        <InputError message={errors?.price} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Currency</Label>
                        <Input value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        <InputError message={errors?.currency} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Max Sends Per Stay</Label>
                        <Input type="number" min={1} max={10} value={data.max_sends_per_stay} onChange={(e) => setData('max_sends_per_stay', Number(e.target.value))} />
                        <InputError message={errors?.max_sends_per_stay} />
                    </div>
                    <div className="flex items-center gap-3 pt-6">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                        <Label>Active</Label>
                    </div>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Description</Label>
                    <Textarea rows={5} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    <InputError message={errors?.description} />
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Button type="button" variant="outline" onClick={() => router.visit(index().url)}>Cancel</Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save
                    </Button>
                </div>
            </form>
        </div>
    );
}

OfferForm.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
