<?php

namespace Tests\Unit;

use App\Models\Evidence;
use Tests\TestCase;

class EvidenceDisplayNameTest extends TestCase
{
    public function test_short_name_is_returned_untouched(): void
    {
        $evidence = new Evidence(['original_name' => 'foto.jpg']);

        $this->assertSame('foto.jpg', $evidence->displayName());
        $this->assertSame('foto.jpg', $evidence->displayName(maxLength: 10));
    }

    public function test_name_at_limit_is_returned_untouched(): void
    {
        $evidence = new Evidence(['original_name' => '1234567890']);

        $this->assertSame('1234567890', $evidence->displayName(maxLength: 10));
    }

    public function test_long_name_keeps_start_and_extension(): void
    {
        $evidence = new Evidence(['original_name' => 'relatorio-tecnico-adequacao-nr10-completa-2026.pdf']);

        $display = $evidence->displayName();

        $this->assertStringStartsWith('relatorio-tecnico', $display);
        $this->assertStringEndsWith('.pdf', $display);
        $this->assertStringContainsString('...', $display);
        $this->assertLessThanOrEqual(40, mb_strlen($display));
    }

    public function test_long_name_without_extension_ends_with_ellipsis(): void
    {
        $evidence = new Evidence(['original_name' => 'arquivo-sem-extensao-muito-longo-para-caber']);

        $display = $evidence->displayName(maxLength: 20);

        $this->assertLessThanOrEqual(20, mb_strlen($display));
        $this->assertStringEndsWith('...', $display);
    }

    public function test_tiny_limit_still_keeps_single_head_char_and_extension(): void
    {
        $evidence = new Evidence(['original_name' => 'documento-muito-longo-sem-fim.jpg']);

        $this->assertSame('d....jpg', $evidence->displayName(maxLength: 6));
    }

    public function test_empty_name_returns_empty_string(): void
    {
        $evidence = new Evidence(['original_name' => '']);

        $this->assertSame('', $evidence->displayName());
    }
}
