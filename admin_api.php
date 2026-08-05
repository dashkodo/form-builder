<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function resolveStorageDir(): string
{
    $storageDir = getenv('FORMS_STORAGE_DIR');
    if (!is_string($storageDir) || trim($storageDir) === '') {
        return __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    }

    $storageDir = rtrim(trim($storageDir), "\\/");
    return $storageDir === '' ? (__DIR__ . DIRECTORY_SEPARATOR . 'storage') : $storageDir;
}

function normalizeQuestionType(string $type): string
{
    $allowed = ['text', 'longtext', 'radio', 'checkbox', 'select', 'date', 'phone', 'photo'];
    $type = strtolower(trim($type));
    return in_array($type, $allowed, true) ? $type : 'text';
}

function parseQuestionTypeMeta(string $metaSource): array
{
    $meta = [
        'type' => 'text',
        'required' => false,
        'filenameFrom' => '',
        'folder' => '',
    ];

    $parts = preg_split('/\s+/', trim($metaSource));
    $meta['type'] = normalizeQuestionType($parts[0] ?? 'text');

    foreach ($parts as $part) {
        if (strtolower($part) === 'required') {
            $meta['required'] = true;
        }
    }

    if (preg_match('/filenameFrom="([^"]+)"/i', $metaSource, $m)) {
        $meta['filenameFrom'] = trim($m[1]);
    } elseif (preg_match('/filenameFrom=([^\s]+)/i', $metaSource, $m)) {
        $meta['filenameFrom'] = trim($m[1]);
    }

    if (preg_match('/folder="([^"]+)"/i', $metaSource, $m)) {
        $meta['folder'] = trim($m[1]);
    } elseif (preg_match('/folder=([^\s]+)/i', $metaSource, $m)) {
        $meta['folder'] = trim($m[1]);
    }

    return $meta;
}

function parseQuestionBlock(array $block): array
{
    $question = [
        'type' => 'text',
        'required' => false,
        'question' => '',
        'explanation' => '',
        'options' => [],
        'filenameFrom' => '',
        'folder' => '',
    ];

    $seenQuestion = false;

    foreach ($block as $line) {
        $trimmed = trim($line);

        if (preg_match('/^QuestionType=(.+)$/i', $trimmed, $matches)) {
            $meta = parseQuestionTypeMeta($matches[1]);
            $question['type'] = $meta['type'];
            $question['required'] = $meta['required'];
            $question['filenameFrom'] = $meta['filenameFrom'];
            $question['folder'] = $meta['folder'];
            continue;
        }

        if (preg_match('/^Required(?:=(true|1|yes))?$/i', $trimmed)) {
            $question['required'] = true;
            continue;
        }

        if (preg_match('/^\s+(.+)$/', $line, $matches)) {
            $question['options'][] = trim($matches[1]);
            continue;
        }

        if ($trimmed !== '') {
            if (!$seenQuestion) {
                $question['question'] = $trimmed;
                $seenQuestion = true;
            } elseif ($question['explanation'] === '') {
                $question['explanation'] = $trimmed;
            }
        }
    }

    return $question;
}

function parseQuestionsFile(string $path): array
{
    if (!file_exists($path)) {
        return [
            [
                'name' => 'Default',
                'questions' => [],
            ],
        ];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $sections = [];
    $currentSection = 'Default';
    $sections[$currentSection] = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $currentSection = $matches[1];
            if (!isset($sections[$currentSection])) {
                $sections[$currentSection] = [];
            }
            continue;
        }
        $sections[$currentSection][] = $line;
    }

    $result = [];
    foreach ($sections as $sectionName => $sectionLines) {
        $blocks = [];
        $currentBlock = [];

        foreach ($sectionLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '---') {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                }
                continue;
            }
            if ($trimmed === '') {
                continue;
            }
            $currentBlock[] = $line;
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        $questions = [];
        foreach ($blocks as $block) {
            $questions[] = parseQuestionBlock($block);
        }

        $result[] = [
            'name' => $sectionName,
            'questions' => $questions,
        ];
    }

    return $result;
}

function questionTypeLine(array $question): string
{
    $type = normalizeQuestionType((string)($question['type'] ?? 'text'));
    $parts = ['QuestionType=' . ucfirst($type)];

    if (!empty($question['required'])) {
        $parts[] = 'required';
    }

    if ($type === 'photo') {
        $filenameFrom = trim((string)($question['filenameFrom'] ?? ''));
        if ($filenameFrom !== '') {
            if (str_contains($filenameFrom, ' ')) {
                $parts[] = 'filenameFrom="' . str_replace('"', '', $filenameFrom) . '"';
            } else {
                $parts[] = 'filenameFrom=' . $filenameFrom;
            }
        }
    }

    return implode(' ', $parts);
}

