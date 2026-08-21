<?php

namespace App\Console\Commands;

use App\Models\WhatsappContact;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWhatsAppTemplate extends Command
{
    protected $signature = 'whatsapp:send-template {template_name}';
    protected $description = 'Envía una plantilla de WhatsApp a todos los contactos activos';

    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    public function handle()
    {
        $templateName = $this->argument('template_name');

        // Buscar la plantilla
        $template = WhatsappTemplate::where('name', $templateName)
            ->where('status', 'approved')
            ->first();

        if (!$template) {
            $this->error("No se encontró la plantilla '{$templateName}' o no está activa");
            return 1;
        }

        // Debug: Mostrar estructura de la plantilla
        Log::info('Estructura de la plantilla:', [
            'name' => $template->name,
            'components' => $template->components
        ]);

        // Obtener todos los contactos activos
        $contacts = WhatsappContact::where('status', 'active')->get();

        if ($contacts->isEmpty()) {
            $this->error('No hay contactos activos para enviar el mensaje');
            return 1;
        }

        $this->info("Iniciando envío de plantilla '{$templateName}' a {$contacts->count()} contactos...");

        $bar = $this->output->createProgressBar($contacts->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($contacts as $contact) {
            try {
                // Usar el helper para armar los parámetros correctamente
                $customValues = [];
                if (isset($template->components) && is_array($template->components)) {
                    foreach ($template->components as $component) {
                        $type = strtoupper($component['type'] ?? '');
                        if (in_array(strtolower($type), ['header', 'body'])) {
                            // Debug: Mostrar información del componente
                            Log::info('Procesando componente:', [
                                'type' => $type,
                                'format' => $component['format'] ?? 'none',
                                'text' => $component['text'] ?? 'none',
                                'parameters' => $component['parameters'] ?? []
                            ]);

                            // Si es header con formato text, usar el nombre del contacto
                            if (strtolower($type) === 'header' &&
                                isset($component['format']) &&
                                strtolower($component['format']) === 'text') {
                                $customValues[] = $contact->name ?? 'Cliente';
                                Log::info('Valor automático para header:', ['value' => $contact->name ?? 'Cliente']);
                            } else {
                                $label = "Valor para variable #" . (count($customValues) + 1) . " ($type)";
                                if (isset($component['text'])) {
                                    $label .= ": Ejemplo: " . $component['text'];
                                }
                                $value = $this->ask($label);
                                // Si el valor es vacío, usar ejemplo o valor por defecto
                                if (empty($value)) {
                                    // Buscar ejemplo en el componente
                                    $ejemplo = null;
                                    if (isset($component['example']['body_text'][0][0])) {
                                        $ejemplo = $component['example']['body_text'][0][0];
                                    } elseif (isset($component['example']['header_text'][0])) {
                                        $ejemplo = $component['example']['header_text'][0];
                                    }
                                    $value = $ejemplo ?? 'valor';
                                    $this->warn("No ingresaste valor, se usará: $value");
                                }
                                $customValues[] = $value;
                                Log::info('Valor ingresado:', ['value' => $value]);
                            }
                        }
                    }
                }

                // Debug: Mostrar valores recolectados
                Log::info('Valores recolectados:', ['customValues' => $customValues]);

                $variables = $this->whatsappService->buildTemplateVariables($template, $contact, $customValues);

                // Debug: Mostrar variables finales
                Log::info('Variables finales:', ['variables' => $variables]);

                // Mostrar resumen y pedir confirmación
                $this->info("\nResumen de valores para el contacto {$contact->phone_number}:");
                foreach ($variables as $idx => $val) {
                    if (is_array($val)) {
                        $this->info("  Variable #" . ($idx + 1) . ": " . json_encode($val));
                    } else {
                        $this->info("  Variable #" . ($idx + 1) . ": " . $val);
                    }
                }

                if (!$this->confirm('¿Deseas enviar la plantilla con estos valores?', true)) {
                    $this->warn('Reingresa los valores para este contacto.');
                    $contactRepeat = true;
                    while ($contactRepeat) {
                        $customValues = [];
                        foreach ($template->components as $component) {
                            $type = strtoupper($component['type'] ?? '');
                            if (in_array(strtolower($type), ['header', 'body'])) {
                                if (strtolower($type) === 'header' &&
                                    isset($component['format']) &&
                                    strtolower($component['format']) === 'text') {
                                    $customValues[] = $contact->name ?? 'Cliente';
                                } else {
                                    $label = "Valor para variable #" . (count($customValues) + 1) . " ($type)";
                                    if (isset($component['text'])) {
                                        $label .= ": Ejemplo: " . $component['text'];
                                    }
                                    $value = $this->ask($label);
                                    if (empty($value)) {
                                        $ejemplo = null;
                                        if (isset($component['example']['body_text'][0][0])) {
                                            $ejemplo = $component['example']['body_text'][0][0];
                                        } elseif (isset($component['example']['header_text'][0])) {
                                            $ejemplo = $component['example']['header_text'][0];
                                        }
                                        $value = $ejemplo ?? 'valor';
                                        $this->warn("No ingresaste valor, se usará: $value");
                                    }
                                    $customValues[] = $value;
                                }
                            }
                        }
                        $variables = $this->whatsappService->buildTemplateVariables($template, $contact, $customValues);

                        // Debug: Mostrar variables en el reingreso
                        Log::info('Variables en reingreso:', ['variables' => $variables]);

                        $this->info("\nResumen de valores para el contacto {$contact->phone_number}:");
                        foreach ($variables as $idx => $val) {
                            if (is_array($val)) {
                                $this->info("  Variable #" . ($idx + 1) . ": " . json_encode($val));
                            } else {
                                $this->info("  Variable #" . ($idx + 1) . ": " . $val);
                            }
                        }
                        $contactRepeat = !$this->confirm('¿Deseas enviar la plantilla con estos valores?', true);
                        if ($contactRepeat) {
                            $this->warn('Reingresa los valores para este contacto.');
                        }
                    }
                }

                Log::info('Enviando plantilla', [
                    'contact' => $contact->phone_number,
                    'variables' => $variables,
                    'template' => $template->name
                ]);

                $result = $this->whatsappService->sendTemplateMessage($contact, $template, $variables);

                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                    Log::error('Error al enviar plantilla', [
                        'contact' => $contact->phone_number,
                        'template' => $templateName
                    ]);
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error al enviar plantilla', [
                    'contact' => $contact->phone_number,
                    'template' => $templateName,
                    'error' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Envío completado:");
        $this->info("- Mensajes enviados exitosamente: {$successCount}");
        $this->info("- Errores: {$errorCount}");

        return 0;
    }
}
