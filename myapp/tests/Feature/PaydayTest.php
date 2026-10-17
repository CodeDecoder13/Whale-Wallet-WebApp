<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaydayTest extends TestCase
{
    use RefreshDatabase;

    public function test_payday_can_be_changed_and_is_validated()
    {
        $user = User::factory()->create(['pay_dates' => [15]]);
        $this->actingAs($user);

        $this->patch(route('profile.payday'), ['pay_dates' => ['30', '15']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([[15, 30], 'semi_monthly'], [$user->fresh()->pay_dates, $user->fresh()->pay_cycle]);

        $this->patch(route('profile.payday'), ['pay_dates' => ['5']]);
        $this->assertSame('monthly', $user->fresh()->pay_cycle);

        $this->patch(route('profile.payday'), ['pay_dates' => ['32']])->assertSessionHasErrors('pay_dates.0');
        $this->patch(route('profile.payday'), ['pay_dates' => ['5', '5']])->assertSessionHasErrors();
        $this->assertSame([5], $user->fresh()->pay_dates);
    }

    public function test_avatar_is_a_small_jpeg_and_can_be_removed()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patch(route('profile.avatar'), ['avatar' => 'https://evil.test/me.png'])->assertSessionHasErrors('avatar');
        $this->patch(route('profile.avatar'), ['avatar' => 'data:image/jpeg;base64,AAAA'])->assertRedirect();
        $this->assertSame('data:image/jpeg;base64,AAAA', $user->fresh()->avatar);

        $this->patch(route('profile.avatar'), ['avatar' => null]);
        $this->assertNull($user->fresh()->avatar);
    }
}
