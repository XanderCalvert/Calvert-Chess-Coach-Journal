<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ShareCodeGenerator
{
    // No 0/o, no 1/i/l — unambiguous lowercase for humans to type or read aloud
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const LENGTH   = 8;

    public static function generate(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (DB::table('games')->where('share_code', $code)->exists());

        return $code;
    }
}
