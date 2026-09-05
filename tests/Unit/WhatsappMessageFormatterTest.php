<?php

namespace Tests\Unit;

use App\Helpers\WhatsappMessageFormatter;
use PHPUnit\Framework\TestCase;

class WhatsappMessageFormatterTest extends TestCase
{
    public function test_image_metadata_is_never_exposed_as_chat_text(): void
    {
        $content = json_encode([
            'mime_type' => 'image/jpeg',
            'sha256' => 'technical-hash',
            'id' => '123456789',
            'url' => 'https://lookaside.fbsbx.com/private-image',
        ]);

        $this->assertSame('', WhatsappMessageFormatter::mediaCaption($content));
        $this->assertSame('Imagen', WhatsappMessageFormatter::displayText($content, 'image'));
    }

    public function test_image_caption_is_preserved_without_showing_technical_metadata(): void
    {
        $content = json_encode([
            'id' => '123456789',
            'sha256' => 'technical-hash',
            'caption' => 'Comprobante del cliente',
        ]);

        $this->assertSame('Comprobante del cliente', WhatsappMessageFormatter::mediaCaption($content));
        $this->assertSame('Comprobante del cliente', WhatsappMessageFormatter::displayText($content, 'image'));
    }
}
