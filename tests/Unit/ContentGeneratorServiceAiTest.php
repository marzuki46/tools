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
}
