<?php

namespace App\Booking;

class ImportCsvParser
{
    /**
     * Parse an uploaded CSV into an array of associative rows, or return an error string.
     * When $requireEvent is true (the cross-event "global import"), an `Event` column is
     * also mandatory alongside Guest Name, identifying which event each row belongs to.
     *
     * @return array|string
     */
    public function parse($file, bool $requireEvent = false)
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return 'Could not read the uploaded file.';
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return 'The file is empty.';
        }

        // Excel/Nextcloud Forms exports commonly start with a UTF-8 BOM, which would
        // otherwise glue onto the first header name and fail the header check.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        // Only Guest Name is mandatory - Comment/Block/Row/Seat/Number of Seats can all be
        // omitted entirely (e.g. a form export that never collected exact seat picks).
        if (! in_array('guest name', $header, true)) {
            fclose($handle);

            return 'CSV header must contain a "Guest Name" column.';
        }

        if ($requireEvent && ! in_array('event', $header, true)) {
            fclose($handle);

            return 'CSV header must contain an "Event" column.';
        }

        $rows = [];
        $rowNumber = 1; // header is row 1

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (! array_filter($data, fn ($v) => trim((string) $v) !== '')) {
                continue; // skip fully blank lines
            }

            if (count($rows) >= 2000) {
                fclose($handle);

                return 'Import file has too many rows (max 2000).';
            }

            $assoc = ['_row' => $rowNumber, 'comment' => '', 'block' => '', 'row' => '', 'seat' => '', 'event' => ''];
            foreach ($header as $i => $key) {
                $assoc[$key] = trim((string) ($data[$i] ?? ''));
            }

            // Normalize an optional submission-timestamp column (used by ImportProposalBuilder
            // for oldest-first priority). Missing/unparseable values yield null.
            $tsRaw = $assoc['timestamp']
                ?? $assoc['submitted at']
                ?? $assoc['submission time']
                ?? $assoc['date']
                ?? $assoc['created at']
                ?? '';
            $assoc['_timestamp'] = $this->normalizeTimestamp((string) $tsRaw);

            $rows[] = $assoc;
        }

        fclose($handle);

        if (empty($rows)) {
            return 'The file has no data rows.';
        }

        return $rows;
    }

    /**
     * Parse a submission-timestamp string (accepts most common date formats via strtotime,
     * which covers ISO 8601, `Y-m-d H:i:s`, RFC 2822, etc.) into a normalized `Y-m-d H:i:s`
     * string. Returns null for empty or unparseable input - those rows land at the bottom
     * of the oldest-first priority order, stably ordered by CSV row index.
     *
     * Note: slash-separated dates like `02/03/2024` are parsed by strtotime() as US
     * `m/d/Y` (Feb 3), not `d/m/Y` (2 March), so avoid that ambiguous format in exports.
     */
    private function normalizeTimestamp(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);

        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
