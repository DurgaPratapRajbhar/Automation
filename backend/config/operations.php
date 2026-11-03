<?php

return [
    // The most of one product in one order (a typo or a mischievous message is not an order)
    'max_quantity' => 1000,

    // How many products are sent to the assistant at most (the ones the customer talks about first)
    'max_products' => 40,

    // The bot answers a chat at most this many messages a minute; more are saved but not answered (a flood costs money)
    'chat_messages_per_minute' => 10,

    // How many of the latest messages the assistant reads
    'history' => 10,

    // A message with none of these words (or a product's name) is not about buying, and goes to the support assistant
    'hints' => [
        'price', 'rate', 'cost', 'kitna', 'kitne', 'kitni', 'daam', 'available', 'stock', 'milega', 'milegi',
        'order', 'buy', 'purchase', 'chahiye', 'chahie', 'book', 'booking', 'appointment', 'slot', 'quantity', 'piece', 'pcs',
        // The same in Hindi script
        'रेट', 'कीमत', 'दाम', 'कितना', 'कितने', 'कितनी', 'चाहिए', 'चाहिये', 'ऑर्डर', 'आर्डर', 'बुक', 'बुकिंग', 'उपलब्ध', 'स्टॉक', 'मिलेगा', 'मिलेगी', 'खरीदना',
    ],

    // A message with one of these (anywhere in it) from a customer who has an open order asks to cancel it
    'cancel_words' => ['cancel', 'cancle', 'radd', 'nahi chahiye', 'nahin chahiye', 'रद्द', 'कैंसल', 'कैन्सल', 'नहीं चाहिए', 'नहीं चाहिये'],

    // The owner is asked about a cancel request of the same order only once in this many hours
    'cancel_ask_hours' => 24,

    // Once the orders assistant has spoken, a short answer ("haan", "ok", "2") stays with it for this many minutes
    'talk_minutes' => 30,
];
