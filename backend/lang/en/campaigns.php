<?php

return [
    'created' => 'Campaign is ready. Check it, then start it.',
    'started' => 'Campaign started. Messages are going out one by one.',
    'cancelled' => 'Campaign stopped. Messages not yet sent will not be sent.',
    'not_found' => 'Campaign not found.',
    'not_draft' => 'Only a campaign that has not started can be started.',
    'not_sending' => 'Only a campaign that is sending can be stopped.',
    'channel_not_found' => 'This WhatsApp number is not connected to your business.',
    'nobody_to_send' => 'Nobody can get this message. People need to be saved with WhatsApp permission, have a phone number, and every blank of the message must have a value.',
    'test_only_draft' => 'A test can only be sent before the campaign starts.',
    'test_bad_phone' => 'That phone number is not valid. Write 10 digits, or the number with its country code.',
    'test_numbers_only' => 'Sending is limited to test numbers right now, so this message was not sent.',
    'test_name' => 'Test message',
    'opted_out' => 'This person asked us to stop messaging them.',
    'missing_value' => ':column is empty',
];
