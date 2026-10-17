<?php

namespace Tests\Unit;

use App\Actions\ParseQuickEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParseQuickEntryTest extends TestCase
{
    public static function inputs(): array
    {
        return [
            'plain' => ['Jollibee 250', ['merchant' => 'Jollibee', 'amount' => 25000, 'card' => null]],
            'thousands and centavos' => ['grab 1,250.50', ['merchant' => 'grab', 'amount' => 125050, 'card' => null]],
            'card hint' => ['coffee 95 gcash', ['merchant' => 'coffee', 'amount' => 9500, 'card' => 'gcash']],
            'multi-word merchant' => ['  Mang Inasal 189  ', ['merchant' => 'Mang Inasal', 'amount' => 18900, 'card' => null]],
            'digits in merchant' => ['7 eleven 55', ['merchant' => '7 eleven', 'amount' => 5500, 'card' => null]],
            'zero amount' => ['coffee 0', null],
            'no amount' => ['hello world', null],
            'no merchant' => ['250', null],
            'junk' => ['!!! ???', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_parse(string $text, ?array $expected)
    {
        $this->assertSame($expected, (new ParseQuickEntry)->handle($text));
    }
}
