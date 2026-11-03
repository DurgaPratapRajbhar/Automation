<?php

return [
    'not_found' => 'Order not found.',
    'not_payable' => 'Only a confirmed order can be marked paid.',
    'not_cancellable' => 'This order is already cancelled.',
    'updated' => 'Saved.',
    'confirmed_payment' => "\nPay ₹:total on UPI to :upi\n:link",
    'not_deliverable' => 'Only a confirmed order of products can be sent for delivery.',
    'on_the_way' => 'Your order is on the way. Thank you for your patience!',
    'delivered' => 'Your order has been delivered. Thank you! Please tell us if anything is not right.',
    'delivery_updated' => 'Updated, and the customer was told.',
    'delivery_updated_not_told' => 'Updated, but the customer could not be told: :reason',
    'not_a_draft' => 'This order is already decided.',
    'not_enough_stock' => 'There is not enough stock of :name.',
    'confirmed' => "Your order is confirmed.\n:lines\nTotal: ₹:total:address:when:payment\nThank you!",
    'cancel_asked' => 'Okay, I have asked the shop to cancel your order. You will get a message once it is done.',
    'cancelled' => "Your order is cancelled.\n:lines\nIf this was a mistake, please write to us.",
    'confirmed_address' => "\nDelivery to: :address",
    'confirmed_booking' => "\nBooked for :at.",
];