function serializeQuestions(array $sections): string
{
    $lines = [];

    foreach ($sections as $section) {
        $name = trim((string)($section['name'] ?? 'Default'));
        if ($name === '') {
            $name = 'Default';
        }

        $lines[] = '[' . $name . ']';

        $questions = $section['questions'] ?? [];
        foreach ($questions as $question) {
            $qText = trim((string)($question['question'] ?? ''));
            if ($qText === '') {
                continue;
            }

            $lines[] = questionTypeLine($question);
            $lines[] = $qText;

            $explanation = trim((string)($question['explanation'] ?? ''));
            if ($explanation !== '') {
                $lines[] = $explanation;
            }

            $type = normalizeQuestionType((string)($question['type'] ?? 'text'));
            $options = $question['options'] ?? [];
            if (in_array($type, ['radio', 'checkbox', 'select'], true)) {
                foreach ($options as $opt) {
                    $opt = trim((string)$opt);
                    if ($opt !== '') {
                        $lines[] = "\t" . $opt;
                    }
                }
            }

            $lines[] = '---';
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function getSubmissions(string $csvPath, string $storageDir): array
{
    if (!file_exists($csvPath)) {
        return [
            'headers' => [],
            'rows' => [],
        ];
    }

    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new Exception('Неможливо відкрити data.csv');
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if (!is_array($headers)) {
        fclose($handle);
        return ['headers' => [], 'rows' => []];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $assoc = [];
        foreach ($headers as $idx => $header) {
            $value = isset($row[$idx]) ? (string)$row[$idx] : '';
            $assoc[$header] = $value;

            if (preg_match('/\.(jpg|jpeg|png|webp|gif|bmp|heic|heif)$/i', $value)) {
                $candidate = $storageDir . DIRECTORY_SEPARATOR . $value;
                if (file_exists($candidate)) {
                    $assoc[$header . '__photoUrl'] = 'storage/' . rawurlencode($value);
                }
            }
        }
        $rows[] = $assoc;
    }

    fclose($handle);

    return [
        'headers' => $headers,
        'rows' => $rows,
    ];
}

function readCsvData(string $csvPath): array
{
    if (!file_exists($csvPath)) {
        return [
            'headers' => [],
            'rows' => [],
        ];
    }

    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new Exception('Неможливо відкрити data.csv');
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if (!is_array($headers)) {
        fclose($handle);
        return ['headers' => [], 'rows' => []];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $assoc = [];
        foreach ($headers as $idx => $header) {
            $assoc[$header] = isset($row[$idx]) ? (string)$row[$idx] : '';
        }
        $rows[] = $assoc;
    }

    fclose($handle);
    return [
        'headers' => $headers,
        'rows' => $rows,
    ];
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsxColName(int $index): string
{
    $index += 1;
    $name = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - 1, 26);
    }
    return $name;
}

function buildSheetXml(array $headers, array $rows, array $sharedMap, array $sharedStrings): string
{
    $sheetRows = [];

    $headerCells = [];
    foreach ($headers as $colIndex => $header) {
        $cellRef = xlsxColName($colIndex) . '1';
        $sharedIndex = $sharedMap[$header];
        $headerCells[] = '<c r="' . $cellRef . '" t="s"><v>' . $sharedIndex . '</v></c>';
    }
    $sheetRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 2;
        $cells = [];
        foreach ($headers as $colIndex => $header) {
            $cellRef = xlsxColName($colIndex) . $excelRow;
            $value = (string)($row[$header] ?? '');
            $sharedIndex = $sharedMap[$value] ?? 0;
            $cells[] = '<c r="' . $cellRef . '" t="s"><v>' . $sharedIndex . '</v></c>';
        }
        $sheetRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '</worksheet>';
}

function buildSharedStringsXml(array $sharedStrings): string
{
    $items = [];
    foreach ($sharedStrings as $str) {
        $items[] = '<si><t>' . xmlEscape($str) . '</t></si>';
    }

    $count = count($sharedStrings);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">'
        . implode('', $items)
        . '</sst>';
}

function buildXlsxBinary(array $headers, array $rows): string
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive недоступний у PHP-контейнері');
    }

    $values = $headers;
    foreach ($rows as $row) {
        foreach ($headers as $header) {
            $values[] = (string)($row[$header] ?? '');
        }
    }

    $sharedMap = [];
    $sharedStrings = [];
    foreach ($values as $value) {
        if (!array_key_exists($value, $sharedMap)) {
            $sharedMap[$value] = count($sharedStrings);
            $sharedStrings[] = $value;
        }
    }

    $sheetXml = buildSheetXml($headers, $rows, $sharedMap, $sharedStrings);
    $sharedXml = buildSharedStringsXml($sharedStrings);

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'fb_xlsx_');
    if ($tmpXlsx === false) {
        throw new Exception('Не вдалося створити тимчасовий XLSX-файл');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpXlsx, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpXlsx);
        throw new Exception('Не вдалося створити XLSX-архів');
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>'
    );

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>'
    );

    $zip->addFromString('docProps/core.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Form Export</dc:title>'
        . '<dc:creator>Forms Builder</dc:creator>'
        . '<cp:lastModifiedBy>Forms Builder</cp:lastModifiedBy>'
        . '</cp:coreProperties>'
    );

    $zip->addFromString('docProps/app.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Forms Builder</Application>'
        . '</Properties>'
    );

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>'
    );

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>'
    );

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedXml);

    $zip->close();
    $binary = file_get_contents($tmpXlsx);
    @unlink($tmpXlsx);

    if ($binary === false) {
        throw new Exception('Не вдалося прочитати згенерований XLSX');
    }

    return $binary;
}

