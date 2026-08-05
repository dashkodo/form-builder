<?php

class FormParser
{
    private $fieldMap = [];
    private $fieldMapTypes = [];
    private $fieldMapMeta = [];
    private $storageDir = '';

    public function __construct(string $questionsPath)
    {
        $this->storageDir = $this->resolveStorageDir();
        $this->parseQuestions($questionsPath);
    }

    private function resolveStorageDir(): string
    {
        $storageDir = getenv('FORMS_STORAGE_DIR');
        if (!is_string($storageDir) || trim($storageDir) === '') {
            return __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        }

        $storageDir = rtrim(trim($storageDir), "\\/");
        return $storageDir === '' ? (__DIR__ . DIRECTORY_SEPARATOR . 'storage') : $storageDir;
    }

    private function parseQuestions(string $questionsPath): void
    {
        if (!file_exists($questionsPath)) {
            throw new Exception("Questions file not found");
        }

        $lines = file($questionsPath, FILE_IGNORE_NEW_LINES);
        $questionIndex = 0;
        $currentKey = null;

        foreach ($lines as $raw) {
            $line = rtrim($raw);
            $trim = trim($line);

            // skip empty and section headers
            if ($trim === '' || preg_match('/^\[(.+)\]$/', $trim)) {
                continue;
            }

            $matches = [];
            // New question begins when we see QuestionType=
            if (preg_match('/^QuestionType\s*=\s*(.+)$/i', $trim, $matches)) {
                $questionIndex++;
                $currentKey = 'q' . $questionIndex;
                // initialize with empty label; we'll fill it from the next non-indented line
                $this->fieldMap[$currentKey] = '';

                $parts = preg_split('/\s+/', trim($matches[1]));
                $type = strtolower($parts[0] ?? 'text');

                $meta = [
                    'filename_from' => '',
                    'folder' => '',
                ];

                $metaSource = trim($matches[1]);
                if (preg_match('/filenameFrom="([^"]+)"/i', $metaSource, $metaMatch)) {
                    $meta['filename_from'] = trim($metaMatch[1]);
                } elseif (preg_match('/filenameFrom=([^\s]+)/i', $metaSource, $metaMatch)) {
                    $meta['filename_from'] = trim($metaMatch[1]);
                }

                if (preg_match('/folder="([^"]+)"/i', $metaSource, $metaMatch)) {
                    $meta['folder'] = trim($metaMatch[1]);
                } elseif (preg_match('/folder=([^\s]+)/i', $metaSource, $metaMatch)) {
                    $meta['folder'] = trim($metaMatch[1]);
                }

                foreach ($parts as $part) {
                    // keep required compatibility; metadata is parsed above from full source
                }

                $this->fieldMapTypes[$currentKey] = $type;
                $this->fieldMapMeta[$currentKey] = $meta;
                continue;
            }

            // If we are inside a question block, the first non-indented non-QuestionType line
            // is treated as the question label/text
            if ($currentKey !== null) {
                // option lines are indented (start with whitespace) — ignore for headers
                if (preg_match('/^\s+.+$/', $raw)) {
                    continue;
                }

                if ($this->fieldMap[$currentKey] === '') {
                    $this->fieldMap[$currentKey] = $trim;
                }
                // otherwise ignore explanation or other lines
            }
        }
    }

    public function getHeaders(): array
    {
        return array_values($this->fieldMap);
    }

    public function extractFormData(): array
    {
        $dataRaw = [];
        if (!empty($_POST)) {
            $dataRaw = $_POST;
        } else {
            $jsonPayload = file_get_contents("php://input");
            $decoded = json_decode($jsonPayload, true);
            if (is_array($decoded)) {
                $dataRaw = $decoded;
            }
        }

        if (!is_array($dataRaw)) {
            return [];
        }

        $row = [];
        foreach ($this->fieldMap as $key => $_meta) {
            $value = isset($dataRaw[$key]) ? (string)$dataRaw[$key] : '';

            if ($this->fieldMapTypes[$key] === 'photo') {
                $value = $this->saveUploadedFile($key, $dataRaw);
            }

            if (str_starts_with(strtolower($this->fieldMapTypes[$key]), 'phone') && $value !== '') {
                $value = "'" . $value;
            }

            $value = str_replace(["\r\n", "\r", "\n"], ',', $value);
            $row[] = $value;
        }

        return $row;
    }

