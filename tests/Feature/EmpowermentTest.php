<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\EmpowermentApplication;
use App\Models\EmpowermentProgramme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class EmpowermentTest extends TestCase
{
    use RefreshDatabase;

    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->office = User::factory()->create(['role' => 'secretary']);
    }

    /**
     * An open programme with two choices (one with 1 place) and one required question.
     */
    private function programme(array $overrides = []): EmpowermentProgramme
    {
        $p = EmpowermentProgramme::create(['slug' => 'youth-2027', 'title' => 'Youth Empowerment 2027', 'is_published' => true, 'max_choices' => 2,
            'min_age' => 16, 'max_age' => 35, 'opens_on' => today()->subDay(), 'closes_on' => today()->addWeek(), 'thank_you' => 'We call shortlisted people in January.', ...$overrides]);
        $p->tracks()->create(['name' => 'Catering', 'slots' => 1, 'position' => 0]);
        $p->tracks()->create(['name' => 'Fashion design', 'position' => 1]);
        $p->questions()->create(['label' => 'Why do you want to join?', 'type' => 'textarea', 'is_required' => true, 'position' => 0]);
        $p->questions()->create(['label' => 'Which days suit you?', 'type' => 'checkboxes', 'options' => ['Saturday', 'Weekday evenings'], 'position' => 1]);

        return $p;
    }

    private function application(EmpowermentProgramme $p, array $overrides = []): array
    {
        [$why, $days] = $p->questions()->get()->all();
        [$catering, $fashion] = $p->tracks()->get()->all();

        return ['name' => 'Chioma Okeke', 'phone' => '0803 123 4567', 'email' => 'chioma@example.com', 'gender' => 'female', 'date_of_birth' => today()->subYears(22)->toDateString(),
            'location' => 'Yaba', 'choices' => [$catering->id, $fashion->id], 'answers' => [$why->key() => 'To start a cake business.', $days->key() => ['Saturday']], 'agree' => '1', ...$overrides];
    }

    public function test_the_office_sets_up_a_programme_with_a_banner_choices_and_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'finance']))->get(route('admin.empowerment.index'))->assertForbidden();

        $this->actingAs($this->office)->get(route('admin.empowerment.index'))->assertOk()->assertSee('New programme');
        $this->post(route('admin.empowerment.store'), ['title' => 'Youth Empowerment 2027'])->assertRedirect();
        $p = EmpowermentProgramme::sole();
        $this->assertSame([false, 4], [$p->is_published, $p->questions()->count()]);
        $this->get(route('empowerment.show', $p))->assertOk()->assertSee('Preview'); // staff can preview a draft

        // Can't publish without choices.
        $settings = ['title' => 'Youth Empowerment 2027', 'max_choices' => 2, 'min_age' => 16, 'max_age' => 35, 'is_published' => '1'];
        $this->post(route('admin.empowerment.update', $p), $settings)->assertSessionHas('status', fn ($s) => str_contains($s, 'not published yet'));
        $this->assertFalse($p->fresh()->is_published);

        $this->put(route('admin.empowerment.tracks', $p), ['new' => ['name' => 'Catering and baking', 'slots' => 20, 'description' => 'Cakes and small chops']])->assertSessionHas('status', 'Choices saved.');
        $this->put(route('admin.empowerment.tracks', $p), ['new' => ['name' => 'Phone repair']]);
        $this->assertSame(['Catering and baking', 'Phone repair'], $p->tracks()->pluck('name')->all());

        // The form builder: a dropdown needs two options.
        $this->put(route('admin.empowerment.questions', $p), ['new' => ['label' => 'Your church?', 'type' => 'select', 'options' => 'Only one']])->assertSessionHasErrors('questions', null, 'questions');
        $this->put(route('admin.empowerment.questions', $p), ['new' => ['label' => 'Your church?', 'type' => 'select', 'options' => "RCCG\nOther", 'is_required' => '1']])->assertSessionHas('status', 'Form saved.');
        $first = $p->questions()->first();
        $this->put(route('admin.empowerment.questions', $p), ['questions' => [$first->id => ['label' => $first->label, 'type' => $first->type, 'remove' => '1']]]);
        $this->assertSame(4, $p->questions()->count());
        $this->assertSame(['RCCG', 'Other'], $p->questions()->where('label', 'Your church?')->value('options'));

        // A banner, then a new one; the old file goes.
        $this->post(route('admin.empowerment.update', $p), [...$settings, 'banner' => UploadedFile::fake()->image('a.jpg', 1600, 600)])->assertSessionHasNoErrors();
        $old = $p->fresh()->banner_path;
        Storage::disk('public')->assertExists($old);
        $this->post(route('admin.empowerment.update', $p), [...$settings, 'banner' => UploadedFile::fake()->image('b.jpg', 1600, 600)])->assertSessionHas('status', fn ($s) => str_contains($s, 'The page is live'));
        Storage::disk('public')->assertMissing($old);
        $p->refresh();
        $this->assertTrue($p->is_published);

        auth()->logout();
        $this->get(route('empowerment.index'))->assertOk()->assertSee($p->bannerUrl())->assertSee('Catering and baking')->assertSee('20 places left')->assertSee('Your church?');
        $this->post(route('admin.empowerment.update', $p), $settings)->assertRedirect(); // guests are sent to log in
    }

    public function test_young_people_apply_and_get_a_reference(): void
    {
        $p = $this->programme();
        $this->get(route('empowerment.index'))->assertOk()->assertSee('Applications open')->assertSee('For ages 16 to 35')->assertSee('First choice')->assertSee('Which days suit you?');

        // Checks: a choice, the required answer, distinct choices, the age range.
        $this->post(route('empowerment.apply', $p), $this->application($p, ['choices' => ['', '']]))->assertSessionHasErrors('choices');
        $this->post(route('empowerment.apply', $p), $this->application($p, ['answers' => []]))->assertSessionHasErrors('answers.q'.$p->questions()->first()->id);
        $catering = $p->tracks()->first();
        $this->post(route('empowerment.apply', $p), $this->application($p, ['choices' => [$catering->id, $catering->id]]))->assertSessionHasErrors('choices.0');
        $this->post(route('empowerment.apply', $p), $this->application($p, ['date_of_birth' => today()->subYears(40)->toDateString()]))
            ->assertSessionHasErrors(['date_of_birth' => 'For ages 16 to 35. You can still ask the church office about other ways to get involved.']);

        $this->post(route('empowerment.apply', $p), $this->application($p))->assertRedirect(route('empowerment.show', $p).'#apply');
        $a = EmpowermentApplication::sole();
        $this->assertSame(['new', ['Catering', 'Fashion design']], [$a->status, $a->choices->pluck('name')->all()]);
        $this->assertSame(['Saturday'], $a->answers['q'.$p->questions()->get()[1]->id]);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('chioma@example.com') && str_contains($m->body, $a->reference) && str_contains($m->body, 'Catering and Fashion design'));
        $this->followingRedirects()->get(route('empowerment.show', $p))->assertOk();

        // The same phone can't apply twice.
        $this->post(route('empowerment.apply', $p), $this->application($p, ['phone' => '+234 803 123 4567', 'name' => 'Someone else']))->assertSessionHasErrors('phone');
        // The honeypot quietly drops bots.
        $this->post(route('empowerment.apply', $p), $this->application($p, ['phone' => '0809', 'website' => 'spam']));
        $this->assertSame(1, EmpowermentApplication::count());
    }

    public function test_closed_and_unpublished_programmes(): void
    {
        $p = $this->programme(['opens_on' => today()->addWeek(), 'closes_on' => today()->addWeeks(3)]);
        $this->get(route('empowerment.show', $p))->assertOk()->assertSee('Applications open on')->assertDontSee('Send my application');
        $this->post(route('empowerment.apply', $p), $this->application($p))->assertStatus(422);

        $p->update(['is_published' => false]);
        $this->get(route('empowerment.show', $p))->assertNotFound();
        $this->get(route('empowerment.index'))->assertOk()->assertSee('There\'s no empowerment programme open right now', false);
    }

    public function test_the_office_reviews_accepts_and_lets_people_know(): void
    {
        $p = $this->programme();
        $this->post(route('empowerment.apply', $p), $this->application($p));
        $this->post(route('empowerment.apply', $p), $this->application($p, ['name' => 'Tunde Bello', 'phone' => '0805 999 0000', 'email' => 'tunde@example.com', 'gender' => 'male', 'choices' => [$p->tracks()->get()[1]->id]]));
        [$chioma, $tunde] = EmpowermentApplication::orderBy('id')->get()->all();
        [$catering, $fashion] = $p->tracks()->get()->all();

        $this->actingAs($this->office)->get(route('admin.empowerment.show', $p))->assertOk()->assertSee('Chioma Okeke')->assertSee('Tunde Bello')->assertSee('1. Catering');
        $this->get(route('admin.empowerment.show', [$p, 'track' => $catering->id]))->assertSee('Chioma Okeke')->assertDontSee('Tunde Bello');
        $this->get(route('admin.empowerment.application', [$p, $chioma]))->assertOk()->assertSee('To start a cake business.')->assertSee('Saturday');

        $this->post(route('admin.empowerment.decide', [$p, $chioma]), ['status' => 'accepted', 'admin_note' => 'Strong interview', 'notify' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'They\'ve been told'));
        $chioma->refresh();
        $this->assertSame(['accepted', $catering->id, $this->office->id], [$chioma->status, $chioma->empowerment_track_id, $chioma->decided_by]);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('chioma@example.com') && str_contains($m->body, 'accepted into Youth Empowerment 2027 for Catering'));

        // Catering had one place: now it's full.
        auth()->logout();
        $this->get(route('empowerment.show', $p))->assertSee('Full: you can still join the waiting list')->assertSee('Catering (full: waiting list)');
        // The applicant's own page, from the signed link in their email.
        $this->get(URL::signedRoute('empowerment.status', $chioma))->assertOk()->assertSee('Accepted')->assertSee($chioma->reference);
        $this->get(route('empowerment.status', $chioma))->assertForbidden();

        // Several at once.
        $this->actingAs($this->office)->post(route('admin.empowerment.bulk', $p), ['ids' => [$tunde->id], 'status' => 'waitlisted', 'notify' => '1'])->assertSessionHas('status', '1 application now: Waiting list.');
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('tunde@example.com') && str_contains($m->body, 'waiting list'));

        // Excel: every answer in its own column.
        $response = $this->get(route('admin.empowerment.export', $p))->assertOk();
        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('To start a cake business.', $sheet);
        $this->assertStringContainsString('Why do you want to join?', $sheet);
        $this->assertStringContainsString('Second choice', $sheet);
        $zip->close();

        // And in Reports.
        $this->actingAs(User::factory()->create(['role' => 'super']))->get(route('admin.reports.download', ['report' => 'empowerment', 'from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        // A programme with applications can't be deleted.
        $this->actingAs($this->office)->delete(route('admin.empowerment.destroy', $p))->assertStatus(422);
    }
}
