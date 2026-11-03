'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

// The Orders assistant's settings: two switches (a checkbox that is not ticked is not sent, so each is read as on / off)
// and the booking hours
export async function saveOrderSettings(previousState, formData) {
  const body = {
    ask_delivery_address: formData.get('ask_delivery_address') === 'on',
    owner_alerts: formData.get('owner_alerts') === 'on',
    auto_booking_followups: formData.get('auto_booking_followups') === 'on',
    booking_open_time: formData.get('booking_open_time'),
    booking_close_time: formData.get('booking_close_time'),
    booking_closed_days: formData.getAll('booking_closed_days'),
    booking_capacity: formData.get('booking_capacity'),
  };

  const { ok, data } = await backend('workspace/profile', { method: 'PUT', body });

  if (!ok) return formError(data, body);

  revalidatePath('/settings/profile');

  return { message: data.message, success: true };
}

// One change to an order: { delivery_status: 'on_the_way' | 'delivered' } (the customer is told), { paid: true }, { status: 'cancelled' }
export async function updateOrder(orderId, changes) {
  const { data } = await backend(`orders/${orderId}`, { method: 'PUT', body: changes });

  revalidatePath('/orders');

  // A change that was kept but could not be told to the customer still comes back with the reason
  return { message: data.message };
}
