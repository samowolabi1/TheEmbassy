<?php

namespace Tests\Feature;

use App\Mail\TestimonyUpdate;
use App\Models\Member;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TestimoniesTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fundTreasury(100_000);
        $this->member = Member::factory()->active()->create(['name' => 'Sis. Adaeze Obi']);
    }

    private function share(array $overrides = []): Testimony
    {
        $this->actingAs($this->member->user)->post(route('account.testimonies.store'), [
            'title' => 'God healed my mother', 'category' => 'healing', 'name_display' => 'first', 'share_publicly' => '1', 'share_in_church' => '1',
            'body' => 'The doctors said there was nothing more to do, but we prayed as a family and the church prayed with us. Today she is home and well.',
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return Testimony::latest('id')->firstOrFail();
    }

    public function test_a_member_shares_and_a_pastor_publishes_it(): void
    {
        $t = $this->share();
        $this->assertSame(['pending', true, 'Adaeze'], [$t->status, $t->share_publicly, $t->byline()]);
        $this->get(route('testimonies.show', $t))->assertNotFound();
        $this->get(route('testimonies.index'))->assertDontSee('God healed my mother');

        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->get(route('admin.testimonies.index'))->assertOk()->assertSee('God healed my mother');
        $this->put(route('admin.testimonies.update', $t), ['title' => 'God healed my mum', 'category' => 'healing', 'body' => $t->body, 'action' => 'publish'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(['published', 'God healed my mum'], [$t->status, $t->title]);
        Mail::assertSent(TestimonyUpdate::class, fn ($m) => $m->hasTo($this->member->user->email) && str_contains($m->body, 'tokens'));
        $this->assertSame(5, $this->member->user->tokenBalance());

        // On the website, under the first name only; people praise God for it once each.
        $this->get(route('testimonies.index'))->assertSee('God healed my mum')->assertSee('Adaeze')->assertDontSee('Obi');
        $this->get(route('testimonies.index', ['category' => 'provision']))->assertDontSee('God healed my mum');
        $friend = Member::factory()->active()->create();
        $this->actingAs($friend->user)->post(route('testimonies.praise', $t));
        $this->post(route('testimonies.praise', $t));
        $this->assertSame(1, $t->fresh()->praises);
        $this->get(route('testimonies.show', $t))->assertOk()->assertSee('You praised God for this');

        // A second testimony the same month earns nothing more.
        $second = $this->share(['title' => 'A new job']);
        $this->actingAs($pastor)->put(route('admin.testimonies.update', $second), ['title' => 'A new job', 'category' => 'career', 'body' => $second->body, 'action' => 'publish']);
        $this->assertSame(5, $this->member->user->tokenBalance());
    }

    public function test_privacy_choices_declining_and_withdrawing(): void
    {
        $private = $this->share(['title' => 'Kept for the church', 'share_publicly' => null, 'name_display' => 'anonymous']);
        $this->assertSame('A member of '.config('church.short_name'), $private->byline());
        $media = User::factory()->create(['role' => 'media']);
        $this->actingAs($media)->put(route('admin.testimonies.update', $private), ['title' => $private->title, 'category' => 'other', 'body' => $private->body, 'action' => 'publish']);
        $this->assertSame('published', $private->fresh()->status);
        $this->get(route('testimonies.index'))->assertDontSee('Kept for the church');
        $this->get(route('testimonies.show', $private))->assertNotFound();

        $short = $this->share(['title' => 'Too short']);
        $this->actingAs($media)->post(route('admin.testimonies.decline', $short), ['review_note' => 'Please tell us a little more.']);
        $this->assertSame('declined', $short->fresh()->status);
        $this->actingAs($this->member->user)->get(route('account.testimonies'))->assertSee('Please tell us a little more.');

        $this->post(route('account.testimonies.withdraw', $private));
        $this->assertSame('withdrawn', $private->fresh()->status);

        // Too short a story, someone else's testimony, members not yet approved, staff without the area.
        $this->post(route('account.testimonies.store'), ['title' => 'Hi', 'category' => 'other', 'name_display' => 'full', 'body' => 'Short'])->assertSessionHasErrors('body');
        $stranger = Member::factory()->active()->create();
        $this->actingAs($stranger->user)->post(route('account.testimonies.withdraw', $short))->assertNotFound();
        $pending = Member::factory()->create(['status' => Member::PENDING]);
        $this->actingAs($pending->user)->get(route('account.testimonies'))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'finance']))->get(route('admin.testimonies.index'))->assertForbidden();
    }
}
