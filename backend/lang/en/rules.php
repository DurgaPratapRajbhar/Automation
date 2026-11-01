<?php

// Messages for the custom rules in app/Rules
return [
    'gstin' => 'The :attribute must be a valid 15-character GSTIN.',
    'phone' => 'The :attribute must be 10 to 15 digits.',
    'readable_text' => 'The :attribute must be readable text, without strange or binary characters.',
    'knowledge_file' => 'This is not a readable PDF, Word (docx) or text file. Please upload the original file.',
    'upi_id' => 'The :attribute must be a valid UPI ID, like name@bank.',
    'gstin_state_mismatch' => 'GSTIN does not match the state code.',
];
