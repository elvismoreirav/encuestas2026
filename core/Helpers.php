<?php

class Helpers
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? BASE_URL : BASE_URL . '/' . $path;
    }

    public static function asset(string $path): string
    {
        return ASSETS_URL . '/' . ltrim($path, '/');
    }

    public static function redirect(string $path = ''): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function setOld(array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        $_SESSION['_old'] = $data;
    }

    public static function clearOld(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        unset($_SESSION['_old']);
    }

    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?: '';
        return trim($value, '-') ?: 'encuesta';
    }

    public static function decodeJson(?string $value, mixed $default = []): mixed
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public static function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public static function requestJson(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isAjax(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }

    public static function formatDateTime(?string $value): string
    {
        if (!$value) {
            return 'Sin registro';
        }

        return date('d/m/Y H:i', strtotime($value));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'scheduled' => 'Programada',
            'closed' => 'Cerrada',
            'archived' => 'Archivada',
            default => 'Borrador',
        };
    }

    public static function userRoleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super administrador',
            'analyst' => 'Analista',
            default => 'Administrativo',
        };
    }

    public static function userStatusLabel(string $status): string
    {
        return $status === 'active' ? 'Activo' : 'Inactivo';
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => SESSION_SECURE,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function downloadXlsx(string $filename, array $sheets): never
    {
        $workbookPath = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($workbookPath === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal para exportar Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($workbookPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($workbookPath);
            throw new RuntimeException('No se pudo inicializar el contenedor del archivo Excel.');
        }

        $normalizedSheets = [];
        foreach (array_values($sheets) as $index => $sheet) {
            $normalizedSheets[] = [
                'name' => self::sanitizeWorksheetName((string) ($sheet['name'] ?? 'Hoja ' . ($index + 1)), $index + 1),
                'rows' => array_values(array_map(
                    static fn(mixed $row): array => array_values(is_array($row) ? $row : [$row]),
                    is_array($sheet['rows'] ?? null) ? $sheet['rows'] : []
                )),
            ];
        }

        $zip->addFromString('[Content_Types].xml', self::buildXlsxContentTypesXml(count($normalizedSheets)));
        $zip->addFromString('_rels/.rels', self::buildXlsxRootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::buildXlsxWorkbookXml($normalizedSheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::buildXlsxWorkbookRelsXml(count($normalizedSheets)));
        $zip->addFromString('xl/styles.xml', self::buildXlsxStylesXml());
        $zip->addFromString('docProps/core.xml', self::buildXlsxCoreXml());
        $zip->addFromString('docProps/app.xml', self::buildXlsxAppXml($normalizedSheets));

        foreach ($normalizedSheets as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                self::buildXlsxWorksheetXml($sheet['rows'])
            );
        }

        $zip->close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $safeFilename = trim($filename) !== '' ? $filename : 'reporte.xlsx';
        if (!str_ends_with(strtolower($safeFilename), '.xlsx')) {
            $safeFilename .= '.xlsx';
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $safeFilename) . '"');
        header('Content-Length: ' . filesize($workbookPath));
        header('Cache-Control: max-age=0, no-store, no-cache, must-revalidate');
        header('Pragma: public');

        readfile($workbookPath);
        @unlink($workbookPath);
        exit;
    }

    private static function sanitizeWorksheetName(string $name, int $fallbackIndex): string
    {
        $clean = preg_replace('/[\[\]\:\*\?\/\\\\]+/', ' ', trim($name)) ?: '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?: '';
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'Hoja ' . $fallbackIndex;
        }

        return mb_substr($clean, 0, 31, 'UTF-8');
    }

    private static function buildXlsxContentTypesXml(int $sheetCount): string
    {
        $sheetOverrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $sheetOverrides
            . '</Types>';
    }

    private static function buildXlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function buildXlsxWorkbookXml(array $sheets): string
    {
        $sheetNodes = '';
        foreach ($sheets as $index => $sheet) {
            $sheetNodes .= '<sheet name="' . self::xmlAttribute($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetNodes . '</sheets>'
            . '</workbook>';
    }

    private static function buildXlsxWorkbookRelsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private static function buildXlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function buildXlsxCoreXml(): string
    {
        $createdAt = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Shalom Encuestas</dc:creator>'
            . '<cp:lastModifiedBy>Shalom Encuestas</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function buildXlsxAppXml(array $sheets): string
    {
        $titles = '';
        foreach ($sheets as $sheet) {
            $titles .= '<vt:lpstr>' . self::xmlText($sheet['name']) . '</vt:lpstr>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Shalom Encuestas</Application>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($sheets) . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private static function buildXlsxWorksheetXml(array $rows): string
    {
        $sheetData = '';
        $columnWidths = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = self::xlsxColumnName($columnIndex + 1) . ($rowIndex + 1);
                $styleIndex = $rowIndex === 0 ? 1 : 0;
                $columnWidths[$columnIndex] = max(
                    $columnWidths[$columnIndex] ?? 10,
                    min(48, mb_strlen(trim((string) $value), 'UTF-8') + 2)
                );

                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="' . $reference . '" s="' . $styleIndex . '"><v>' . $value . '</v></c>';
                    continue;
                }

                $text = (string) ($value ?? '');
                $cells .= '<c r="' . $reference . '" s="' . $styleIndex . '" t="inlineStr"><is><t xml:space="preserve">'
                    . self::xmlText($text)
                    . '</t></is></c>';
            }

            $sheetData .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }

        $columnsXml = '';
        if ($columnWidths !== []) {
            ksort($columnWidths);
            $columnsXml = '<cols>';
            foreach ($columnWidths as $columnIndex => $width) {
                $col = $columnIndex + 1;
                $columnsXml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
            }
            $columnsXml .= '</cols>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $columnsXml
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }

    private static function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function xmlAttribute(string $value): string
    {
        return self::xmlText($value);
    }
}
