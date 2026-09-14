<?php

namespace Tests\Unit;

use App\Models\WhatsappContact;
use PHPUnit\Framework\TestCase;

/**
 * Pedido explícito en vivo: el mismo cliente aparecía como varias fichas
 * distintas en "Clientes" porque cada canal guardaba su teléfono en un
 * formato distinto (WhatsApp/Meta: "593988492339"; micrositio o admin: a
 * veces "0988492339" o "988492339", tal como lo escribió la persona).
 */
class WhatsappContactPhoneNormalizationTest extends TestCase
{
    /** @dataProvider phoneFormats */
    public function test_every_common_format_of_the_same_number_normalizes_identically(string $input): void
    {
        $this->assertSame('593988492339', WhatsappContact::normalizePhone($input));
    }

    public static function phoneFormats(): array
    {
        return [
            'ya viene en formato de Meta' => ['593988492339'],
            'formato local con cero inicial' => ['0988492339'],
            'sin cero ni código de país' => ['988492339'],
            'con espacios y guiones' => ['098-849-2339'],
            'con signo de más' => ['+593988492339'],
        ];
    }

    public function test_an_unrelated_international_number_is_kept_as_is(): void
    {
        // 11 dígitos: no encaja con ninguno de los patrones locales de
        // Ecuador (9 o 10 dígitos), así que no se le fuerza el 593.
        $this->assertSame('12025550123', WhatsappContact::normalizePhone('12025550123'));
    }

    public function test_garbage_input_returns_null(): void
    {
        $this->assertNull(WhatsappContact::normalizePhone('abc123!!'));
        $this->assertNull(WhatsappContact::normalizePhone(''));
        $this->assertNull(WhatsappContact::normalizePhone(null));
    }
}
