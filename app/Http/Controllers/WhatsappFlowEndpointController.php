<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Endpoint cifrado requerido por Meta para publicar y probar WhatsApp Flows. */
class WhatsappFlowEndpointController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $payload = $this->decrypt($request->json()->all());
            Log::info('[WhatsApp Flow endpoint] Solicitud recibida', [
                'action' => $payload['action'] ?? 'unknown',
                'screen' => $payload['screen'] ?? null,
            ]);

            // Meta envía action=ping durante la validación del endpoint. En
            // ese caso, exige explícitamente data.status=active. El Flow
            // actual es estático: el resultado final llegará como nfm_reply.
            $responseData = ($payload['action'] ?? null) === 'ping'
                ? ['status' => 'active']
                : [];

            // Meta espera el Base64 directamente en el cuerpo HTTP, no un JSON
            // que contenga la propiedad encrypted_flow_data.
            return response($this->encrypt([
                'version' => '3.0',
                'data' => $responseData,
            ]), 200)->header('Content-Type', 'text/plain');
        } catch (Throwable $e) {
            Log::warning('[WhatsApp Flow endpoint] Error de cifrado', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Unable to process Flow request.'], 421);
        }
    }

    private function decrypt(array $request): array
    {
        foreach (['encrypted_flow_data', 'encrypted_aes_key', 'initial_vector'] as $field) {
            if (empty($request[$field])) {
                throw new \RuntimeException("Missing {$field}.");
            }
        }

        $privateKey = Storage::disk('local')->get(config('whatsapp.flows.private_key_path'));
        $aesKey = '';
        if (!openssl_private_decrypt(
            base64_decode($request['encrypted_aes_key'], true),
            $aesKey,
            $privateKey,
            OPENSSL_PKCS1_OAEP_PADDING,
            'sha256'
        )) {
            throw new \RuntimeException('Unable to decrypt the AES key.');
        }

        $iv = base64_decode($request['initial_vector'], true);
        $encrypted = base64_decode($request['encrypted_flow_data'], true);
        if ($iv === false || $encrypted === false || strlen($encrypted) < 17) {
            throw new \RuntimeException('Invalid encrypted Flow payload.');
        }

        $tag = substr($encrypted, -16);
        $ciphertext = substr($encrypted, 0, -16);
        $plain = openssl_decrypt($ciphertext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Unable to decrypt Flow payload.');
        }

        $this->aesKey = $aesKey;
        $this->responseIv = $this->flipIv($iv);

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private string $aesKey;
    private string $responseIv;

    private function encrypt(array $response): string
    {
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'aes-128-gcm',
            $this->aesKey,
            OPENSSL_RAW_DATA,
            $this->responseIv,
            $tag
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt Flow response.');
        }

        return base64_encode($ciphertext . $tag);
    }

    private function flipIv(string $iv): string
    {
        return implode('', array_map(
            static fn (string $byte): string => chr(ord($byte) ^ 0xFF),
            str_split($iv)
        ));
    }
}
