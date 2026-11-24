<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_does_not_duplicate_the_defaults()
    {
        $user = User::factory()->create();

        Category::seedFor($user);
        Category::seedFor($user);

        $this->assertSame(count(Category::DEFAULTS), $user->categories()->count());
    }
}
