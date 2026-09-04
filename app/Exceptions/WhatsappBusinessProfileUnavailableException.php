<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó operar WhatsappService sobre un perfil inexistente o no usable
 * (desconectado/error). Se lanza en vez de reutilizar en silencio el perfil
 * previamente cargado en la instancia -- crítico en loops/jobs que reutilizan
 * una sola instancia entre contactos/empresas distintas.
 */
class WhatsappBusinessProfileUnavailableException extends RuntimeException
{
}
