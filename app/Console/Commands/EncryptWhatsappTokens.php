<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptWhatsappTokens extends Command
{
    protected $signature = 'whatsapp:encrypt-tokens';

    protected $description = 'Cifra en su lugar los access_token en texto plano de whatsapp_business_profiles. Correr una sola vez, antes de desplegar el cast "encrypted" del modelo.';

    public function handle(): int
    {
        $rows = DB::table('whatsapp_business_profiles')
            ->whereNotNull('access_token')
            ->get(['id', 'access_token']);

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($this->looksAlreadyEncrypted($row->access_token)) {
                $skipped++;
                continue;
            }

            DB::table('whatsapp_business_profiles')
                ->where('id', $row->id)
                ->update(['access_token' => Crypt::encryptString($row->access_token)]);

            $updated++;
        }

        $this->info("Tokens cifrados: {$updated}. Ya cifrados (sin tocar): {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Los payloads de Crypt::encryptString() son JSON base64 con las claves
     * iv/value/mac; un access_token de Meta nunca tiene esa forma, así que
     * sirve para no cifrar dos veces si el comando se corre por error de nuevo.
     */
    private function looksAlreadyEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
}