    private function sanitizePathSegment(string $value): string
    {
        $safe = trim($value);
        // Keep Unicode letters/numbers (including Cyrillic), spaces, dot, underscore and dash.
        $safe = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '', $safe);
        // Collapse whitespace to underscores for stable filenames.
        $safe = preg_replace('/\s+/u', '_', (string)$safe);
        // Remove risky leading/trailing separators.
        $safe = trim((string)$safe, " ._-");
        return $safe === '' ? 'file' : $safe;
    }

    private function sanitizeFolder(string $folder): string
    {
        $folder = str_replace('\\', '/', trim($folder));
        $folder = preg_replace('/\.{2,}/', '', $folder);
        $parts = array_filter(explode('/', $folder), fn($p) => $p !== '' && $p !== '.');
        $safeParts = array_map(fn($p) => $this->sanitizePathSegment($p), $parts);

        if (empty($safeParts)) {
            return '';
        }

        return implode(DIRECTORY_SEPARATOR, $safeParts);
    }

    private function detectExtension(array $file): string
    {
        $originalName = $file['name'] ?? '';
        $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'];

        if (in_array($ext, $allowed, true)) {
            return $ext;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if ($tmpName !== '' && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                $byMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    'image/bmp' => 'bmp',
                    'image/heic' => 'heic',
                    'image/heif' => 'heif',
                ];

                if (isset($byMime[$mime])) {
                    return $byMime[$mime];
                }
            }
        }

        return 'bin';
    }

    private function saveUploadedFile(string $key, array $dataRaw): string
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return '';
        }

        $file = $_FILES[$key];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }

        $meta = $this->fieldMapMeta[$key] ?? ['filename_from' => '', 'folder' => ''];
        $fileNameSourceKey = $meta['filename_from'] ?? '';

        $rawBaseName = '';
        $resolvedSourceKey = $this->resolveFieldReferenceToKey($fileNameSourceKey);
        if ($resolvedSourceKey !== '' && isset($dataRaw[$resolvedSourceKey])) {
            $rawBaseName = (string)$dataRaw[$resolvedSourceKey];
        } elseif ($fileNameSourceKey !== '' && isset($dataRaw[$fileNameSourceKey])) {
            $rawBaseName = (string)$dataRaw[$fileNameSourceKey];
        }
        if ($rawBaseName === '') {
            $rawBaseName = $key;
        }

        $baseName = $this->sanitizePathSegment($rawBaseName);
        $targetDir = $this->storageDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new Exception('Неможливо створити директорію для завантажених фото');
        }

        $ext = $this->detectExtension($file);
        $targetName = $baseName . '.' . $ext;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;
        $counter = 1;

        while (file_exists($targetPath)) {
            $targetName = $baseName . '_' . $counter . '.' . $ext;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;
            $counter++;
        }

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            throw new Exception('Помилка збереження фото на сервері');
        }

        return $targetName;
    }

    private function normalizeFieldReference(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    private function resolveFieldReferenceToKey(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }

        if (isset($this->fieldMap[$reference])) {
            return $reference;
        }

        foreach ($this->fieldMap as $fieldKey => $fieldLabel) {
            if (trim($fieldLabel) === $reference) {
                return $fieldKey;
            }
        }

        $normalizedRef = $this->normalizeFieldReference($reference);
        foreach ($this->fieldMap as $fieldKey => $fieldLabel) {
            if ($this->normalizeFieldReference($fieldLabel) === $normalizedRef) {
                return $fieldKey;
            }
        }

        return '';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

try {
    $storageDir = getenv('FORMS_STORAGE_DIR');
    if (!is_string($storageDir) || trim($storageDir) === '') {
        $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    } else {
        $storageDir = rtrim(trim($storageDir), "\\/");
    }

    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new Exception('Неможливо створити директорію збереження даних');
    }

    $parser = new FormParser(__DIR__ . DIRECTORY_SEPARATOR . 'questions.txt');
    $headers = $parser->getHeaders();
    $dataRow = $parser->extractFormData();

    $csvPath = $storageDir . DIRECTORY_SEPARATOR . 'data.csv';

    if (!file_exists($csvPath)) {
        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            throw new Exception('Неможливо створити CSV-файл');
        }
        fputcsv($handle, $headers);
        fputcsv($handle, $dataRow);
        fclose($handle);
    } else {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new Exception('Неможливо відкрити CSV-файл');
        }

        $existingHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        fclose($handle);

        if ($existingHeaders !== $headers) {
            $handle = fopen($csvPath, 'w');
            if ($handle === false) {
                throw new Exception('Неможливо перезаписати CSV-файл');
            }
            fputcsv($handle, $headers);

            $readHandle = fopen($csvPath, 'r');
            if ($readHandle === false) {
                throw new Exception('Неможливо прочитати CSV-файл');
            }
            fgetcsv($readHandle, 0, ',', '"', '\\');
            while ($row = fgetcsv($readHandle, 0, ',', '"', '\\')) {
                fputcsv($handle, $row);
            }
            fclose($readHandle);
            fclose($handle);
        }

        $handle = fopen($csvPath, 'a');
        if ($handle === false) {
            throw new Exception('Неможливо додати до CSV-файлу');
        }
        fputcsv($handle, $dataRow);
        fclose($handle);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Помилка: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

header('Location: /saved.html');
exit;
