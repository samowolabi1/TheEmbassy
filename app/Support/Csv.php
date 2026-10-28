<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads that are safe to open in a spreadsheet.
 */
class Csv
{
    /**
     * Spreadsheet apps run cells starting with these as formulas; prefix them so they stay text.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Stream a CSV. `$rows` receives a callback to write each row with.
     *
     * @param  list<string>  $header
     * @param  Closure(Closure(array<int, mixed>): void): void  $rows
     */
    public static function download(string $filename, array $header, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            $rows(fn (array $row) => fputcsv($out, array_map([self::class, 'cell'], $row)));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
