<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\Reports;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports: pick a module and the dates, and download an Excel workbook. Administrators and the
 * pastor in charge only; the giving report is for the pastor in charge alone.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('admin.reports', [
            'reports' => collect(Reports::all())->reject(fn ($r) => ($r['giving'] ?? false) && ! $request->user()->can('see-giving')),
            'from' => $from,
            'to' => $to,
            'presets' => $this->presets(),
            'excel' => Xlsx::available(),
        ]);
    }

    public function download(Request $request, string $report, Reports $reports): BinaryFileResponse
    {
        $meta = Reports::all()[$report] ?? abort(404);
        abort_if(($meta['giving'] ?? false) && ! $request->user()->can('see-giving'), 403, 'Giving records are for the pastor in charge only.');
        abort_unless(Xlsx::available(), 500, 'This server can\'t make Excel files: ask your host to turn on PHP\'s zip extension.');
        [$from, $to] = $this->range($request);

        $path = Xlsx::write($reports->build($report, $from, $to), config('church.short_name').' '.$meta['title']);
        $name = Str::slug(config('church.short_name').' '.$meta['title']).'-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.xlsx';

        return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * The dates chosen, or this month so far.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : today()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : today();
        // Keep a workbook to a sensible size.
        if ($from->diffInYears($to) > 5) {
            $from = $to->copy()->subYears(5);
        }

        return [$from, $to];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function presets(): array
    {
        $t = today();

        return [
            'This month' => [$t->copy()->startOfMonth()->toDateString(), $t->toDateString()],
            'Last month' => [$t->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $t->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'This quarter' => [$t->copy()->firstOfQuarter()->toDateString(), $t->toDateString()],
            'This year' => [$t->copy()->startOfYear()->toDateString(), $t->toDateString()],
            'Last year' => [$t->copy()->subYear()->startOfYear()->toDateString(), $t->copy()->subYear()->endOfYear()->toDateString()],
        ];
    }
}
