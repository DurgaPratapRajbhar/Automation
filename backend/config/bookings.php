<?php

return [
    // A booking of a service with no length of its own takes this long
    'default_minutes' => 60,

    // Free times are offered every this many minutes, for this many days ahead, and this many are suggested
    'step_minutes' => 30,
    'look_ahead_days' => 14,
    'suggestions' => 3,

    // A reminder is suggested when a confirmed booking is this many hours away or less
    'reminder_hours_before' => 24,

    // Feedback is asked this many hours after a booking ended, and not for bookings older than this many days
    'feedback_after_hours' => 2,
    'feedback_max_days' => 2,

    // An agent does not suggest the same follow-up for the same booking again within this many days
    'quiet_days' => 3,

    // Day names the settings use, in the order of Carbon's dayOfWeek (0 = Sunday)
    'days' => ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
];
