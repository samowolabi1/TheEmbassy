<?php

namespace Tests\Feature;

use App\Models\FirstTimer;
use App\Models\Fund;
use App\Models\Gift;
use App\Models\Income;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Download a report and return each sheet's XML, checking every part is well-formed XML.
     *
     * @return array<string, string> sheet name => sheet xml
     */
    private function workbook(string $report, array $query = []): array
    {
        $response = $this->get(route('admin.reports.download', ['report' => $report, ...$query]));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertMatchesRegularExpression('/\.xlsx"?$/', $response->headers->get('content-disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)), 'Bad XML in '.$zip->getNameIndex($i));
        }
        $book = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $sheets = [];
        foreach ($book->sheets->sheet as $i => $sheet) {
            $sheets[(string) $sheet['name']] = $zip->getFromName('xl/worksheets/sheet'.(count($sheets) + 1).'.xml');
        }
        $zip->close();

        return $sheets;
    }

    public function test_only_administrators_see_reports_and_only_the_pastor_in_charge_sees_giving(): void
    {
        foreach (['pastor', 'secretary', 'finance', 'usher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.reports.index'))->assertForbidden();
            $this->get(route('admin.reports.download', 'members'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->get(route('admin.reports.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.reports.index'))->assertOk()
            ->assertSee('Download Excel')->assertSee('Income &amp; expenses', false)->assertDontSee('Every gift in the dates chosen');
        $this->get(route('admin.reports.download', 'giving'))->assertForbidden();
        $this->get(route('admin.reports.download', 'nothing'))->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'super']))->get(route('admin.reports.index'))->assertOk()->assertSee('Every gift in the dates chosen');
        $this->get(route('admin.dashboard'))->assertSee(route('admin.reports.index'));
    }

    public function test_every_report_downloads_as_a_workbook(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super']));
        Member::factory()->active()->create(['unit_id' => Unit::factory()->create()->id]);
        foreach (array_keys(Reports::all()) as $report) {
            $sheets = $this->workbook($report, ['from' => today()->subYear()->toDateString(), 'to' => today()->toDateString()]);
            $this->assertSame('Summary', array_key_first($sheets), $report);
            $this->assertGreaterThan(1, count($sheets), $report);
        }
    }

    public function test_reports_hold_the_figures_for_the_dates_chosen(): void
    {
        $this->actingAs($super = User::factory()->create(['role' => 'super']));
        $choir = Unit::factory()->create(['name' => 'Choir']);
        Member::factory()->active()->create(['name' => 'Ada Obi', 'unit_id' => $choir->id, 'joined_on' => today()->subDays(3), 'phone' => '=HYPERLINK("x")']);
        Member::factory()->create(['name' => 'Waiting Person']);
        $service = Service::create(['held_on' => today()->subDays(2), 'kind' => 'sunday', 'in_person' => 120, 'online' => 30]);
        FirstTimer::create(['name' => 'Tolu Visitor', 'phone' => '0803', 'visited_on' => today()->subDays(2)]);
        FirstTimer::create(['name' => 'Old Visitor', 'phone' => '0804', 'visited_on' => today()->subMonths(3)]);
        Gift::forceCreate(['fund_id' => Fund::create(['name' => 'Tithe'])->id, 'code' => 'G1', 'name' => 'Ada Obi', 'email' => 'a@example.com', 'amount' => 2500000, 'method' => 'transfer', 'status' => Gift::PAID, 'given_on' => today()->subDay()]);
        Income::forceCreate(['title' => 'Harvest sales', 'category' => 'Harvest', 'amount' => 1000000, 'received_on' => today(), 'method' => 'cash', 'status' => Income::CONFIRMED, 'recorded_by' => $super->id]);
        $range = ['from' => today()->subWeek()->toDateString(), 'to' => today()->toDateString()];

        $members = $this->workbook('members', $range);
        $this->assertStringContainsString('Ada Obi', $members['Members']);
        $this->assertStringContainsString('Choir', $members['Members']);
        // Text stays text: nothing runs as a formula.
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;x&quot;)', $members['Members']);
        $this->assertStringNotContainsString('<f>', $members['Members']);
        $this->assertStringContainsString('<autoFilter', $members['Members']);
        $this->assertStringContainsString('Waiting for approval', $members['Summary']);

        $attendance = $this->workbook('attendance', $range);
        $this->assertStringContainsString('<v>120</v>', $attendance['Services']);

        $care = $this->workbook('care', $range);
        $this->assertStringContainsString('Tolu Visitor', $care['First-timers']);
        $this->assertStringNotContainsString('Old Visitor', $care['First-timers']);

        $giving = $this->workbook('giving', $range);
        $this->assertStringContainsString('<v>25000</v>', $giving['Gifts']); // naira, as a number
        $this->assertStringContainsString('<v>25000</v>', $giving['Summary']);

        $finance = $this->workbook('finance', $range);
        $this->assertStringContainsString('Harvest sales', $finance['Income']);
        $this->assertStringContainsString('<v>10000</v>', $finance['Summary']);
    }
}
