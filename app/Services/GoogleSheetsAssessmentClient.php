<?php

namespace App\Services;

use App\Exceptions\GoogleSheetsException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JsonException;

class GoogleSheetsAssessmentClient
{
    public const CRITERIA = [
        'case_fit' => 'Kesesuaian solusi dengan studi kasus',
        'innovation' => 'Inovasi dan kreativitas ide',
        'feasibility' => 'Kelayakan implementasi',
        'solution_design' => 'Kualitas rancangan solusi',
        'prototype' => 'Kualitas prototype awal',
    ];

    public const SCORE_LABELS = [
        1 => 'Belum memadai',
        2 => 'Kurang',
        3 => 'Memenuhi',
        4 => 'Baik/di atas harapan',
        5 => 'Sangat kuat',
    ];

    private const HEADERS = [
        'Nama Juri',
        'Nama Tim',
        'Kesesuaian Solusi dengan Studi Kasus',
        'Inovasi dan Kreativitas Ide',
        'Kelayakan Implementasi',
        'Kualitas Rancangan Solusi',
        'Kualitas Prototype Awal',
        'Total Nilai',
        'Komentar',
        'ID Juri Internal',
        'ID Tim Internal',
    ];

    private const GOOGLE_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private ?string $accessToken = null;

    private bool $internalColumnsHidden = false;