function fileLooksLikeAttachment(string $value): bool
{
    return preg_match('/\.(jpg|jpeg|png|webp|gif|bmp|heic|heif|pdf|doc|docx|xls|xlsx|txt)$/i', $value) === 1;
}

function buildExportZip(array $selectedHeaders, string $csvPath, string $storageDir): string
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive недоступний у PHP-контейнері');
    }

    $data = readCsvData($csvPath);
    $allHeaders = $data['headers'];
    $rows = $data['rows'];

    if (empty($allHeaders)) {
        throw new Exception('Немає даних для експорту');
    }

    if (empty($selectedHeaders)) {
        $selectedHeaders = $allHeaders;
    }

    $selectedHeaders = array_values(array_filter($selectedHeaders, fn($h) => in_array($h, $allHeaders, true)));
    if (empty($selectedHeaders)) {
        throw new Exception('Не обрано жодної валідної колонки');
    }

    $filteredRows = [];
    foreach ($rows as $row) {
        $filtered = [];
        foreach ($selectedHeaders as $header) {
            $filtered[$header] = (string)($row[$header] ?? '');
        }
        $filteredRows[] = $filtered;
    }

    $xlsxBinary = buildXlsxBinary($selectedHeaders, $filteredRows);

    $tmpZip = tempnam(sys_get_temp_dir(), 'fb_export_');
    if ($tmpZip === false) {
        throw new Exception('Не вдалося створити тимчасовий ZIP');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpZip);
        throw new Exception('Не вдалося створити ZIP-архів');
    }

    $zip->addFromString('export.xlsx', $xlsxBinary);

    $usedNames = [];
    foreach ($filteredRows as $row) {
        foreach ($selectedHeaders as $header) {
            $value = trim((string)($row[$header] ?? ''));
            if ($value === '' || !fileLooksLikeAttachment($value)) {
                continue;
            }

            $candidate = $storageDir . DIRECTORY_SEPARATOR . $value;
            if (!file_exists($candidate) || !is_file($candidate)) {
                continue;
            }

            $base = pathinfo($value, PATHINFO_FILENAME);
            $ext = pathinfo($value, PATHINFO_EXTENSION);
            $safeBase = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $base);
            $safeBase = trim((string)$safeBase, '_');
            if ($safeBase === '') {
                $safeBase = 'file';
            }

            $finalName = $safeBase . ($ext !== '' ? '.' . $ext : '');
            $counter = 1;
            while (isset($usedNames[$finalName])) {
                $finalName = $safeBase . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
                $counter++;
            }
            $usedNames[$finalName] = true;

            $zip->addFile($candidate, 'files/' . $finalName);
        }
    }

    $zip->close();
    return $tmpZip;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $questionsPath = __DIR__ . DIRECTORY_SEPARATOR . 'questions.txt';
    $storageDir = resolveStorageDir();
    $csvPath = $storageDir . DIRECTORY_SEPARATOR . 'data.csv';

    if ($action === 'get_questions') {
        echo json_encode([
            'ok' => true,
            'sections' => parseQuestionsFile($questionsPath),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_questions') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || !isset($input['sections']) || !is_array($input['sections'])) {
            throw new Exception('Некоректні дані для збереження');
        }

        $serialized = serializeQuestions($input['sections']);
        $written = file_put_contents($questionsPath, $serialized);
        if ($written === false) {
            throw new Exception('Не вдалося зберегти questions.txt');
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_submissions') {
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        echo json_encode([
            'ok' => true,
            'storageDir' => $storageDir,
            'submissions' => getSubmissions($csvPath, $storageDir),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'export_zip') {
        $input = json_decode(file_get_contents('php://input'), true);
        $selectedHeaders = [];
        if (is_array($input) && isset($input['columns']) && is_array($input['columns'])) {
            $selectedHeaders = array_map(fn($v) => (string)$v, $input['columns']);
        }

        $zipPath = buildExportZip($selectedHeaders, $csvPath, $storageDir);
        $downloadName = 'form_export_' . date('Ymd_His') . '.zip';

        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    throw new Exception('Невідома дія');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
