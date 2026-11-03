<?php

return [
    // "Today" for a business in India; the model turns "kal" and "Friday" into dates from it
    'timezone' => 'Asia/Kolkata',

    // A late payment is not suggested for a reminder again within this many days
    'reminder_quiet_days' => 3,

    // How many of the latest messages the agent reads
    'history' => 6,

    // A message with none of these (a digit, which covers "10 Nov", or one of the words) cannot hold a dated promise: the AI is not asked
    'hints' => [
        'kal', 'parso', 'aaj', 'subah', 'shaam', 'raat', 'tak', 'baad', 'hafte', 'mahine', 'tarikh', 'tareekh',
        'payment', 'paisa', 'paise', 'rupaye', 'rupees', 'bhej', 'transfer', 'dunga', 'denge', 'karunga', 'karenge',
        'today', 'tomorrow', 'tonight', 'morning', 'evening',
        // The same in Hindi script (a digit is not needed)
        'कल', 'परसों', 'आज', 'सुबह', 'शाम', 'तक', 'पैसे', 'पैसा', 'रुपये', 'रुपए', 'पेमेंट', 'भुगतान', 'दूंगा', 'दूँगा', 'भेजूंगा', 'भेजूँगा',
        'सोमवार', 'मंगलवार', 'बुधवार', 'गुरुवार', 'शुक्रवार', 'शनिवार', 'रविवार',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
        'somvar', 'mangalvar', 'budhvar', 'guruvar', 'shukravar', 'shanivar', 'ravivar',
    ],
];
