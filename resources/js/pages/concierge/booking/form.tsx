import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { FormResponse } from '@/lib/constant';
import { index, store, update } from '@/routes/backoffice/concierge/booking';
import type { Booking } from '@/types/booking';

const suites = ['Suite Al Andalus', 'Suite Zitoun', 'Suite Atlas', 'Suite Menara'];
const statuses = ['confirmed', 'checked_in', 'checked_out', 'cancelled'];
const sources = ['Airbnb', 'Direct', 'Booking.com'];

type Props = { booking?: Booking };

export default function BookingForm({ booking }: Props) {
    const { data, setData, post, put, errors, processing } = useForm({
        lodgify_booking_id: booking?.lodgify_booking_id ?? '',
        guest_name: booking?.guest_name ?? '',
        guest_phone: booking?.guest_phone ?? '',
        guest_email: booking?.guest_email ?? '',
        guest_nationality: booking?.guest_nationality ?? '',
        num_guests: booking?.num_guests ?? 1,
        suite_name: booking?.suite_name ?? suites[0],
        check_in: booking?.check_in ?? '',
        check_out: booking?.check_out ?? '',
        num_nights: booking?.num_nights ?? 1,
        booking_source: booking?.booking_source ?? sources[0],
        booking_status: booking?.booking_status ?? 'confirmed',
        total_amount: booking?.total_amount ?? '',
        currency: booking?.currency ?? 'MAD',
        special_requests: booking?.special_requests ?? '',
        internal_notes: booking?.internal_notes ?? '',
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (booking) {
            put(update(booking.id).url, FormResponse);
        } else {
            post(store().url, FormResponse);
        }
    };

    return (
        <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <h1 className="text-xl font-semibold">{booking ? 'Edit Booking' : 'New Booking'}</h1>
            <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label>Lodgify Booking ID</Label>
                        <Input value={data.lodgify_booking_id} onChange={(e) => setData('lodgify_booking_id', e.target.value)} />
                        <InputError message={errors?.lodgify_booking_id} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Guest Name</Label>
                        <Input value={data.guest_name} onChange={(e) => setData('guest_name', e.target.value)} />
                        <InputError message={errors?.guest_name} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Phone</Label>
                        <Input value={data.guest_phone} onChange={(e) => setData('guest_phone', e.target.value)} />
                        <InputError message={errors?.guest_phone} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Email</Label>
                        <Input type="email" value={data.guest_email} onChange={(e) => setData('guest_email', e.target.value)} />
                        <InputError message={errors?.guest_email} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Nationality</Label>
                        <Input value={data.guest_nationality} onChange={(e) => setData('guest_nationality', e.target.value)} />
                        <InputError message={errors?.guest_nationality} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Number of Guests</Label>
                        <Input type="number" min={1} value={data.num_guests} onChange={(e) => setData('num_guests', Number(e.target.value))} />
                        <InputError message={errors?.num_guests} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Suite</Label>
                        <Select value={data.suite_name} onValueChange={(v) => setData('suite_name', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {suites.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.suite_name} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Check In</Label>
                        <Input type="date" value={data.check_in} onChange={(e) => setData('check_in', e.target.value)} />
                        <InputError message={errors?.check_in} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Check Out</Label>
                        <Input type="date" value={data.check_out} onChange={(e) => setData('check_out', e.target.value)} />
                        <InputError message={errors?.check_out} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Nights</Label>
                        <Input type="number" min={1} value={data.num_nights} onChange={(e) => setData('num_nights', Number(e.target.value))} />
                        <InputError message={errors?.num_nights} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Source</Label>
                        <Select value={data.booking_source} onValueChange={(v) => setData('booking_source', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {sources.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.booking_source} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Status</Label>
                        <Select value={data.booking_status} onValueChange={(v) => setData('booking_status', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => <SelectItem key={s} value={s}>{s.replace('_', ' ')}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.booking_status} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Total Amount</Label>
                        <Input type="number" step="0.01" value={data.total_amount} onChange={(e) => setData('total_amount', e.target.value)} />
                        <InputError message={errors?.total_amount} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Currency</Label>
                        <Input value={data.currency} onChange={(e) => setData('currency', e.target.value)} />
                        <InputError message={errors?.currency} />
                    </div>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Special Requests</Label>
                    <Textarea rows={3} value={data.special_requests} onChange={(e) => setData('special_requests', e.target.value)} />
                    <InputError message={errors?.special_requests} />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Internal Notes</Label>
                    <Textarea rows={3} value={data.internal_notes} onChange={(e) => setData('internal_notes', e.target.value)} />
                    <InputError message={errors?.internal_notes} />
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

BookingForm.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
