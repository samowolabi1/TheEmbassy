<?php

namespace Tests\Feature;

use App\Mail\PrayerReply;
use App\Models\Member;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberPrayerTest extends TestCase
{
    use RefreshDatabase;

    private Member $ada;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->ada = Member::factory()->active()->create(['name' => 'Sis. Ada Obi']);
    }

    private function ask(string $audience, array $extra = []): PrayerRequest
    {
        $this->actingAs($this->ada->user)->post(route('account.prayer.store'), [
            'category' => 'health', 'request' => 'Please pray for my mother\'s surgery on Friday.', 'audience' => $audience, ...$extra,
        ])->assertSessionHasNoErrors();

        return PrayerRequest::latest('id')->firstOrFail();
    }

    public function test_a_member_asks_and_the_prayer_team_replies(): void
    {
        $p = $this->ask('team');
        $this->assertSame([$this->ada->id, false, false, 'Sis. Ada Obi'], [$p->member_id, $p->is_private, $p->on_wall, $p->name]);

        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->get(route('admin.prayer-requests.index', ['show' => 'members']))->assertOk()->assertSee('mother')->assertSee('Health and healing');
        $this->post(route('admin.prayer-requests.reply', $p), ['body' => 'We are standing with you. Isaiah 41:10.'])->assertSessionHasNoErrors();
        $this->assertNotNull($p->fresh()->read_at);
        Mail::assertSent(PrayerReply::class, fn ($m) => $m->hasTo($this->ada->user->email));

        $this->actingAs($this->ada->user)->get(route('account.prayer'))->assertOk()->assertSee('Isaiah 41:10')->assertSee('Prayed for');

        // Answered: the member tells us, and can turn it into a testimony.
        $this->post(route('account.prayer.answered', $p), ['answer_note' => 'The surgery went perfectly.'])->assertSessionHas('answered', $p->id);
        $this->assertNotNull($p->fresh()->answered_at);
        $this->get(route('account.testimonies', ['prayer' => $p->id]))->assertOk()->assertSee('The surgery went perfectly.')->assertSee('value="healing" selected', false);

        // Home page requests have no account to reply to.
        $anon = PrayerRequest::create(['request' => 'Pray for me']);
        $this->actingAs($pastor)->post(route('admin.prayer-requests.reply', $anon), ['body' => 'Hi'])->assertStatus(422);
    }

    public function test_the_prayer_wall_is_for_members_and_counts_prayers_once(): void
    {
        $shared = $this->ask('church', ['anonymous' => '1']);
        $private = $this->ask('pastors', ['request' => 'Something only the pastors should know']);
        $this->assertSame([true, true], [$shared->on_wall, $shared->anonymous]);
        $this->assertSame([true, false], [$private->is_private, $private->on_wall]);

        $friend = Member::factory()->active()->create();
        $this->actingAs($friend->user)->get(route('account.prayer.wall'))->assertOk()
            ->assertSee('mother')->assertDontSee('Something only the pastors')->assertDontSee('Ada')->assertSee('A member of');
        $this->post(route('account.prayer.pray', $shared));
        $this->post(route('account.prayer.pray', $shared));
        $this->assertSame(1, $shared->fresh()->prayed_count);
        $this->get(route('account.prayer.wall'))->assertSee('You prayed');
        $this->post(route('account.prayer.pray', $private))->assertNotFound();

        // Staff can take something off the wall.
        $this->actingAs(User::factory()->create(['role' => 'secretary']))->post(route('admin.prayer-requests.off-wall', $shared));
        $this->actingAs($friend->user)->get(route('account.prayer.wall'))->assertDontSee('mother');

        // Old requests leave the wall; only the member can mark or remove their own; visitors can't see the wall.
        $old = $this->ask('church');
        $old->forceFill(['created_at' => now()->subDays(PrayerRequest::WALL_DAYS + 1)])->save();
        $this->assertFalse(PrayerRequest::wall()->whereKey($old->id)->exists());
        $this->actingAs($friend->user)->post(route('account.prayer.answered', $old))->assertNotFound();
        $this->delete(route('account.prayer.destroy', $old))->assertNotFound();
        $this->actingAs($this->ada->user)->delete(route('account.prayer.destroy', $old));
        $this->assertModelMissing($old);
        $pending = Member::factory()->create(['status' => Member::PENDING]);
        $this->actingAs($pending->user)->get(route('account.prayer.wall'))->assertNotFound();
    }
}
