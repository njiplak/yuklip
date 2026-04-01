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
import { index, store, update } from '@/routes/backoffice/concierge/menu-item';
import type { MenuItem } from '@/types/menu-item';

const categories = ['breakfast', 'lunch', 'dinner', 'drinks', 'snacks'];

type Props = { menuItem?: MenuItem };

export default function MenuItemForm({ menuItem }: Props) {
    const { data, setData, post, put, errors, processing } = useForm({
        name: menuItem?.name ?? '',
        name_fr: menuItem?.name_fr ?? '',
        category: menuItem?.category ?? categories[0],
        description: menuItem?.description ?? '',
        price: menuItem?.price ?? '',
        currency: menuItem?.currency ?? 'MAD',
        is_available: menuItem?.is_available ?? true,
        availability_note: menuItem?.availability_note ?? '',
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (menuItem) {
            put(update(menuItem.id).url, FormResponse);
        } else {
            post(store().url, FormResponse);
        }
    };

    return (
        <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <h1 className="text-xl font-semibold">{menuItem ? 'Edit Menu Item' : 'New Menu Item'}</h1>
            <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label>Name</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Name (French)</Label>
                        <Input value={data.name_fr} onChange={(e) => setData('name_fr', e.target.value)} placeholder="Optional" />
                        <InputError message={errors?.name_fr} />
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
                        <Label>Price</Label>
                        <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} placeholder="Leave empty if included" />
                        <InputError message={errors?.price} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Currency</Label>
                        <Input value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        <InputError message={errors?.currency} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Availability Note</Label>
                        <Input value={data.availability_note} onChange={(e) => setData('availability_note', e.target.value)} placeholder="e.g. Seasonal — June to September" />
                        <InputError message={errors?.availability_note} />
                    </div>
                    <div className="flex items-center gap-3 pt-6">
                        <Checkbox checked={data.is_available} onCheckedChange={(v) => setData('is_available', v === true)} />
                        <Label>Available</Label>
                    </div>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Description</Label>
                    <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
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

MenuItemForm.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
