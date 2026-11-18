<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertPruningTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Alert> */
    private array $alerts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();

        $this->alerts = [
            'unread, 91 days' => $this->alert(91),
            'unread, 89 days' => $this->alert(89),
            'unread, 45 days' => $this->alert(45),
            'read, 31 days' => $this->alert(31, read: true),
            'read, 29 days' => $this->alert(29, read: true),
        ];
    }

    public function test_it_prunes_unread_alerts_after_90_days_and_read_ones_after_30(): void
    {
        $this->assertPrunable(['unread, 91 days', 'read, 31 days']);
    }

    public function test_the_retention_windows_come_from_config(): void
    {
        config(['alerts.retention_days' => 60, 'alerts.read_retention_days' => 20]);

        $this->assertPrunable(['unread, 91 days', 'unread, 89 days', 'read, 31 days', 'read, 29 days']);
    }

    public function test_model_prune_deletes_exactly_the_prunable_alerts(): void
    {
        $this->artisan('model:prune', ['--model' => [Alert::class]])->assertSuccessful();

        $this->assertModelMissing($this->alerts['unread, 91 days']);
        $this->assertModelMissing($this->alerts['read, 31 days']);
        $this->assertModelExists($this->alerts['unread, 89 days']);
        $this->assertModelExists($this->alerts['unread, 45 days']);
        $this->assertModelExists($this->alerts['read, 29 days']);
    }

    /**
     * The no-re-alert guarantee only lasts as long as the alert row does.
     */
    public function test_a_listing_alerts_again_if_it_comes_back_after_its_alert_was_pruned(): void
    {
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $search = SavedSearch::factory()->daily()->forCriteria(['region' => 'Leeds'])->create();
        $listing = Listing::factory()->live()->for($leeds)->create();
        $original = Alert::query()->where('listing_id', $listing->id)->sole();

        $listing->update(['status' => ListingStatus::UnderOffer]);
        $this->travel(91)->days();
        $this->artisan('model:prune', ['--model' => [Alert::class]]);
        $listing->update(['status' => ListingStatus::Live]);

        $this->assertModelMissing($original);
        $this->assertSame($search->id, Alert::query()->where('listing_id', $listing->id)->sole()->saved_search_id);
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertPrunable(array $expected): void
    {
        $expectedIds = collect($expected)->map(fn (string $key) => $this->alerts[$key]->id)->sort()->values()->all();

        $this->assertSame($expectedIds, (new Alert)->prunable()->orderBy('id')->pluck('id')->all());
    }

    private function alert(int $daysOld, bool $read = false): Alert
    {
        $createdAt = now()->subDays($daysOld);

        return Alert::factory()->create([
            'created_at' => $createdAt,
            'read_at' => $read ? $createdAt->copy()->addDay() : null,
        ]);
    }
}
