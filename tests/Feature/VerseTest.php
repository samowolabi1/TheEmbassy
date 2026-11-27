<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\Verse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_kjv_verse_bank_is_loaded_and_complete(): void
    {
        $count = Verse::active()->count();
        $this->assertGreaterThanOrEqual(300, $count);
        $this->assertSame($count, Verse::distinct()->count('reference'));
        $this->assertSame(0, Verse::where('text', '')->orWhereNull('theme')->count());
        $this->assertSame('The LORD is my shepherd; I shall not want.', Verse::firstWhere('reference', 'Psalm 23:1')->text);
        // No verse stops mid-sentence on a comma.
        $this->assertSame(0, Verse::where('text', 'like', '%,')->count());
    }

    public function test_members_scroll_verses_and_get_more_without_repeats(): void
    {
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->get(route('account'))->assertSee(route('account.verses'), false);

        $page = $this->get(route('account.verses'))->assertOk()->assertSee('Swipe up or tap for another verse')->assertSee('KJV');
        $first = Verse::all()->filter(fn ($v) => str_contains($page->getContent(), 'data-verse="'.$v->id.'"'))->pluck('id');
        $this->assertCount(25, $first);

        $more = collect($this->getJson(route('account.verses.more', ['seen' => $first->join(',')]))->assertOk()->json('verses'));
        $this->assertCount(25, $more);
        $this->assertEmpty($more->pluck('id')->intersect($first));
        $this->assertSame(['id', 'reference', 'text', 'theme'], array_keys($more->first()));

        // Hidden verses never appear.
        Verse::whereKeyNot($first->first())->update(['is_active' => false]);
        $this->assertSame([$first->first()], collect($this->getJson(route('account.verses.more'))->json('verses'))->pluck('id')->all());
    }

    public function test_members_save_verses_and_find_them_again(): void
    {
        $member = Member::factory()->active()->create();
        $verse = Verse::firstWhere('reference', 'John 3:16');

        $this->actingAs($member->user)->postJson(route('account.verses.save', $verse))->assertOk()->assertJson(['saved' => true]);
        $this->get(route('account.verses.saved'))->assertOk()->assertSee('For God so loved the world');
        $this->postJson(route('account.verses.save', $verse))->assertJson(['saved' => false]);
        $this->get(route('account.verses.saved'))->assertSee('No saved verses yet');
    }

    public function test_visitors_and_non_members_cannot_use_the_scroll(): void
    {
        $this->get(route('account.verses'))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('account.verses'))->assertForbidden();
    }

    public function test_a_verse_greets_members_once_a_day_unless_they_turn_it_off(): void
    {
        $member = Member::factory()->active()->create(['daily_verse' => true]);

        $this->actingAs($member->user)->get(route('account'))->assertSee('VERSE FOR TODAY')->assertSee('Amen');
        $this->get(route('account'))->assertDontSee('VERSE FOR TODAY');
        $this->assertTrue($member->fresh()->daily_verse_on->isToday());

        $this->travel(1)->day();
        $this->get('/')->assertSee('VERSE FOR TODAY');

        // Turned off in My details: no more verses.
        $this->put(route('account.update'), ['phone' => '0803'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->daily_verse);
        $this->travel(1)->day();
        $this->get(route('account'))->assertDontSee('VERSE FOR TODAY')->assertSee('Show me a Bible verse each day');

        $this->put(route('account.update'), ['phone' => '0803', 'daily_verse' => '1']);
        $this->get(route('account'))->assertSee('VERSE FOR TODAY');
    }

    public function test_staff_add_edit_and_hide_verses(): void
    {
        $office = User::factory()->staff('secretary')->create();
        $this->actingAs($office)->get(route('admin.verses.index'))->assertOk()->assertSee('Psalm 23:1');
        $this->get(route('admin.verses.index', ['q' => 'shepherd']))->assertSee('Psalm 23:1')->assertDontSee('John 3:16');

        $this->post(route('admin.verses.store'), ['reference' => 'Psalm 23:6', 'text' => 'Surely goodness and mercy shall follow me all the days of my life: and I will dwell in the house of the LORD for ever.', 'theme' => 'Faithfulness'])
            ->assertSessionHasNoErrors();
        $verse = Verse::firstWhere('reference', 'Psalm 23:6');
        $this->put(route('admin.verses.update', $verse), ['reference' => 'Psalm 23:6', 'text' => $verse->text, 'theme' => 'Hope'])->assertSessionHasNoErrors();
        $this->assertSame('Hope', $verse->fresh()->theme);

        $this->post(route('admin.verses.toggle', $verse));
        $this->assertFalse($verse->fresh()->is_active);
        $this->get(route('admin.verses.index', ['show' => 'hidden']))->assertSee('Psalm 23:6');

        $this->actingAs(User::factory()->staff('usher')->create())->get(route('admin.verses.index'))->assertForbidden();
    }
}
