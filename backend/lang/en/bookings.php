<?php

return [
    'default_what' => 'booking',
    'close_after_open' => 'The closing time must be after the opening time.',
    'reminder' => 'Namaste :name, a reminder: your :what is on :when. Please reply here if you need to change it. See you!',
    'feedback' => 'Namaste :name, thank you for coming! How was your :what? Please reply with a rating from 1 (poor) to 5 (excellent), and tell us what we can do better.',
    'remind_summary' => 'Remind :name about :what on :when',
    'feedback_summary' => 'Ask :name for feedback on :what',
    'unavailable' => 'Sorry, :when is not available: :reason. :options',
    'options' => 'Free times: :times. Which one suits you?',
    'no_options' => 'Please tell me another day and time.',
    'reason' => [
        'past' => 'that time has already passed',
        'closed_day' => 'we are closed that day',
        'outside_hours' => 'it is outside our working hours (:open to :close)',
        'full' => 'that time is already booked',
    ],
];
