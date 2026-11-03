'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { saveOrderSettings } from '@/features/shop/orders/actions';

const DAY_NAMES = { sun: 'Sun', mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat' };

// How the Orders assistant works for this business
export default function OrderSettingsForm({ profile, canEdit }) {
  const [state, formAction, pending] = useActionState(saveOrderSettings, {});
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <fieldset disabled={!canEdit} className="space-y-3">
        <label className="flex items-start gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" name="ask_delivery_address" defaultChecked={profile.ask_delivery_address} className="mt-0.5 h-4 w-4" />
          <span>I deliver to the customer. Ask for the address before an order is noted.</span>
        </label>

        <label className="flex items-start gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" name="owner_alerts" defaultChecked={profile.owner_alerts} className="mt-0.5 h-4 w-4" />
          <span>
            Send me a WhatsApp message on my phone number (above) for a new order and a hot lead.
            <span className="block font-normal text-slate-500">WhatsApp lets this through only if you wrote to your business number in the last 24 hours. It is always in the app too.</span>
          </span>
        </label>

        <div className="space-y-3 border-t border-slate-200 pt-4">
          <p className="text-sm font-medium text-slate-700">Bookings (appointments, visits, classes, tables): when customers can book</p>
          <div className="grid gap-3 sm:grid-cols-3">
            <Input label="Opens" name="booking_open_time" type="time" defaultValue={profile.booking_open_time} error={errors.booking_open_time} required />
            <Input label="Closes" name="booking_close_time" type="time" defaultValue={profile.booking_close_time} error={errors.booking_close_time} required />
            <Input label="At the same time" name="booking_capacity" type="number" min="1" max="50" defaultValue={profile.booking_capacity} error={errors.booking_capacity} required />
          </div>
          <label className="flex items-start gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="auto_booking_followups" defaultChecked={profile.auto_booking_followups} className="mt-0.5 h-4 w-4" />
            <span>
              Remind customers before their booking, and ask for feedback after it, by itself.
              <span className="block font-normal text-slate-500">Off: each one waits for your Yes in Approvals. WhatsApp lets this through only if the customer wrote in the last 24 hours; otherwise it shows under Problems.</span>
            </span>
          </label>
          <p className="text-xs text-slate-500">“At the same time” = how many bookings can happen together (chairs, doctors, tables, staff).</p>
          <div className="flex flex-wrap gap-x-4 gap-y-2">
            <span className="w-full text-sm text-slate-700">Closed on</span>
            {profile.booking_days.map((day) => (
              <label key={day} className="flex items-center gap-1.5 text-sm">
                <input type="checkbox" name="booking_closed_days" value={day} defaultChecked={profile.booking_closed_days.includes(day)} className="h-4 w-4" />
                {DAY_NAMES[day]}
              </label>
            ))}
          </div>
        </div>
      </fieldset>

      {canEdit && <Button pending={pending}>Save</Button>}
    </form>
  );
}
