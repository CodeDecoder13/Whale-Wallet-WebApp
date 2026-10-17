<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Pod;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetSummary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Pest is not installed (and no new deps allowed), so this is PHPUnit with a data provider.
class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_has_15844_pesos_after_fixed_costs()
    {
        $this->seed(DatabaseSeeder::class);

        $summary = (new BudgetSummary)->for(User::where('email', 'demo@example.com')->first());

        $this->assertSame(['income' => 3_500_000, 'fixed' => 1_915_600, 'remaining' => 1_584_400], $summary);
    }

    public static function models(): array
    {
        return array_map(fn ($m) => [$m], [
            IncomeSource::class, Card::class, Pod::class, Category::class, Transaction::class, RecurringItem::class,
        ]);
    }

    #[DataProvider('models')]
    public function test_user_cannot_see_another_users_rows(string $model)
    {
        [$a, $b] = User::factory(2)->create();
        $mine = $model::factory()->for($a)->create();
        $theirs = $model::factory()->for($b)->create();

        $this->actingAs($a);

        $this->assertSame([$mine->id], $model::pluck('id')->all());
        $this->assertNull($model::find($theirs->id));
    }
}
