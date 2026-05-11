<?php

namespace Tests\Feature;

use App\Support\ShareCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_8_char_code_with_expected_alphabet(): void
    {
        $code = ShareCodeGenerator::generate();

        $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/', $code);
    }

    public function test_generate_returns_unique_codes_across_many_calls(): void
    {
        $codes = [];

        for ($i = 0; $i < 50; $i++) {
            $codes[] = ShareCodeGenerator::generate();
        }

        $this->assertCount(count(array_unique($codes)), $codes);
    }
}
