<?php

// Ready-made first messages shown when a user opens a support ticket. Picking one fills the message box (they can edit it);
// "Something else" is simply the empty box. The label becomes the ticket's subject.
return [
    'templates' => [
        'balance' => ['label' => 'My safe-to-spend or balance looks wrong', 'text' => "My safe-to-spend / balance looks wrong.\nWhat I see: \nWhat I expected: "],
        'loan' => ['label' => 'Help with a loan, bill or subscription', 'text' => "I need help with a loan, bill or subscription.\nWhich one: \nWhat is wrong: "],
        'receipt' => ['label' => 'A receipt scan failed or read it wrong', 'text' => "A receipt scan failed or was read wrong.\nMerchant and date: \nWhat happened: "],
        'signin' => ['label' => "I can't sign in or I forgot my PIN", 'text' => "I can't sign in / I forgot my PIN.\nWhat happens when I try: "],
        'account' => ['label' => 'I want to reset or delete my account', 'text' => "I want to reset or delete my account.\nWhich one and why: "],
        'bug' => ['label' => 'Something is not working', 'text' => "Something is not working.\nWhere in the app: \nWhat I did, and what happened: "],
    ],

    // attachments: images and PDFs, up to 5 MB
    'file_rules' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
];
