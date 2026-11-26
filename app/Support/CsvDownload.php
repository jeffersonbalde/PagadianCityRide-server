<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A list as a file that opens in Excel.
 */
class CsvDownload
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public static function response(string $fileName, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'w');

            // The mark that tells Excel the file is UTF-8, so names with ñ are read right.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings, escape: '');

            foreach ($rows as $row) {
                fputcsv($output, array_map(self::safeCell(...), $row), escape: '');
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A spreadsheet runs a cell that starts like a formula. People type these values, so such a cell is shown as text.
     */
    public static function safeCell(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
