<?php

class FormParser
{
    private $fieldMap = [];
    private $fieldMapTypes = [];

    public function __construct(string $questionsPath)
    {
        $this->parseQuestions($questionsPath);
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
                $this->fieldMapTypes[$currentKey] = $matches[1];
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
        $dataRaw = json_decode(file_get_contents("php://input"), true);
        if (!is_array($dataRaw)) {
            return [];
        }

        $row = [];
        foreach ($this->fieldMap as $key => $meta) {
            $value = isset($dataRaw[$key]) ? (string)$dataRaw[$key] : '';
            
            ob_start();              // start output buffering
            var_dump($this->fieldMapTypes);        // dump variable
            $dump = ob_get_clean();  // get buffered output
            file_put_contents("debug.log", $dump, FILE_APPEND);
             ob_start();              // start output buffering
            var_dump($key);        // dump variable
            $dump = ob_get_clean();  // get buffered output
            file_put_contents("debug.log", $dump, FILE_APPEND);
            if (str_starts_with(strtolower($this->fieldMapTypes[$key]), 'phone') && $value !== '') {
                $value = "'" . $value;
            }

            $row[] = $value;
        }

        return $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

try {
    $parser = new FormParser(__DIR__ . DIRECTORY_SEPARATOR . 'questions.txt');
    $headers = $parser->getHeaders();
    $dataRow = $parser->extractFormData();

    $csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'data.csv';

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

        $existingHeaders = fgetcsv($handle);
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
            fgetcsv($readHandle);
            while ($row = fgetcsv($readHandle)) {
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
