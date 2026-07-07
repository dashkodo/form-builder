<?php

class FormBuilder
{
    private $sections = [];

    public function __construct(string $filePath)
    {
        $this->parseQuestions($filePath);
    }

    private function parseQuestions(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("Questions file not found: $filePath");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
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

        $questionIndex = 0;
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

            foreach ($blocks as $block) {
                $question = $this->parseQuestionBlock($block);
                $questionIndex++;
                $question['id'] = 'q' . $questionIndex;
                $this->sections[$sectionName][] = $question;
            }
        }
    }

    private function parseQuestionBlock(array $block): array
    {
        $question = [
            'type' => 'text',
            'question' => '',
            'explanation' => '',
            'options' => [],
            'id' => '',
            'required' => false,
        ];

        $seenQuestion = false;

        foreach ($block as $line) {
            $trimmed = trim($line);

            if (preg_match('/^QuestionType=(.+)$/', $trimmed, $matches)) {
                $parts = preg_split('/\s+/', trim($matches[1]));
                $question['type'] = strtolower($parts[0]);
                if (in_array('required', array_map('strtolower', $parts), true)) {
                    $question['required'] = true;
                }
                continue;
            }

            if (preg_match('/^Required(?:=(true|1|yes))?$/i', $trimmed, $matches)) {
                $question['required'] = true;
                continue;
            }

            if (preg_match('/^\s+(.+)$/', $line, $matches)) {
                $option = trim($matches[1]);
                if ($question['type'] !== 'text' && $question['type'] !== 'date' && $question['type'] !== 'phone') {
                    $question['options'][] = $option;
                }
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

    public function generateHtml(): string
    {
        $html = '';
        $sectionCount = count($this->sections);
        $sectionIndex = 0;

        foreach ($this->sections as $sectionName => $questions) {
            $isLast = ($sectionIndex === $sectionCount - 1);
            $html .= sprintf(
                '<form data-section="%d" style="display: %s;"><div class="form-section">',
                $sectionIndex,
                $sectionIndex === 0 ? 'block' : 'none'
            );

            $html .= sprintf('<div class="section-title">%s</div>', htmlspecialchars($sectionName));

            foreach ($questions as $question) {
                $html .= $this->generateQuestionHtml($question);
            }

            $html .= '<div class="section-buttons">';
            if ($sectionIndex > 0) {
                $html .= '<button type="button" class="btn-prev btn" onclick="prevSection()">Назад</button>';
            }
            if ($isLast) {
                $html .= '<button type="button" class="btn-submit btn" onclick="submitForm()">Зберегти</button>';
            } else {
                $html .= '<button type="button" class="btn-next btn" onclick="nextSection()">Далі</button>';
            }
            $html .= '</div>';

            $html .= '</div></form>';
            $sectionIndex++;
        }

        return $html;
    }

    private function generateQuestionHtml(array $question): string
    {
        $html = '<div class="question">';
        $html .= sprintf('<label class="question-label">%s</label>', htmlspecialchars($question['question']).($question['required'] ? ' <span class="required">*</span>' : ''));
        if ($question['explanation'] !== '') {
            $html .= sprintf('<div class="question-explanation">%s</div>', htmlspecialchars($question['explanation']));
        }
        $html .= '<br/>';
        $requiredAttr = $question['required'] ? ' required' : '';

        switch ($question['type']) {
            case 'text':
                $html .= sprintf(
                    '<input type="text" id="%s" name="%s" class="text-input"%s/>',
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id']),
                    $requiredAttr
                );
                break;
            case 'longtext':
                $html .= sprintf(
                    '<textarea id="%s" name="%s" class="text-input"%s></textarea>',
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id']),
                    $requiredAttr
                );
                break;

            case 'date':
                $html .= sprintf(
                    '<input type="date" id="%s" name="%s" class="date-input"%s/>',
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id']),
                    $requiredAttr
                );
                break;

            case 'phone':
                $html .= sprintf(
                    '<input type="text" id="%s" name="%s" class="phone-input" placeholder="+38 095 123 45 67" %s/>',
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id']),
                    $requiredAttr
                );
                break;

            case 'radio':
                $html .= $this->generateRadioGroup($question, $requiredAttr);
                break;

            case 'checkbox':
                $html .= $this->generateCheckboxGroup($question, $requiredAttr);
                break;

            case 'select':
                $html .= $this->generateSelectGroup($question, $requiredAttr);
                break;

            default:
                $html .= sprintf('<input type="text" id="%s" name="%s"%s />', htmlspecialchars($question['id']), htmlspecialchars($question['id']), $requiredAttr);
        }

        $html .= '</div><br/>';
        return $html;
    }

    private function generateRadioGroup(array $question, string $requiredAttr): string
    {
        $html = '';
        $first = true;
        foreach ($question['options'] as $option) {
            $hasFree = substr($option, -6) === '[free]';
            $optionClean = $hasFree ? substr($option, 0, -6) : $option;
            $optionId = htmlspecialchars($question['id'] . '_' . preg_replace('/[^a-z0-9]/i', '_', $optionClean));
            $optionValue = htmlspecialchars($optionClean);
            $attr = $first ? $requiredAttr : '';

            $html .= sprintf(
                '<input type="radio" id="%s" name="%s" value="%s" class="radio-input"%s/> %s',
                $optionId,
                htmlspecialchars($question['id']),
                $optionValue,
                $attr,
                htmlspecialchars($optionClean)
            );

            if ($hasFree) {
                $html .= sprintf(
                    ' <input type="text" placeholder="введіть текст..." id="%s" name="%s"  class="free-input" data-radio="%s" style="display:none; max-width: 70%%;"/>',
                    htmlspecialchars($optionId . '_free'),
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id'])
                );
            }

            $html .= '<br/>';
            $first = false;
        }

        return $html;
    }

    private function generateCheckboxGroup(array $question, string $requiredAttr): string
    {
        $html = '';
        $first = true;
        foreach ($question['options'] as $idx => $option) {
            $hasFree = substr($option, -6) === '[free]';
            $optionClean = $hasFree ? substr($option, 0, -6) : $option;
            $optionId = htmlspecialchars($question['id'] . '_' . $idx);
            $optionValue = htmlspecialchars($optionClean);
            $attr = $first ? $requiredAttr : '';

            $html .= sprintf(
                '<input type="checkbox" id="%s" name="%s" value="%s" class="checkbox-input"%s/> %s',
                $optionId,
                htmlspecialchars($question['id']),
                $optionValue,
                $attr,
                htmlspecialchars($optionClean)
            );

            if ($hasFree) {
                $html .= sprintf(
                    ' <input type="text" placeholder="введіть текст..." id="%s" name="%s"  class="free-input" data-checkbox="%s" style="display:none; max-width: 70%%;"/>',
                    htmlspecialchars($optionId . '_free'),
                    htmlspecialchars($question['id']),
                    htmlspecialchars($question['id'])
                );
            }

            $html .= '<br/>';
        }

        return $html;
    }

    private function generateSelectGroup(array $question, string $requiredAttr): string
    {
        $html = sprintf('<select id="%s" name="%s"%s>', htmlspecialchars($question['id']), htmlspecialchars($question['id']), $requiredAttr);
        $html .= '<option value="">Choose</option>';

        foreach ($question['options'] as $option) {
            $optionClean = preg_replace('/\[free\]$/', '', $option);
            $html .= sprintf('<option value="%s">%s</option>', htmlspecialchars($optionClean), htmlspecialchars($optionClean));
        }

        $html .= '</select>';
        return $html;
    }
}

$builder = new FormBuilder(__DIR__ . '/questions.txt');
echo $builder->generateHtml();
