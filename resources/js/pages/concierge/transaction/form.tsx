import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { FormResponse } from '@/lib/constant';
import { index, store, update } from '@/routes/backoffice/concierge/transaction';
import type { Transaction } from '@/types/transaction';

const types = ['income', 'expense'];
const categories = ['room_revenue', 'upsell', 'supplies', 'staff', 'maintenance'];
const paymentMethods = ['cash', 'card', 'bank_transfer'];

type Props = { transaction?: Transaction };

export default function TransactionForm({ transaction }: Props) {
    const { data, setData, post, put, errors, processing } = useForm({
        booking_id: transaction?.booking_id ?? '',
        type: transaction?.type ?? types[0],
        category: transaction?.category ?? categories[0],
        description: transaction?.description ?? '',
        amount: transaction?.amount ?? '',
        currency: transaction?.currency ?? 'MAD',
        transaction_date: transaction?.transaction_date ?? '',
        payment_method: transaction?.payment_method ?? '',
        reference: transaction?.reference ?? '',
        recorded_by: transaction?.recorded_by ?? '',
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (transaction) {
            put(update(transaction.id).url, FormResponse);
        } else {
            post(store().url, FormResponse);
        }
    };

    return (
        <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <h1 className="text-xl font-semibold">{transaction ? 'Edit Transaction' : 'New Transaction'}</h1>
            <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label>Type</Label>
                        <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {types.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.type} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Category</Label>
                        <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {categories.map((c) => <SelectItem key={c} value={c}>{c.replace('_', ' ')}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.category} />
                    </div>
                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                        <Label>Description</Label>
                        <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors?.description} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Amount</Label>
                        <Input type="number" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                        <InputError message={errors?.amount} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Currency</Label>
                        <Input value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        <InputError message={errors?.currency} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Date</Label>
                        <Input type="date" value={data.transaction_date} onChange={(e) => setData('transaction_date', e.target.value)} />
                        <InputError message={errors?.transaction_date} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Payment Method</Label>
                        <Select value={data.payment_method} onValueChange={(v) => setData('payment_method', v)}>
                            <SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger>
                            <SelectContent>
                                {paymentMethods.map((m) => <SelectItem key={m} value={m}>{m.replace('_', ' ')}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.payment_method} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Reference</Label>
                        <Input value={data.reference} onChange={(e) => setData('reference', e.target.value)} placeholder="Invoice or receipt number" />
                        <InputError message={errors?.reference} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Recorded By</Label>
                        <Input value={data.recorded_by} onChange={(e) => setData('recorded_by', e.target.value)} />
                        <InputError message={errors?.recorded_by} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Booking ID</Label>
                        <Input type="number" value={data.booking_id} onChange={(e) => setData('booking_id', e.target.value)} placeholder="Optional" />
                        <InputError message={errors?.booking_id} />
                    </div>
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

TransactionForm.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
