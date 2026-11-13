<?php

namespace Tests\Feature;

use App\Mail\MediaRequestUpdate;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\MediaRequest;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Unit $media;

    private Member $hod;

    private Member $camera;

    private Unit $choir;

    private Member $choirHead;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->media = Unit::factory()->media()->create();
        $this->hod = Member::factory()->active()->create(['name' => 'Bro. Tunde', 'unit_id' => $this->media->id]);
        $this->media->update(['hod_member_id' => $this->hod->id]);
        $this->camera = Member::factory()->active()->create(['name' => 'Kunle Camera', 'unit_id' => $this->media->id]);
        $this->choir = Unit::factory()->create(['name' => 'Choir']);
        $this->choirHead = Member::factory()->active()->create(['name' => 'Sis. Ada', 'unit_id' => $this->choir->id]);
        $this->choir->update(['hod_member_id' => $this->choirHead->id]);
    }

    private function ask(array $overrides = []): MediaRequest
    {
        $this->actingAs($this->choirHead->user)->post(route('media-requests.store'), [
            'unit_id' => $this->choir->id, 'type' => 'flyer', 'title' => 'Christmas concert flyer',
            'details' => 'Saturday 20 Dec, 5pm, main auditorium.', 'needed_by' => today()->addDays(10)->toDateString(),
            'files' => [UploadedFile::fake()->image('logo.png', 400, 400)],
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return MediaRequest::latest('id')->firstOrFail();
    }

    public function test_unit_heads_and_staff_can_ask_but_ordinary_members_cannot(): void
    {
        $ordinary = Member::factory()->active()->create(['unit_id' => $this->choir->id]);
        $this->actingAs($ordinary->user)->get(route('media-requests.index'))->assertForbidden();
        $this->actingAs($this->choirHead->user)->get(route('media-requests.index'))->assertOk()->assertSee('Ask the media team.');
        $this->actingAs($this->choirHead->user)->get(route('account'))->assertSee('Ask the media team');

        // A unit head can only ask for their own unit.
        $other = Unit::factory()->create();
        $this->actingAs($this->choirHead->user)->post(route('media-requests.store'), [
            'unit_id' => $other->id, 'type' => 'flyer', 'title' => 'X', 'details' => 'Y', 'needed_by' => today()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('unit_id');

        // Staff can ask for the church office.
        $staff = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($staff)->post(route('media-requests.store'), [
            'type' => 'graphic', 'title' => 'Thanksgiving Sunday', 'details' => 'Square post', 'needed_by' => today()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('Church office', MediaRequest::firstWhere('title', 'Thanksgiving Sunday')->fromLabel());
    }

    public function test_a_request_goes_from_asked_to_delivered_to_done(): void
    {
        $request = $this->ask();
        $this->assertSame(['new', 'Choir'], [$request->status, $request->fromLabel()]);
        $this->assertMatchesRegularExpression('/^MR-[A-Z0-9]{6}$/', $request->reference);
        $this->assertSame('reference', $request->files()->sole()->purpose);
        Mail::assertSent(MediaRequestUpdate::class, fn ($m) => $m->hasTo($this->hod->user->email) && $m->forTeam);

        // The team sees it; a team member takes it.
        $this->actingAs($this->camera->user)->get(route('unit.requests.index', $this->media))->assertOk()->assertSee('Christmas concert flyer');
        $this->actingAs($this->camera->user)->post(route('unit.requests.assign', [$this->media, $request]))->assertSessionHasNoErrors();
        $this->assertSame(['in_progress', $this->camera->id], [$request->fresh()->status, $request->fresh()->assigned_member_id]);
        Mail::assertSent(MediaRequestUpdate::class, fn ($m) => $m->hasTo($this->choirHead->user->email) && str_contains($m->body, 'Kunle'));

        // Messages: an internal note isn't shown or sent to whoever asked.
        $this->post(route('unit.requests.comment', [$this->media, $request]), ['body' => 'Using the gold template', 'internal' => '1']);
        $this->post(route('unit.requests.comment', [$this->media, $request]), ['body' => 'Do you want the choir photo on it?']);
        $this->actingAs($this->choirHead->user)->get(route('media-requests.show', $request))
            ->assertSee('Do you want the choir photo on it?')->assertDontSee('Using the gold template');

        // Delivered with a file and a link.
        $this->actingAs($this->camera->user)->post(route('unit.requests.deliver', [$this->media, $request]), [
            'files' => [UploadedFile::fake()->image('flyer.jpg', 1080, 1350)],
            'links' => "https://drive.google.com/file/abc\nnot-a-link",
        ])->assertSessionHasErrors('links.1');
        $this->post(route('unit.requests.deliver', [$this->media, $request]), [
            'files' => [UploadedFile::fake()->image('flyer.jpg', 1080, 1350)],
            'links' => 'https://drive.google.com/file/abc', 'note' => 'Here you go',
        ])->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertSame('delivered', $request->status);
        $this->assertSame(2, $request->files()->where('purpose', 'deliverable')->count());
        $flyer = $request->files()->where('purpose', 'deliverable')->where('kind', 'image')->sole();
        Storage::disk('local')->assertExists($flyer->path);
        $this->assertNotNull($flyer->thumb_path);

        // Whoever asked can download what was delivered and asks for a change, then marks it done.
        $this->actingAs($this->choirHead->user)->get(route('media-files.show', [$flyer, 'download']))->assertOk();
        $this->post(route('media-requests.respond', $request), ['answer' => 'changes'])->assertSessionHasErrors('changes');
        $this->post(route('media-requests.respond', $request), ['answer' => 'changes', 'changes' => 'Make the date bigger']);
        $this->assertSame('in_progress', $request->fresh()->status);
        Mail::assertSent(MediaRequestUpdate::class, fn ($m) => $m->hasTo($this->camera->user->email) && str_contains($m->body, 'Make the date bigger'));
        $this->actingAs($this->camera->user)->post(route('unit.requests.deliver', [$this->media, $request]), ['note' => 'Bigger date done']);
        $this->actingAs($this->choirHead->user)->post(route('media-requests.respond', $request), ['answer' => 'done']);
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_only_leaders_assign_and_decline_and_only_the_assignee_delivers(): void
    {
        $request = $this->ask();
        $other = Member::factory()->active()->create(['unit_id' => $this->media->id]);

        $this->actingAs($this->camera->user)->post(route('unit.requests.decline', [$this->media, $request]), ['reason' => 'No'])->assertForbidden();
        // A team member can't hand it to someone else; it becomes theirs instead.
        $this->post(route('unit.requests.assign', [$this->media, $request]), ['member_id' => $other->id]);
        $this->assertSame($this->camera->id, $request->fresh()->assigned_member_id);
        // Someone else can't take it or deliver it.
        $this->actingAs($other->user)->post(route('unit.requests.assign', [$this->media, $request]))->assertForbidden();
        $this->post(route('unit.requests.deliver', [$this->media, $request]), ['note' => 'x'])->assertForbidden();

        // The HOD reassigns, or declines with a reason the requester sees.
        $this->actingAs($this->hod->user)->post(route('unit.requests.assign', [$this->media, $request]), ['member_id' => $other->id]);
        $this->assertSame($other->id, $request->fresh()->assigned_member_id);
        $this->post(route('unit.requests.decline', [$this->media, $request]), ['reason' => 'Not enough notice for a video.']);
        $this->assertSame('declined', $request->fresh()->status);
        $this->actingAs($this->choirHead->user)->get(route('media-requests.show', $request))->assertSee('Not enough notice for a video.');
    }

    public function test_requests_and_their_files_are_private(): void
    {
        $request = $this->ask();
        $file = $request->files()->sole();
        $stranger = Member::factory()->active()->create();

        $this->actingAs($stranger->user)->get(route('media-requests.show', $request))->assertNotFound();
        $this->get(route('media-files.show', [$file, 'download']))->assertNotFound();
        $this->get(route('unit.requests.show', [$this->media, $request]))->assertForbidden();
        $this->actingAs($this->camera->user)->get(route('media-files.show', [$file, 'view']))->assertOk();
        // Whoever asked can cancel while it's open.
        $this->actingAs($this->choirHead->user)->post(route('media-requests.respond', $request), ['answer' => 'cancel']);
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_the_library_keeps_files_in_folders_with_tags_and_search(): void
    {
        $this->actingAs($this->camera->user);
        $this->post(route('unit.library.folders.store', $this->media), ['name' => 'Logos'])->assertForbidden();
        $this->actingAs($this->hod->user)->post(route('unit.library.folders.store', $this->media), ['name' => 'Logos']);
        $folder = MediaFolder::sole();

        $this->actingAs($this->camera->user)->post(route('unit.library.upload', $this->media), [
            'files' => [UploadedFile::fake()->image('church-logo.png', 800, 800), UploadedFile::fake()->create('brand.psd', 2048), UploadedFile::fake()->create('promo.mp4', 4096, 'video/mp4')],
            'media_folder_id' => $folder->id, 'tags' => 'Brand, logo, brand',
        ])->assertSessionHasNoErrors();
        $this->post(route('unit.library.upload', $this->media), ['files' => [UploadedFile::fake()->create('tool.exe', 10)]])->assertSessionHasErrors('files.0');
        $this->post(route('unit.library.link', $this->media), ['url' => 'https://drive.google.com/x', 'title' => 'Easter raw footage']);

        $this->assertSame(['image', 'design', 'video'], MediaFile::where('media_folder_id', $folder->id)->orderBy('id')->pluck('kind')->all());
        $logo = MediaFile::firstWhere('title', 'church-logo');
        $this->assertSame('brand, logo', $logo->tags);
        $this->assertNotNull($logo->thumb_path);

        $this->get(route('unit.library.index', [$this->media, 'folder' => $folder->id]))->assertOk()->assertSee('church-logo')->assertDontSee('Easter raw footage');
        $this->get(route('unit.library.index', [$this->media, 'q' => 'easter']))->assertSee('Easter raw footage')->assertDontSee('church-logo');
        $this->get(route('unit.library.index', [$this->media, 'kind' => 'design']))->assertSee('brand')->assertDontSee('church-logo');

        // A design file is only ever downloaded, never shown in the browser.
        $psd = MediaFile::firstWhere('kind', 'design');
        $this->get(route('media-files.show', [$psd, 'view']))->assertDownload('brand.psd');
        $this->get(route('media-files.show', [$logo, 'view']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Library files are the team's: whoever asked for a request can't open them, nor can anyone else.
        $this->actingAs($this->choirHead->user)->get(route('media-files.show', [$logo, 'download']))->assertNotFound();
        $this->get(route('unit.library.index', $this->media))->assertForbidden();

        // Only whoever added it or a leader deletes it; removing a folder keeps its files.
        $other = Member::factory()->active()->create(['unit_id' => $this->media->id]);
        $this->actingAs($other->user)->delete(route('unit.library.destroy', [$this->media, $logo]))->assertForbidden();
        $this->actingAs($this->camera->user)->delete(route('unit.library.destroy', [$this->media, $logo]));
        $this->assertModelMissing($logo);
        Storage::disk('local')->assertMissing($logo->path);
        $this->actingAs($this->hod->user)->delete(route('unit.library.folders.destroy', [$this->media, $folder]));
        $this->assertSame(2, MediaFile::whereNull('media_folder_id')->where('kind', '!=', 'link')->count());
    }
}
