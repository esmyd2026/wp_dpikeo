<?php

namespace App\Services;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportService
{
    /** @var list<string> */
    public const PRODUCT_HEADERS = [
        'sku',
        'nombre',
        'categoria',
        'precio',
        'precio_promo',
        'descripcion',
        'beneficios',
        'caracteristicas',
        'stock',
        'cant_min',
        'cant_max',
        'activo',
        'permitir_cantidad',
        'demo_cliente',
    ];

    /** @var array<string, string> */
    private const HEADER_ALIASES = [
        'codigo' => 'sku',
        'codigo_producto' => 'sku',
        'name' => 'nombre',
        'category' => 'categoria',
        'categoría' => 'categoria',
        'price' => 'precio',
        'promo' => 'precio_promo',
        'precio_promocional' => 'precio_promo',
        'description' => 'descripcion',
        'descripción' => 'descripcion',
        'benefit' => 'beneficios',
        'beneficio' => 'beneficios',
        'characteristics' => 'caracteristicas',
        'característica' => 'caracteristicas',
        'caracteristicas' => 'caracteristicas',
        'min_quantity' => 'cant_min',
        'cantidad_minima' => 'cant_min',
        'max_quantity' => 'cant_max',
        'cantidad_maxima' => 'cant_max',
        'is_active' => 'activo',
        'activo_en_catalogo' => 'activo',
        'allow_quantity_selection' => 'permitir_cantidad',
        'demo' => 'demo_cliente',
        'empresa' => 'demo_cliente',
    ];

    public function __construct(
        private readonly DemoClienteService $demoCliente,
    ) {}

    public function templateDownloadResponse(?int $businessProfileId = null): StreamedResponse
    {
        $categories = WhatsappMenuItem::catalogCategories($businessProfileId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'title', 'action_id', 'demo_cliente']);

        $exampleCategory = $categories->first()?->title ?? 'Nombre de categoría existente';
        $businessName = $businessProfileId
            ? (WhatsappBusinessProfile::find($businessProfileId)?->business_name ?? 'tu empresa')
            : 'tu empresa';
        $demoClienteExample = $categories->first()?->demo_cliente ?: '';

        $exampleRow = [
            'PROD-001',
            "Ejemplo: Nuevo producto {$businessName}",
            $exampleCategory,
            12.50,
            '',
            'Descripción breve del producto',
            'Beneficios opcionales',
            'Presentación 1L|Uso industrial',
            50,
            1,
            999,
            'Si',
            'Si',
            $demoClienteExample,
        ];

