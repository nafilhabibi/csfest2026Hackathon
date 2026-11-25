<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmissionAvailability
{
    private const SETTING_KEY = 'submissions_closed';

    public function isClosed(): bool
    {
        $value = DB::table('app_settings')
            ->where('setting_key', self::SETTING_KEY)
            ->value('setting_value');

        if (! in_array($value, ['0', '1'], true)) {
            throw new RuntimeException('The submission availability setting is missing or invalid.');
        }

        return $value === '1';
    }

    public function setClosed(bool $closed): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['setting_key' => self::SETTING_KEY],
            [
                'setting_value' => $closed ? '1' : '0',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
