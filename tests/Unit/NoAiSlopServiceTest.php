<?php

namespace Tests\Unit;

use Modules\ContentGenerator\Services\NoAiSlopService;
use Tests\TestCase;

class NoAiSlopServiceTest extends TestCase
{
    private function service(): NoAiSlopService
    {
        return new NoAiSlopService();
    }

    public function test_detects_clean_content_as_empty(): void
    {
        $content = "# Judul Artikel\n\nArtikel ini membahas cara kerja sistem secara lengkap dan mudah dipahami.";
        $this->assertSame([], $this->service()->scan($content, 'id'));
    }

    public function test_detects_banished_word_in_id(): void
    {
        $content = "Perlu digarisbawahi bahwa fitur ini memfasilitasi pengguna baru.";
        $hits = $this->service()->scan($content, 'id');
        $words = array_column($hits, 'word');
        $this->assertContains('perlu digarisbawahi', $words);
        $this->assertContains('memfasilitasi', $words);
    }

    public function test_en_words_not_matched_for_id_locale(): void
    {
        $content = 'The system leverage is robust.';
        $hits = $this->service()->scan($content, 'id');
        $this->assertSame([], $hits);
    }

    public function test_detects_banished_word_in_en(): void
    {
        $content = 'The system leverages robust data to streamline the workflow.';
        $hits = $this->service()->scan($content, 'en');
        $words = array_column($hits, 'word');
        $this->assertContains('leverage', $words);
        $this->assertContains('robust', $words);
    }

    public function test_detects_emoji_in_heading(): void
    {
        $content = "## 🚀 Tips Cepat\n\nParagraf normal.";
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Emoji di heading', $hits[0]['pattern']);
    }

    public function test_detects_binary_contrast_id(): void
    {
        $content = 'Ini bukan sekadar alat, melainkan solusi lengkap.';
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Kontras biner (bukan X, melainkan Y)', $hits[0]['pattern']);
    }

    public function test_detects_colon_reveal_heading(): void
    {
        $content = "## Kunci suksesnya:\n\nParagraf penjelasan.";
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Colon reveal di heading (judul dramatis)', $hits[0]['pattern']);
    }

    public function test_auto_fix_removes_banished_words(): void
    {
        $content = 'Sistem memfasilitasi proses dan berjalan dengan mulus.';
        $fixed = $this->service()->clean($content, 'id', true);
        $this->assertStringNotContainsString('memfasilitasi', $fixed);
        $this->assertStringNotContainsString('dengan mulus', $fixed);
        $this->assertStringContainsString('memudahkan', $fixed);
    }

    public function test_clean_without_auto_fix_keeps_content(): void
    {
        $content = 'Sistem memfasilitasi proses.';
        $this->assertSame($content, $this->service()->clean($content, 'id', false));
    }

    public function test_clean_empty_string_returns_as_is(): void
    {
        $this->assertSame('', $this->service()->clean('', 'id'));
        $this->assertSame([], $this->service()->scan('', 'id'));
    }

    public function test_detects_em_dash(): void
    {
        $content = 'The policy was announced — without warning — last week.';
        $hits = $this->service()->scan($content, 'en');
        $this->assertNotEmpty($hits);
        $this->assertSame('Em/en dash (tanda pisah)', $hits[0]['pattern']);
        $this->assertSame('critical', $hits[0]['severity']);
    }

    public function test_detects_copula_avoidance_id(): void
    {
        $content = 'Aplikasi ini berfungsi sebagai pusat kendali seluruh operasional.';
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Copula avoidance (berfungsi sebagai)', $hits[0]['pattern']);
    }

    public function test_detects_weasel_words_en(): void
    {
        $content = 'Experts believe the river plays a crucial role in the ecosystem.';
        $hits = $this->service()->scan($content, 'en');
        $this->assertNotEmpty($hits);
        $this->assertSame('Weasel words (vague attribution)', $hits[0]['pattern']);
    }

    public function test_detects_chatbot_artifact_id(): void
    {
        $content = 'Semoga membantu. Jika ada pertanyaan, jangan ragu untuk bertanya.';
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Chatbot artifact (jargon obrolan)', $hits[0]['pattern']);
    }

    public function test_detects_generic_conclusion_id(): void
    {
        $content = 'Masa depan industri ini terlihat cerah dan menjanjikan.';
        $hits = $this->service()->scan($content, 'id');
        $this->assertNotEmpty($hits);
        $this->assertSame('Kesimpulan generik', $hits[0]['pattern']);
    }

    public function test_locale_specific_patterns_are_skipped_for_other_locale(): void
    {
        $content = 'Para ahli percaya fitur ini berfungsi sebagai solusi.';
        $hits = $this->service()->scan($content, 'en');
        $this->assertSame([], $hits);
    }

    public function test_should_rewrite_false_for_single_weak_hit(): void
    {
        $hits = [
            ['pattern' => 'banned_word', 'severity' => 'weak', 'word' => 'komprehensif'],
        ];
        $this->assertFalse($this->service()->shouldRewrite($hits, 'id'));
    }

    public function test_should_rewrite_true_for_single_critical_hit(): void
    {
        $hits = [
            ['pattern' => 'Em/en dash (tanda pisah)', 'severity' => 'critical'],
        ];
        $this->assertTrue($this->service()->shouldRewrite($hits, 'id'));
    }

    public function test_should_rewrite_true_for_two_strong_hits(): void
    {
        $hits = [
            ['pattern' => 'Kesimpulan generik', 'severity' => 'strong'],
            ['pattern' => 'Hedging berlebih', 'severity' => 'strong'],
        ];
        $this->assertTrue($this->service()->shouldRewrite($hits, 'id'));
    }

    public function test_should_rewrite_true_for_three_distinct_weak_hits(): void
    {
        $hits = [
            ['pattern' => 'False range (dari X ke Y)', 'severity' => 'weak'],
            ['pattern' => 'Rule of three (A, B, dan C)', 'severity' => 'weak'],
            ['pattern' => 'banned_word', 'severity' => 'weak'],
        ];
        $this->assertTrue($this->service()->shouldRewrite($hits, 'id'));
    }

    public function test_should_rewrite_false_for_repeated_single_weak_pattern(): void
    {
        $hits = [
            ['pattern' => 'banned_word', 'severity' => 'weak', 'word' => 'komprehensif'],
            ['pattern' => 'banned_word', 'severity' => 'weak', 'word' => 'mutakhir'],
            ['pattern' => 'banned_word', 'severity' => 'weak', 'word' => 'revolusioner'],
        ];
        $this->assertFalse($this->service()->shouldRewrite($hits, 'id'));
    }

    public function test_score_returns_counts_and_flag(): void
    {
        $hits = [
            ['pattern' => 'Em/en dash (tanda pisah)', 'severity' => 'critical'],
            ['pattern' => 'Kesimpulan generik', 'severity' => 'strong'],
            ['pattern' => 'banned_word', 'severity' => 'weak'],
        ];
        $score = $this->service()->score($hits, 'id');
        $this->assertSame(1, $score['critical']);
        $this->assertSame(1, $score['strong']);
        $this->assertSame(1, $score['weak']);
        $this->assertTrue($score['should_rewrite']);
    }
}