        return $this->streamWorkbook('plantilla-productos.xlsx', function (Spreadsheet $spreadsheet) use ($categories, $exampleRow, $businessName) {
            $this->buildInstructionsSheet($spreadsheet, $businessName);
            $this->buildProductsSheet($spreadsheet->createSheet(), [$exampleRow], 'Productos');
            $this->buildCategoriesSheet($spreadsheet->createSheet(), $categories);
            $spreadsheet->setActiveSheetIndex(0);
        });
    }

    public function exportDownloadResponse(?int $businessProfileId = null): StreamedResponse
    {
        $products = WhatsappPrice::with('menuCategory:id,title')
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->orderBy('name')
            ->get();

        $rows = $products->map(fn (WhatsappPrice $product) => $this->productToRow($product))->all();

        $filename = 'catalogo-productos-' . now()->format('Y-m-d_His') . '.xlsx';

        return $this->streamWorkbook($filename, function (Spreadsheet $spreadsheet) use ($rows) {
            $this->buildProductsSheet($spreadsheet->getActiveSheet(), $rows, 'Productos');
        });
    }

    /**
     * @return array{created:int,updated:int,skipped:int,errors:list<array{row:int,sku:string,message:string}>}
     */
    public function importFromUpload(UploadedFile $file, string $mode = 'upsert', ?int $businessProfileId = null): array
    {
        $mode = in_array($mode, ['upsert', 'create', 'update'], true) ? $mode : 'upsert';

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Productos') ?? $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow < 2) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['row' => 1, 'sku' => '', 'message' => 'El archivo no contiene filas de productos.']],
            ];
        }

        $columnMap = $this->mapHeaderRow($sheet, $highestColumnIndex);
        $required = ['sku', 'nombre', 'categoria', 'precio'];
        foreach ($required as $field) {
            if (!isset($columnMap[$field])) {
                return [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [[
                        'row' => 1,
                        'sku' => '',
                        'message' => "Falta la columna obligatoria «{$field}» en la hoja Productos.",
                    ]],
                ];
            }
        }

        $categories = $this->loadCategoryCatalog($businessProfileId);
        $existingSkus = WhatsappPrice::query()
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->pluck('id', 'sku')->mapWithKeys(
                fn ($id, $sku) => [strtoupper((string) $sku) => $id]
            )->all();

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = $this->readRow($sheet, $row, $columnMap, $highestColumnIndex);

            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $sku = strtoupper(trim((string) ($rowData['sku'] ?? '')));
            $existingId = $existingSkus[$sku] ?? null;

            if ($mode === 'create' && $existingId) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $row,
                    'sku' => $sku,
                    'message' => 'El SKU ya existe. Use modo «Crear y actualizar» o «Solo actualizar».',
                ];

                continue;
            }

            if ($mode === 'update' && !$existingId) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $row,
                    'sku' => $sku,
                    'message' => 'El SKU no existe. Use modo «Crear y actualizar» o «Solo crear».',
                ];

                continue;
            }

            try {
                $payload = $this->buildProductPayload($rowData, $categories, $row, $businessProfileId);
            } catch (\InvalidArgumentException $e) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $row,
                    'sku' => $sku,
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            DB::transaction(function () use ($existingId, $payload, &$result, &$existingSkus, $sku) {
                if ($existingId) {
                    WhatsappPrice::whereKey($existingId)->update($payload);
                    $result['updated']++;
                } else {
                    WhatsappPrice::create($payload);
                    $existingSkus[$sku] = true;
                    $result['created']++;
                }
            });
        }

        return $result;
    }

    /** @param list<list<mixed>> $rows */
    private function buildProductsSheet($sheet, array $rows, string $title): void
    {
        $sheet->setTitle($title);

        foreach (self::PRODUCT_HEADERS as $colIndex => $header) {
            $sheet->setCellValue([$colIndex + 1, 1], $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::PRODUCT_HEADERS));
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '075E54'],
            ],
        ]);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
            }
        }

        foreach (range(1, count(self::PRODUCT_HEADERS)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet, string $businessName = 'tu empresa'): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Instrucciones');

        $lines = [
            ["Carga masiva de productos — {$businessName}"],
            [''],
            ['1. Complete la hoja «Productos». No cambie los nombres de las columnas de la fila 1.'],
            ['2. Columnas obligatorias: sku, nombre, categoria, precio.'],
            ['3. sku: único, hasta 20 caracteres alfanuméricos (ej. PROD-001).'],
            ['4. categoria: nombre exacto de una categoría de la hoja «Categorias» (también acepta ID numérico o action_id).'],
            ['5. precio_promo: opcional; debe ser menor que precio.'],
            ['6. caracteristicas: separe valores con | o saltos de línea dentro de la celda.'],
            ['7. activo / permitir_cantidad: Si, No, 1 o 0.'],
            ["8. Los productos importados pertenecen al catálogo de {$businessName}."],
            ['9. Si el SKU ya existe, el producto se actualiza (modo por defecto).'],
            ['10. Respete el límite de productos de su plan.'],
            [''],
            ['Descargue plantilla vacía o exporte el catálogo actual desde Productos → Importar Excel.'],
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValue([1, $index + 1], $line[0] ?? '');
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(100);
    }

    /** @param \Illuminate\Support\Collection<int, WhatsappMenuItem> $categories */
    private function buildCategoriesSheet($sheet, $categories): void
    {
        $sheet->setTitle('Categorias');
        $headers = ['id', 'nombre', 'action_id', 'demo_cliente'];

        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValue([$colIndex + 1, 1], $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F5E9'],
            ],
        ]);

        foreach ($categories as $index => $category) {
            $sheet->setCellValue([1, $index + 2], $category->id);
            $sheet->setCellValue([2, $index + 2], $category->title);
            $sheet->setCellValue([3, $index + 2], $category->action_id);
            $sheet->setCellValue([4, $index + 2], $category->demo_cliente);
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    private function streamWorkbook(string $filename, callable $builder): StreamedResponse
    {
        return response()->streamDownload(function () use ($builder) {
            $spreadsheet = new Spreadsheet();
            $builder($spreadsheet);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /** @return array<string, int> */
    private function mapHeaderRow($sheet, int $highestColumnIndex): array
    {
        $map = [];

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $raw = strtolower(trim((string) $sheet->getCell([$col, 1])->getValue()));
            $raw = str_replace([' ', '-'], '_', $raw);
            $raw = $this->removeAccents($raw);
            $key = self::HEADER_ALIASES[$raw] ?? $raw;

            if (in_array($key, self::PRODUCT_HEADERS, true)) {
                $map[$key] = $col;
            }
        }

        return $map;
    }

    /** @param array<string, int> $columnMap */
    private function readRow($sheet, int $row, array $columnMap, int $highestColumnIndex): array
    {
        $data = [];

        foreach ($columnMap as $field => $col) {
            $data[$field] = $sheet->getCell([$col, $row])->getCalculatedValue();
        }

        return $data;
    }

    /** @param array<string, mixed> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach (['sku', 'nombre', 'categoria', 'precio'] as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{index: array<string, WhatsappMenuItem>, title_counts: array<string, int>} */
    private function loadCategoryCatalog(?int $businessProfileId = null): array
    {
        $index = [];
        $titleCounts = [];

        foreach (WhatsappMenuItem::catalogCategories($businessProfileId)->get() as $category) {
            $titleLower = mb_strtolower(trim($category->title));
            $titleCounts[$titleLower] = ($titleCounts[$titleLower] ?? 0) + 1;

            $index['id:' . $category->id] = $category;
            $index['title:' . $titleLower] = $category;

            $demo = trim((string) ($category->demo_cliente ?? ''));
            if ($demo !== '') {
                $index['title:' . $titleLower . '|' . mb_strtolower($demo)] = $category;
            }

            if ($category->action_id) {
                $index['action:' . mb_strtolower(trim($category->action_id))] = $category;
            }
        }

        return [
            'index' => $index,
            'title_counts' => $titleCounts,
        ];
    }

    /**
     * @param array{index: array<string, WhatsappMenuItem>, title_counts: array<string, int>} $catalog
     */
    private function resolveCategory(string $ref, array $catalog, ?string $demoCliente): ?WhatsappMenuItem
    {
        if ($ref === '') {
            return null;
        }

        $index = $catalog['index'];
        $titleCounts = $catalog['title_counts'];

        if (is_numeric($ref)) {
            return $index['id:' . (int) $ref] ?? null;
        }

        $lower = mb_strtolower(trim($ref));

        if ($demoCliente) {
            $specific = $index['title:' . $lower . '|' . mb_strtolower($demoCliente)] ?? null;
            if ($specific) {
                return $specific;
            }
        }

        if (($titleCounts[$lower] ?? 0) > 1 && !$demoCliente) {
            throw new \InvalidArgumentException(
                "Hay varias categorías «{$ref}» en distintas empresas. Indique demo_cliente en la fila (vea hoja Categorias)."
            );
        }

        return $index['title:' . $lower]
            ?? $index['action:' . $lower]
            ?? null;
    }

    /**
     * @param array{index: array<string, WhatsappMenuItem>, title_counts: array<string, int>} $catalog
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildProductPayload(array $row, array $catalog, int $excelRow, ?int $businessProfileId = null): array
    {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku === '' || strlen($sku) > 20 || !preg_match('/^[A-Z0-9\-]+$/', $sku)) {
            throw new \InvalidArgumentException('SKU inválido (obligatorio, hasta 20 caracteres alfanuméricos).');
        }

        $name = trim((string) ($row['nombre'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }

        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('El nombre no puede superar 255 caracteres.');
        }

        $categoryRef = trim((string) ($row['categoria'] ?? ''));
        $demoClienteRaw = trim((string) ($row['demo_cliente'] ?? ''));
        $demoClienteForLookup = $demoClienteRaw !== ''
            ? $this->normalizeDemoClienteSlug($demoClienteRaw)
            : null;

        if ($demoClienteRaw !== '' && $demoClienteForLookup === null) {
            throw new \InvalidArgumentException($this->unknownDemoClienteMessage($demoClienteRaw));
        }

        $category = $this->resolveCategory($categoryRef, $catalog, $demoClienteForLookup);
        if (!$category) {
            throw new \InvalidArgumentException("Categoría «{$categoryRef}» no encontrada. Revise la hoja Categorias.");
        }

        $demoCliente = $this->resolveDemoCliente($demoClienteRaw, $category);

        $price = $this->parseNumber($row['precio'] ?? null);
        if ($price === null || $price < 0) {
            throw new \InvalidArgumentException('Precio inválido.');
        }

        $promoRaw = $row['precio_promo'] ?? null;
        $promoPrice = null;
        if ($promoRaw !== null && trim((string) $promoRaw) !== '') {
            $promoPrice = $this->parseNumber($promoRaw);
            if ($promoPrice === null || $promoPrice <= 0 || $promoPrice >= $price) {
                throw new \InvalidArgumentException('precio_promo debe ser mayor que 0 y menor que precio.');
            }
        }

        $minQty = max(1, (int) ($this->parseNumber($row['cant_min'] ?? 1) ?? 1));
        $maxQty = max($minQty, (int) ($this->parseNumber($row['cant_max'] ?? 999) ?? 999));

        return [
            'menu_item_id' => $category->id,
            'business_profile_id' => $businessProfileId ?? $category->business_profile_id,
            'category' => $category->title,
            'sku' => $sku,
            'name' => $name,
            'description' => $this->nullableString($row['descripcion'] ?? null, 5000),
            'benefits' => $this->nullableString($row['beneficios'] ?? null, 5000),
            'characteristics' => $this->parseCharacteristics($row['caracteristicas'] ?? ''),
            'price' => $price,
            'promo_price' => $promoPrice,
            'is_promo' => $promoPrice !== null,
            'promo_start_date' => $promoPrice !== null ? now()->toDateString() : null,
            'promo_end_date' => $promoPrice !== null ? now()->addDays(30)->toDateString() : null,
            'currency' => 'USD',
            'is_active' => $this->parseBool($row['activo'] ?? null, true),
            'demo_cliente' => $demoCliente,
            'stock' => max(0, (int) ($this->parseNumber($row['stock'] ?? 0) ?? 0)),
            'allow_quantity_selection' => $this->parseBool($row['permitir_cantidad'] ?? null, true),
            'min_quantity' => $minQty,
            'max_quantity' => $maxQty,
        ];
    }

    private function resolveDemoCliente(string $raw, WhatsappMenuItem $category): ?string
    {
        if ($raw === '') {
            return $category->demo_cliente ?: null;
        }

        $normalized = $this->normalizeDemoClienteSlug($raw);
        if ($normalized === null) {
            throw new \InvalidArgumentException($this->unknownDemoClienteMessage($raw));
        }

        if ($category->demo_cliente && $category->demo_cliente !== $normalized) {
            throw new \InvalidArgumentException(
                "demo_cliente «{$normalized}» no coincide con la categoría «{$category->title}» ({$category->demo_cliente})."
            );
        }

        return $normalized;
    }

    private function normalizeDemoClienteSlug(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $options = $this->demoCliente->options();

        if (array_key_exists($value, $options)) {
            return $value;
        }

        foreach ($options as $slug => $label) {
            if (strcasecmp($slug, $value) === 0 || strcasecmp($label, $value) === 0) {
                return $slug;
            }
        }

        return null;
    }

    private function unknownDemoClienteMessage(string $value): string
    {
        $options = $this->demoCliente->options();
        $hints = [];

        foreach ($options as $slug => $label) {
            $hints[] = "{$slug} ({$label})";
        }

        $list = $hints !== [] ? implode(', ', $hints) : 'ninguno configurado';

        return "demo_cliente «{$value}» no reconocido. Valores válidos: {$list}.";
    }

    private function productToRow(WhatsappPrice $product): array
    {
        $characteristics = $product->characteristics;
        if (is_string($characteristics)) {
            $decoded = json_decode($characteristics, true);
            $characteristics = is_array($decoded) ? $decoded : [];
        }

        return [
            $product->sku,
            $product->name,
            $product->menuCategory?->title ?? $product->category,
            $product->price,
            $product->promo_price ?? '',
            $product->description ?? '',
            $product->benefits ?? '',
            is_array($characteristics) ? implode('|', $characteristics) : '',
            $product->stock ?? 0,
            $product->min_quantity ?? 1,
            $product->max_quantity ?? 999,
            $product->is_active ? 'Si' : 'No',
            $product->allow_quantity_selection ? 'Si' : 'No',
            $product->demo_cliente ?? '',
        ];
    }

    private function parseCharacteristics(mixed $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\||\r\n|\r|\n/', $text);

        return array_values(array_filter(array_map('trim', $parts ?: [])));
    }

    private function parseBool(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'si', 'sí', 'yes', 'true', 'activo'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'no', 'false', 'inactivo'], true)) {
            return false;
        }

        return $default;
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = str_replace([' ', '$'], '', (string) $value);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (strlen($text) > $max) {
            throw new \InvalidArgumentException("Texto demasiado largo (máximo {$max} caracteres).");
        }

        return $text;
    }

    private function removeAccents(string $value): string
    {
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ];

        return strtr($value, $replacements);
    }
}
