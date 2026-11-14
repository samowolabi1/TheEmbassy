<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tokens\Ledger;
use Database\Seeders\MemberDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MemberDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_the_church_once_and_members_can_sign_in(): void
    {
        $this->seed(MemberDemoSeeder::class);
        $this->seed(MemberDemoSeeder::class); // a second run adds nothing

        $this->assertSame(36, Member::count());
        $this->assertSame([30, 4, 2], [Member::where('status', 'active')->count(), Member::where('status', 'pending')->count(), Member::where('status', 'inactive')->count()]);
        $this->assertSame(Unit::count(), Unit::whereNotNull('hod_member_id')->count());
        $this->assertSame(30, Member::whereNotNull('last_attended_on')->count());
        $this->assertGreaterThan(0, FollowUp::open()->where('reason', 'absent')->count());
        $this->assertTrue(app(Ledger::class)->audit()['ok']);

        $tolu = User::firstWhere('email', 'member1@demo.test');
        $this->assertTrue($tolu->member->hasBirthdayToday());
        $this->assertGreaterThan(0, $tolu->tokenBalance());
        $this->post(route('login.store'), ['email' => 'member1@demo.test', 'password' => MemberDemoSeeder::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($tolu);
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(MemberDemoSeeder::class)->run();
    }
}
