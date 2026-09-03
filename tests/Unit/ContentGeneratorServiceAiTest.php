<?php

namespace Tests\Unit;

use Modules\ContentGenerator\Services\ContentGeneratorService;
use Tests\TestCase;

class ContentGeneratorServiceAiTest extends TestCase
{
    private function invokeAiMessageContent(array $data): ?string
    {
        $service = new ContentGeneratorService();
        $method = new \ReflectionMethod($service, 'aiMessageContent');
        $method->setAccessible(true);
        return $method->invoke($service, $data);
    }

    public function test_returns_content_from_standard_chat_completion(): void
    {
        $data = [
            'choices' => [
                ['message' => ['content' => 'Artikel lengkap...']],
            ],
        ];
        $this->assertSame('Artikel lengkap...', $this->invokeAiMessageContent($data));
    }

    public function test_returns_null_when_choices_empty(): void
    {
        $this->assertNull($this->invokeAiMessageContent([]));
        $this->assertNull($this->invokeAiMessageContent(['choices' => []]));
    }

    public function test_returns_null_when_content_blank_string(): void
    {
        $data = [
            'choices' => [
                ['message' => ['content' => '   ']],
            ],
        ];
        $this->assertNull($this->invokeAiMessageContent($data));
    }

    public function test_returns_reasoning_content_when_content_empty(): void
    {
        $data = [
            'choices' => [
                ['message' => ['content' => '', 'reasoning_content' => 'Pikiran internal model']],
            ],
        ];
        $this->assertSame('Pikiran internal model', $this->invokeAiMessageContent($data));
    }

    public function test_returns_content_array_text_form(): void
    {
        $data = [
            'choices' => [
                ['message' => ['content' => [['text' => 'Konten dari array']]]],
            ],
        ];
        $this->assertSame('Konten dari array', $this->invokeAiMessageContent($data));
    }

    public function test_returns_null_for_malformed_response(): void
    {
        $this->assertNull($this->invokeAiMessageContent(['foo' => 'bar']));
        $this->assertNull($this->invokeAiMessageContent(['choices' => [['message' => []]]]));
    }

    private function invokeHasVisibleText(string $html): bool
    {
        $service = new ContentGeneratorService();
        $method = new \ReflectionMethod($service, 'hasVisibleText');
        $method->setAccessible(true);
        return $method->invoke($service, $html);
    }

    public function test_visible_text_rejects_true_empty_content(): void
    {
        // Tidak ada teks terlihat sama sekali (hanya tag kosong / spasi) ->
        // dianggap TIDAK punya konten, supaya tidak pernah 'selesai tapi 0 kata'.
        $this->assertFalse($this->invokeHasVisibleText('<p></p>'));
        $this->assertFalse($this->invokeHasVisibleText('<p>   </p>'));
        $this->assertFalse($this->invokeHasVisibleText('<p><br></p>'));
        $this->assertFalse($this->invokeHasVisibleText('   '));
        $this->assertFalse($this->invokeHasVisibleText(''));
    }

    public function test_visible_text_accepts_real_content(): void
    {
        $this->assertTrue($this->invokeHasVisibleText('<h2>Judul</h2><p>Ini isi artikel yang nyata.</p>'));
        $this->assertTrue($this->invokeHasVisibleText('<p>Kalimat pertama yang bisa dibaca.</p>'));
    }
}
