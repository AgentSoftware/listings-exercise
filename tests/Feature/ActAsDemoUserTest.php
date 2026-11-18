<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ActAsDemoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reports both ways of reading the user, which must always agree.
        Route::middleware('web')->get('/_test/whoami', fn (Request $request) => [
            'request' => $request->user()?->id,
            'auth' => auth()->id(),
        ]);
    }

    public function test_the_demo_user_is_used_when_no_one_is_authenticated(): void
    {
        $demo = User::factory()->create();
        User::factory()->create();

        $this->get('/_test/whoami')->assertExactJson(['request' => $demo->id, 'auth' => $demo->id]);
    }

    public function test_an_authenticated_user_takes_precedence_over_the_demo_user(): void
    {
        User::factory()->create(); // would be the demo user
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get('/_test/whoami')
            ->assertExactJson(['request' => $other->id, 'auth' => $other->id]);
    }

    public function test_an_unseeded_database_gives_a_guest_without_errors(): void
    {
        $this->get('/_test/whoami')->assertExactJson(['request' => null, 'auth' => null]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user', null));

        // User-only pages send a guest to the listings page, not a missing login route.
        $this->get('/saved-searches')->assertRedirect('/');
    }
}
