<?php

/**
 * Regenerates the synthetic receipt fixtures and expected.json (needs the GD extension).
 *   php tests/Fixtures/receipts/generate.php
 * The images are fake: made-up merchants, amounts and reference numbers. The fake AI provider answers from
 * expected.json by file name; `php artisan receipts:eval` checks the real model against the same file.
 * Dates sit in March 2026, so the parse job's "not older than a year" rule makes them stale after March 2027.
 */
$dir = __DIR__;

// [file stem, app, header colour, merchant, amount, date shown, ISO date, reference, category]
$receipts = [
    ['gcash-jollibee', 'GCash', [0, 112, 224], 'Jollibee', '250.00', 'Mar 09, 2026 12:30 PM', '2026-03-09 12:30', '5012 884 213', 'Food'],
    ['maya-grab', 'Maya', [18, 160, 90], 'Grab Philippines', '187.50', 'Mar 08, 2026 06:05 PM', '2026-03-08 18:05', 'MAYA-77120934', 'Transport'],
    ['bpi-meralco', 'BPI', [190, 30, 45], 'Meralco', '2,340.75', 'Mar 05, 2026 09:10 AM', '2026-03-05 09:10', 'BPI0305-889120', 'Bills'],
    ['gcash-7eleven', 'GCash', [0, 112, 224], '7-Eleven', '96.00', 'Mar 07, 2026 09:40 PM', '2026-03-07 21:40', '5012 771 090', 'Food'],
    ['maya-netflix', 'Maya', [18, 160, 90], 'Netflix', '549.00', 'Mar 04, 2026 07:00 AM', '2026-03-04 07:00', 'MAYA-66401277', 'Entertainment'],
    ['bpi-mercury', 'BPI', [190, 30, 45], 'Mercury Drug', '482.35', 'Mar 06, 2026 03:22 PM', '2026-03-06 15:22', 'BPI0306-120455', 'Health'],
    ['gcash-shopee', 'GCash', [0, 112, 224], 'Shopee', '1,299.00', 'Mar 02, 2026 08:15 PM', '2026-03-02 20:15', '5012 332 654', 'Shopping'],
    ['bdo-sm-supermarket', 'BDO', [0, 70, 150], 'SM Supermarket', '3,115.60', 'Mar 01, 2026 11:48 AM', '2026-03-01 11:48', 'BDO-2026030100918', 'Food'],
];

$expected = [];

foreach ($receipts as [$stem, $app, $rgb, $merchant, $amount, $shown, $iso, $ref, $category]) {
    [$w, $h] = [300, 420];
    $img = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink = imagecolorallocate($img, 30, 30, 30);
    $grey = imagecolorallocate($img, 120, 120, 120);
    imagefill($img, 0, 0, imagecolorallocate($img, 238, 240, 244));
    imagefilledrectangle($img, 15, 15, $w - 15, $h - 15, $white);
    imagefilledrectangle($img, 15, 15, $w - 15, 70, imagecolorallocate($img, ...$rgb));
    imagestring($img, 5, 30, 35, $app.'  |  Payment Successful', $white);

    $lines = [
        [$grey, 'Paid to'], [$ink, $merchant], [null, ''],
        [$grey, 'Amount'], [$ink, 'PHP '.$amount], [null, ''],
        [$grey, 'Date and time'], [$ink, $shown], [null, ''],
        [$grey, 'Reference No.'], [$ink, $ref], [null, ''],
        [$grey, 'Thank you for using '.$app],
    ];
    foreach ($lines as $i => [$colour, $text]) {
        if ($colour) {
            imagestring($img, 5, 30, 95 + $i * 22, $text, $colour);
        }
    }

    $big = imagecreatetruecolor($w * 2, $h * 2); // built-in GD fonts are tiny; 2x makes them readable
    imagecopyresampled($big, $img, 0, 0, 0, 0, $w * 2, $h * 2, $w, $h);
    imagepng($big, "$dir/$stem.png");

    $expected[$stem] = [
        'merchant' => $merchant, 'amount' => str_replace(',', '', $amount), 'currency' => 'PHP',
        'occurred_at' => $iso, 'reference' => $ref, 'category' => $category, 'payment_app' => $app, 'confidence' => 0.95,
    ];
}

file_put_contents("$dir/expected.json", json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo 'Wrote '.count($expected)." receipts to $dir\n";