    public function isConfigured(): bool
    {
        return is_string(config('services.google_sheets.spreadsheet_id'))
            && config('services.google_sheets.spreadsheet_id') !== ''
            && is_string(config('services.google_sheets.service_account_json_base64'))
            && config('services.google_sheets.service_account_json_base64') !== ''
            && is_string(config('services.google_sheets.worksheet'))
            && config('services.google_sheets.worksheet') !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForJudge(string $judgeId, string $teamId): ?array
    {
        $assessment = null;

        foreach ($this->rows() as $row) {
            if (($row[9] ?? null) === $judgeId && ($row[10] ?? null) === $teamId) {
                if ($assessment !== null) {
                    throw new GoogleSheetsException('Google Sheets contains duplicate assessment rows.');
                }

                $assessment = $this->assessmentFromRow($row);
            }
        }

        return $assessment;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forJudge(string $judgeId): array
    {
        $assessments = [];

        foreach ($this->rows() as $row) {
            if (($row[9] ?? null) !== $judgeId) {
                continue;
            }

            $teamId = $row[10] ?? null;
            if (! is_string($teamId) || $teamId === '' || isset($assessments[$teamId])) {
                throw new GoogleSheetsException('Google Sheets contains an invalid or duplicate assessment row.');
            }

            $assessments[$teamId] = $this->assessmentFromRow($row);
        }

        return $assessments;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forTeam(string $teamId): array
    {
        $assessments = [];
        $judgeIds = [];

        foreach ($this->rows() as $row) {
            if (($row[10] ?? null) !== $teamId) {
                continue;
            }

            $judgeId = $row[9] ?? null;
            if (! is_string($judgeId) || $judgeId === '' || isset($judgeIds[$judgeId])) {
                throw new GoogleSheetsException('Google Sheets contains an invalid or duplicate assessment row.');
            }

            $assessments[] = $this->assessmentFromRow($row);
            $judgeIds[$judgeId] = true;
        }

        return $assessments;
    }

    /**
     * @param  array<string, int|string>  $scores
     */
    public function saveForJudge(
        string $judgeId,
        string $judgeName,
        string $teamId,
        string $teamName,
        array $scores,
        ?string $comment,
    ): void {
        $rows = $this->rows();
        $scoreValues = [];

        foreach (array_keys(self::CRITERIA) as $criterion) {
            $score = $scores[$criterion] ?? null;
            if (is_string($score)) {
                $score = filter_var($score, FILTER_VALIDATE_INT);
            }

            if (! is_int($score) || $score < 1 || $score > 5) {
                throw new GoogleSheetsException('The assessment score values are invalid.');
            }

            $scoreValues[] = $score;
        }

        $row = [
            $judgeName,
            $teamName,
            ...$scoreValues,
            array_sum($scoreValues),
            $comment ?? '',
            $judgeId,
            $teamId,
        ];

        $matchingRow = null;
        foreach ($rows as $index => $existingRow) {
            if (($existingRow[9] ?? null) === $judgeId && ($existingRow[10] ?? null) === $teamId) {
                if ($matchingRow !== null) {
                    throw new GoogleSheetsException('Google Sheets contains duplicate assessment rows.');
                }

                $matchingRow = $index + 2;
            }
        }

        if ($matchingRow !== null) {
            $this->updateRow($matchingRow, $row);

            return;
        }

        $this->appendRow($row);
    }

    /**
     * @return list<array<mixed>>
     */
    private function rows(): array
    {
        $response = $this->request()
            ->withToken($this->getAccessToken());

        try {
            $response = $response->get($this->valuesEndpoint(), ['valueRenderOption' => 'UNFORMATTED_VALUE']);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google Sheets could not read the assessment worksheet.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GoogleSheetsException('Google Sheets could not read the assessment worksheet (HTTP '.$response->status().').');
        }

        $values = $response->json('values', []);
        if (! is_array($values)) {
            throw new GoogleSheetsException('Google Sheets returned an invalid worksheet response.');
        }

        if ($values === []) {
            $this->writeValues($this->worksheetCellsRange('A1:K1'), [self::HEADERS]);
            $this->hideInternalColumns();

            return [];
        }

        if (! is_array($values[0] ?? null) || array_values($values[0]) !== self::HEADERS) {
            throw new GoogleSheetsException('The Google Sheets worksheet has an unexpected header row.');
        }

        $this->hideInternalColumns();

        $rows = [];
        foreach (array_slice($values, 1) as $row) {
            if (! is_array($row)) {
                throw new GoogleSheetsException('Google Sheets contains an invalid assessment row.');
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<mixed>  $row
     * @return array<string, mixed>
     */
    private function assessmentFromRow(array $row): array
    {
        if (count($row) < count(self::HEADERS)) {
            throw new GoogleSheetsException('Google Sheets contains an incomplete assessment row.');
        }

        $scores = [];
        foreach (array_keys(self::CRITERIA) as $index => $criterion) {
            $score = $row[$index + 2] ?? null;
            if (! is_int($score) && ! (is_float($score) && floor($score) === $score)) {
                throw new GoogleSheetsException('Google Sheets contains an invalid assessment score.');
            }

            $score = (int) $score;
            if ($score < 1 || $score > 5) {
                throw new GoogleSheetsException('Google Sheets contains an out-of-range assessment score.');
            }

            $scores[$criterion] = $score;
        }

        if (! is_numeric($row[7] ?? null) || (int) $row[7] !== array_sum($scores)) {
            throw new GoogleSheetsException('Google Sheets contains an invalid assessment total.');
        }

        return [
            'judge_id' => (string) $row[9],
            'judge_name' => (string) $row[0],
            'team_id' => (string) $row[10],
            'team_name' => (string) $row[1],
            'scores' => $scores,
            'total' => (int) $row[7],
            'comment' => (string) ($row[8] ?? ''),
        ];
    }

    /**
     * @param  array<mixed>  $row
     */
    private function updateRow(int $rowNumber, array $row): void
    {
        $this->writeValues($this->worksheetCellsRange('A'.$rowNumber.':K'.$rowNumber), [$row]);
    }

    /**
     * @param  array<mixed>  $row
     */
    private function appendRow(array $row): void
    {
        $range = $this->worksheetRange();

        try {
            $response = $this->request()
                ->withToken($this->getAccessToken())
                ->post($this->valuesEndpoint($range).':append?'.http_build_query([
                    'valueInputOption' => 'RAW',
                    'insertDataOption' => 'INSERT_ROWS',
                ]), [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => [$row],
                ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google Sheets could not append the assessment.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GoogleSheetsException('Google Sheets rejected the assessment (HTTP '.$response->status().').');
        }
    }

    /**
     * @param  list<array<mixed>>  $values
     */
    private function writeValues(string $range, array $values): void
    {
        try {
            $request = $this->request()
                ->withToken($this->getAccessToken())
                ->asJson();

            $response = $request->put($this->valuesEndpoint($range).'?valueInputOption=RAW', [
                'majorDimension' => 'ROWS',
                'values' => $values,
            ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google Sheets could not save the assessment data.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GoogleSheetsException('Google Sheets rejected the assessment data (HTTP '.$response->status().').');
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $credentials = $this->credentials();
        if (! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null)) {
            throw new GoogleSheetsException('Google service account credentials are invalid.');
        }

        $issuedAt = time();
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => self::GOOGLE_TOKEN_URI,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];
        $unsignedToken = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR)).'.'.$this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false || ! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new GoogleSheetsException('Google service account signing failed.');
        }

        $assertion = $unsignedToken.'.'.$this->base64UrlEncode($signature);

        try {
            $response = $this->request()->asForm()->post(self::GOOGLE_TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google authentication service is unavailable.', previous: $exception);
        }

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new GoogleSheetsException('Google service account authentication failed (HTTP '.$response->status().').');
        }

        return $this->accessToken = $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $encodedCredentials = config('services.google_sheets.service_account_json_base64');
        if (! is_string($encodedCredentials) || $encodedCredentials === '') {
            throw new GoogleSheetsException('Google Sheets is not configured.');
        }

        $json = base64_decode($encodedCredentials, true);
        if (! is_string($json)) {
            throw new GoogleSheetsException('Google service account credentials are invalid.');
        }

        try {
            $credentials = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GoogleSheetsException('Google service account credentials are invalid.', previous: $exception);
        }

        if (! is_array($credentials)) {
            throw new GoogleSheetsException('Google service account credentials are invalid.');
        }

        return $credentials;
    }

    private function valuesEndpoint(?string $range = null): string
    {
        $range ??= $this->worksheetRange();

        return 'https://sheets.googleapis.com/v4/spreadsheets/'
            .rawurlencode((string) config('services.google_sheets.spreadsheet_id'))
            .'/values/'.rawurlencode($range);
    }

    private function worksheetRange(): string
    {
        return $this->worksheetCellsRange('A:K');
    }

    private function hideInternalColumns(): void
    {
        if ($this->internalColumnsHidden) {
            return;
        }

        try {
            $response = $this->request()
                ->withToken($this->getAccessToken())
                ->get($this->spreadsheetEndpoint(), [
                    'ranges' => $this->worksheetCellsRange('J:K'),
                    'includeGridData' => 'true',
                    'fields' => 'sheets(properties(sheetId,title),data(startColumn,columnMetadata(hiddenByUser)))',
                ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google Sheets could not read worksheet column settings.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GoogleSheetsException('Google Sheets could not read worksheet column settings (HTTP '.$response->status().').');
        }

        $sheets = $response->json('sheets');
        if (! is_array($sheets)) {
            throw new GoogleSheetsException('Google Sheets returned invalid worksheet column settings.');
        }

        $worksheet = (string) config('services.google_sheets.worksheet');
        $sheetId = null;
        $hiddenColumns = [];

        foreach ($sheets as $sheet) {
            if (($sheet['properties']['title'] ?? null) !== $worksheet) {
                continue;
            }

            $sheetId = $sheet['properties']['sheetId'] ?? null;
            $gridData = $sheet['data'][0] ?? [];
            $startColumn = $gridData['startColumn'] ?? 0;

            foreach ($gridData['columnMetadata'] ?? [] as $index => $column) {
                $hiddenColumns[$startColumn + $index] = ($column['hiddenByUser'] ?? false) === true;
            }

            break;
        }

        if (! is_int($sheetId)) {
            throw new GoogleSheetsException('Google Sheets worksheet was not found.');
        }

        if (($hiddenColumns[9] ?? false) && ($hiddenColumns[10] ?? false)) {
            $this->internalColumnsHidden = true;

            return;
        }

        try {
            $response = $this->request()
                ->withToken($this->getAccessToken())
                ->post($this->spreadsheetEndpoint().':batchUpdate', [
                    'requests' => [[
                        'updateDimensionProperties' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'dimension' => 'COLUMNS',
                                'startIndex' => 9,
                                'endIndex' => 11,
                            ],
                            'properties' => ['hiddenByUser' => true],
                            'fields' => 'hiddenByUser',
                        ],
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Google Sheets could not hide internal assessment columns.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GoogleSheetsException('Google Sheets could not hide internal assessment columns (HTTP '.$response->status().').');
        }

        $this->internalColumnsHidden = true;
    }

    private function spreadsheetEndpoint(): string
    {
        return 'https://sheets.googleapis.com/v4/spreadsheets/'
            .rawurlencode((string) config('services.google_sheets.spreadsheet_id'));
    }

    private function worksheetCellsRange(string $cells): string
    {
        $worksheet = str_replace("'", "''", (string) config('services.google_sheets.worksheet'));

        return "'".$worksheet."'!".$cells;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
