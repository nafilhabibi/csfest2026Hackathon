<?php

namespace Tests\Feature;

use App\Services\GoogleSheetsAssessmentClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetsAssessmentTest extends TestCase
{
    public function test_new_assessment_is_appended_to_google_sheets(): void
    {
        $this->configureGoogleSheets();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    if (! str_contains($request->url(), '/values/')) {
                        return Http::response($this->worksheetMetadata(idsHidden: false));
                    }

                    return Http::response(['values' => [$this->assessmentHeaders()]]);
                }

                return Http::response(['updates' => ['updatedRows' => 1]], 200);
            },
        ]);

        app(GoogleSheetsAssessmentClient::class)->saveForJudge(
            'judge-1',
            'Juri Satu',
            'team-1',
            'Tim Merah',
            [
                'case_fit' => '5',
                'innovation' => '4',
                'feasibility' => '3',
                'solution_design' => '4',
                'prototype' => '5',
            ],
            'Ide kuat.',
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), ':append')
            && $request->hasHeader('Authorization', 'Bearer google-access-token')
            && $request['values'][0] === [
                'Juri Satu', 'Tim Merah', 5, 4, 3, 4, 5, 21, 'Ide kuat.', 'judge-1', 'team-1',
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), ':batchUpdate')
            && $request['requests'][0]['updateDimensionProperties']['range'] === [
                'sheetId' => 17,
                'dimension' => 'COLUMNS',
                'startIndex' => 9,
                'endIndex' => 11,
            ]);
    }

    public function test_empty_worksheet_is_initialized_before_appending_an_assessment(): void
    {
        $this->configureGoogleSheets();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    if (! str_contains($request->url(), '/values/')) {
                        return Http::response($this->worksheetMetadata(idsHidden: false));
                    }

                    return Http::response(['values' => []]);
                }

                return Http::response(['updatedRows' => 1], 200);
            },
        ]);

        app(GoogleSheetsAssessmentClient::class)->saveForJudge(
            'judge-1',
            'Juri Satu',
            'team-1',
            'Tim Merah',
            [
                'case_fit' => 1,
                'innovation' => 1,
                'feasibility' => 1,
                'solution_design' => 1,
                'prototype' => 1,
            ],
            null,
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_contains($request->url(), 'A1%3AK1')
            && $request['values'][0] === $this->assessmentHeaders());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), ':batchUpdate'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), ':append'));
    }

    public function test_existing_assessment_is_updated_instead_of_appended(): void
    {
        $this->configureGoogleSheets();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    if (! str_contains($request->url(), '/values/')) {
                        return Http::response($this->worksheetMetadata(idsHidden: true));
                    }

                    return Http::response([
                        'values' => [
                            $this->assessmentHeaders(),
                            [
                                'Juri Satu', 'Tim Merah', 3, 3, 3, 3, 3, 15, '', 'judge-1', 'team-1',
                            ],
                        ],
                    ]);
                }

                return Http::response(['updatedRows' => 1], 200);
            },
        ]);

        app(GoogleSheetsAssessmentClient::class)->saveForJudge(
            'judge-1',
            'Juri Satu',
            'team-1',
            'Tim Merah',
            [
                'case_fit' => 5,
                'innovation' => 5,
                'feasibility' => 4,
                'solution_design' => 4,
                'prototype' => 5,
            ],
            null,
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_contains($request->url(), 'A2%3AK2')
            && $request['values'][0] === [
                'Juri Satu', 'Tim Merah', 5, 5, 4, 4, 5, 23, '', 'judge-1', 'team-1',
            ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), ':append'));
    }

    public function test_assessment_reads_back_only_matching_judge_rows(): void
    {
        $this->configureGoogleSheets();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if (! str_contains($request->url(), '/values/')) {
                    return Http::response($this->worksheetMetadata(idsHidden: true));
                }

                return Http::response([
                    'values' => [
                        $this->assessmentHeaders(),
                        ['Juri Satu', 'Tim Merah', 5, 4, 3, 4, 5, 21, 'Ide kuat.', 'judge-1', 'team-1'],
                        ['Juri Dua', 'Tim Biru', 3, 3, 3, 3, 3, 15, '', 'judge-2', 'team-2'],
                    ],
                ]);
            },
        ]);

        $assessments = app(GoogleSheetsAssessmentClient::class)->forJudge('judge-1');

        $this->assertSame(['team-1'], array_keys($assessments));
        $this->assertSame([
            'judge_id' => 'judge-1',
            'judge_name' => 'Juri Satu',
            'team_id' => 'team-1',
            'team_name' => 'Tim Merah',
            'scores' => [
                'case_fit' => 5,
                'innovation' => 4,
                'feasibility' => 3,
                'solution_design' => 4,
                'prototype' => 5,
            ],
            'total' => 21,
            'comment' => 'Ide kuat.',
        ], $assessments['team-1']);
    }

    public function test_team_assessments_include_only_the_requested_team(): void
    {
        $this->configureGoogleSheets();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if (! str_contains($request->url(), '/values/')) {
                    return Http::response($this->worksheetMetadata(idsHidden: true));
                }

                return Http::response([
                    'values' => [
                        $this->assessmentHeaders(),
                        ['Juri Satu', 'Tim Merah', 5, 4, 3, 4, 5, 21, 'Solusi kuat.', 'judge-1', 'team-1'],
                        ['Juri Dua', 'Tim Merah', 4, 4, 4, 4, 4, 20, '', 'judge-2', 'team-1'],
                        ['Juri Tiga', 'Tim Biru', 1, 1, 1, 1, 1, 5, 'Tim lain.', 'judge-3', 'team-2'],
                    ],
                ]);
            },
        ]);

        $assessments = app(GoogleSheetsAssessmentClient::class)->forTeam('team-1');

        $this->assertSame([
            [
                'judge_id' => 'judge-1',
                'judge_name' => 'Juri Satu',
                'team_id' => 'team-1',
                'team_name' => 'Tim Merah',
                'scores' => [
                    'case_fit' => 5,
                    'innovation' => 4,
                    'feasibility' => 3,
                    'solution_design' => 4,
                    'prototype' => 5,
                ],
                'total' => 21,
                'comment' => 'Solusi kuat.',
            ],
            [
                'judge_id' => 'judge-2',
                'judge_name' => 'Juri Dua',
                'team_id' => 'team-1',
                'team_name' => 'Tim Merah',
                'scores' => [
                    'case_fit' => 4,
                    'innovation' => 4,
                    'feasibility' => 4,
                    'solution_design' => 4,
                    'prototype' => 4,
                ],
                'total' => 20,
                'comment' => '',
            ],
        ], $assessments);
    }

    public function test_assessment_endpoint_rejects_scores_outside_the_allowed_range(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 'judge-1',
                'name' => 'Juri Satu',
                'role' => 'judge',
            ],
        ])->post(route('admin.submissions.assessment.store', 'team-1'), [
            'scores' => [
                'case_fit' => 6,
                'innovation' => 4,
                'feasibility' => 3,
                'solution_design' => 4,
                'prototype' => 5,
            ],
        ]);

        $response->assertSessionHasErrors(['scores.case_fit']);
    }

    public function test_assessment_endpoint_rejects_unexpected_criterion_keys(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 'judge-1',
                'name' => 'Juri Satu',
                'role' => 'judge',
            ],
        ])->post(route('admin.submissions.assessment.store', 'team-1'), [
            'scores' => [
                'case_fit' => 3,
                'innovation' => 3,
                'feasibility' => 3,
                'solution_design' => 3,
                'prototype' => 3,
                'role' => 'admin',
            ],
        ]);

        $response->assertSessionHasErrors(['scores']);
    }

    public function test_non_judge_cannot_submit_an_assessment(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 'admin-1',
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.submissions.assessment.store', 'team-1'), []);

        $response->assertForbidden();
    }

    private function configureGoogleSheets(): void
    {
        $privateKeyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($privateKeyResource);
        $this->assertTrue(openssl_pkey_export($privateKeyResource, $privateKey));

        config([
            'services.google_sheets.spreadsheet_id' => 'test-spreadsheet',
            'services.google_sheets.worksheet' => 'Penilaian',
            'services.google_sheets.service_account_json_base64' => base64_encode(json_encode([
                'client_email' => 'judge-sheets@example.iam.gserviceaccount.com',
                'private_key' => $privateKey,
            ], JSON_THROW_ON_ERROR)),
        ]);
    }

    /**
     * @return list<string>
     */
    private function assessmentHeaders(): array
    {
        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    private function worksheetMetadata(bool $idsHidden): array
    {
        return [
            'sheets' => [[
                'properties' => [
                    'sheetId' => 17,
                    'title' => 'Penilaian',
                ],
                'data' => [[
                    'startColumn' => 9,
                    'columnMetadata' => [
                        ['hiddenByUser' => $idsHidden],
                        ['hiddenByUser' => $idsHidden],
                    ],
                ]],
            ]],
        ];
    }
}
