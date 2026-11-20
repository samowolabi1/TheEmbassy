<?php

namespace Tests\Feature;

use App\Models\CommunityRoom;
use App\Models\Member;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityWorkGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_business_and_career_groups_exist_with_their_helpers(): void
    {
        $this->assertSame(
            ['General', 'Prayer & praise', 'Word & devotion', 'Campus life', 'Career', 'Job vacancies', 'Business', 'Banter & fun'],
            CommunityRoom::whereNull('unit_id')->orderBy('position')->pluck('name')->all(),
        );
        $this->assertFalse(CommunityRoom::where('slug', 'career-and-hustle')->exists());

        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->get(route('community.index'))->assertOk()->assertSee('Job vacancies')->assertSee('Business')->assertSee('Career');
        $this->get(route('community.room', 'job-vacancies'))->assertOk()->assertSee('Post a job')->assertSee('How to apply')->assertSee('data-template', false);
        $this->get(route('community.room', 'career'))->assertOk()->assertSee('Share an opportunity');
        $this->get(route('community.room', 'general'))->assertOk()->assertDontSee('data-template', false)->assertDontSee('data-template-fill', false);

        // A seller's business helper starts with their shop's link.
        $shop = Shop::factory()->approved()->create(['user_id' => $member->user_id]);
        $this->actingAs($member->user->fresh())->get(route('community.room', 'business'))->assertOk()->assertSee('Share my business')->assertSee(route('market.shop', $shop));

        // A post written by the helper goes up like any message, with its link clickable.
        $this->postJson(route('community.messages.store', 'job-vacancies'), ['body' => "📢 JOB VACANCY: Sales rep\n📍 Ikeja\n✉️ How to apply: https://example.com/jobs"])->assertCreated();
        $this->get(route('community.room', 'job-vacancies'))->assertSee('JOB VACANCY: Sales rep')->assertSee('<a href="https://example.com/jobs"', false);
    }
}
