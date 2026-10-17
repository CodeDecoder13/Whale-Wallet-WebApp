<?php

namespace Tests\Unit;

use App\Services\Forecast\ForecastService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForecastServiceTest extends TestCase
{
    private const BASE = [
        'balance' => 1_000_000, 'today' => '2026-03-10', 'pay_dates' => [15, 30],
        'outflows' => [], 'spend' => [], 'card_limits' => [], 'pod_monthly' => 0,
    ];

    /** Name => [input overrides, expected payday, days, lowest date, lowest amount, balance at payday, safe per day]. */
    public static function cases(): array
    {
        $spend = fn (string $date, int $amount) => ['date' => $date, 'card' => 1, 'amount' => $amount];

        return [
            'zero history' => [[], '2026-03-15', 5, '2026-03-10', 1_000_000, 1_000_000, 200_000],
            'payday is tomorrow' => [
                ['today' => '2026-03-14', 'spend' => [$spend('2026-03-13', 72_000)]],
                '2026-03-15', 1, '2026-03-15', 996_000, 996_000, 996_000,
            ],
            'payday is today, no pods' => [['today' => '2026-03-15'], '2026-03-30', 15, '2026-03-15', 1_000_000, 1_000_000, 66_666],
            'pod contribution on payday' => [
                ['today' => '2026-03-15', 'pod_monthly' => 600_000],
                '2026-03-30', 15, '2026-03-15', 700_000, 700_000, 46_666,
            ],
            'pods are not taken the day before payday' => [
                ['today' => '2026-03-14', 'pod_monthly' => 600_000],
                '2026-03-15', 1, '2026-03-14', 1_000_000, 1_000_000, 1_000_000,
            ],
            'bill on the 27th, later bill ignored' => [
                ['today' => '2026-03-20', 'balance' => 2_000_000, 'outflows' => [
                    ['date' => '2026-03-27', 'amount' => 900_000], ['date' => '2026-04-27', 'amount' => 900_000],
                ]],
                '2026-03-30', 10, '2026-03-27', 1_100_000, 1_100_000, 110_000,
            ],
            'bill on payday is the lowest day' => [
                ['today' => '2026-03-20', 'balance' => 2_000_000, 'spend' => [$spend('2026-03-19', 144_000)],
                    'outflows' => [['date' => '2026-03-30', 'amount' => 500_000]]],
                '2026-03-30', 10, '2026-03-30', 1_420_000, 1_420_000, 142_000,
            ],
            'bill pushes balance negative, safe is floored at 0' => [
                ['today' => '2026-03-20', 'balance' => 100_000, 'outflows' => [['date' => '2026-03-25', 'amount' => 500_000]]],
                '2026-03-30', 10, '2026-03-25', -400_000, -400_000, 0,
            ],
            'February payday clamps to the 28th' => [
                ['today' => '2026-02-10', 'pay_dates' => [30], 'balance' => 1_800_000],
                '2026-02-28', 18, '2026-02-10', 1_800_000, 1_800_000, 100_000,
            ],
            'from the 31st into February' => [
                ['today' => '2026-01-31', 'balance' => 1_500_000],
                '2026-02-15', 15, '2026-01-31', 1_500_000, 1_500_000, 100_000,
            ],
            'pay day 31 is payday on the 30th of a 30-day month' => [
                ['today' => '2026-04-30', 'pay_dates' => [15, 31], 'balance' => 1_500_000, 'pod_monthly' => 600_000],
                '2026-05-15', 15, '2026-04-30', 1_200_000, 1_200_000, 80_000,
            ],
            'weighted history: recent weeks count more, days 0 and 31+ ignored' => [
                ['today' => '2026-03-20', 'balance' => 2_000_000, 'spend' => [
                    $spend('2026-03-19', 72_000), $spend('2026-03-10', 72_000), $spend('2026-02-28', 72_000),
                    $spend('2026-02-18', 72_000), $spend('2026-03-20', 999_999), $spend('2026-02-17', 999_999),
                ]],
                '2026-03-30', 10, '2026-03-30', 1_900_000, 1_900_000, 190_000,
            ],
            'daily average rounds half up' => [
                ['spend' => [$spend('2026-03-09', 100)]],
                '2026-03-15', 5, '2026-03-15', 999_970, 999_970, 199_994,
            ],
            'no card limit' => [
                ['today' => '2026-03-20', 'balance' => 2_000_000, 'spend' => [$spend('2026-03-19', 144_000)]],
                '2026-03-30', 10, '2026-03-30', 1_920_000, 1_920_000, 192_000,
            ],
            'card limit caps spending' => [
                ['today' => '2026-03-20', 'balance' => 2_000_000, 'spend' => [$spend('2026-03-19', 144_000)], 'card_limits' => [1 => 25_000]],
                '2026-03-30', 10, '2026-03-24', 1_975_000, 1_975_000, 197_500,
            ],
        ];
    }

    #[DataProvider('cases')]
    public function test_forecast(array $override, string $payday, int $days, string $lowDate, int $lowAmount, int $atPayday, int $safe)
    {
        $out = (new ForecastService)->forecast([...self::BASE, ...$override]);

        $this->assertSame($payday, $out['payday']);
        $this->assertSame($days, $out['days']);
        $this->assertCount($days + 1, $out['series']);
        $this->assertSame(['date' => $lowDate, 'amount' => $lowAmount], $out['lowest']);
        $this->assertSame($atPayday, $out['at_payday']);
        $this->assertSame($safe, $out['safe_per_day']);
    }
}
