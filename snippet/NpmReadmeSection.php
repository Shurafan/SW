<?php
/**
 * NpmReadmeSection - вывод секции README по # ## из NPM или GitHub
 * 
 * Вызов: [[!NpmReadmeSection? &package=`@studio-west/component-sw` &anchor=`установка`]]
 * Или с GitHub: [[!NpmReadmeSection? &repo=`studio-west/component-sw` &anchor=`установка` &branch=`main`]]
 * 
 * @property string $package   npm-пакет (обязательно, если нет &repo)
 * @property string $repo      GitHub репозиторий owner/repo (опционально)
 * @property string $branch    ветка GitHub (по умолчанию: main)
 * @property string $anchor    якорь раздела без # (обязательно)
 * @property int    $cache_ttl время кэширования в секундах (по умолчанию: 3600)
 * @property bool   $debug     вывод доступных якорей при ошибке (по умолчанию: false)
 */

$package = $modx->getOption('package', $scriptProperties, '@studio-west/component-sw');
$repo    = $modx->getOption('repo', $scriptProperties, '');
$branch  = $modx->getOption('branch', $scriptProperties, 'main');
$anchor  = $modx->getOption('anchor', $scriptProperties, '');
$cache_ttl = (int)$modx->getOption('cache_ttl', $scriptProperties, 3600);
$debug   = (bool)$modx->getOption('debug', $scriptProperties, false);

if (empty($anchor)) {
    return '<!-- NpmReadmeSection: параметр &anchor обязателен -->';
}

// 1. Определяем источник
$isGitHub = !empty($repo);
$sourceUrl = $isGitHub 
    ? "https://raw.githubusercontent.com/{$repo}/{$branch}/README.md"
    : "https://registry.npmjs.org/" . urlencode($package);

$cacheKey = "npm_readme_" . md5($sourceUrl . $anchor);
$cached = $modx->cacheManager->get($cacheKey);
if ($cached !== null) {
    return $cached;
}

// 2. Загрузка контента
$markdown = false;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $sourceUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MODX3/3.0)',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $markdown = $isGitHub ? $response : (json_decode($response, true)['readme'] ?? false);
    }
} elseif (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0 (compatible; MODX3/3.0)\r\n"
        ]
    ]);
    $response = @file_get_contents($sourceUrl, false, $ctx);
    if ($response !== false) {
        $markdown = $isGitHub ? $response : (json_decode($response, true)['readme'] ?? false);
    }
}

if ($markdown === false || trim($markdown) === '') {
    $modx->log(modX::LOG_LEVEL_ERROR, "[NpmReadmeSection] Не удалось загрузить README из {$sourceUrl}");
    return '<!-- Error: README not loaded -->';
}

// 3. Парсинг секций по заголовкам #, ##
$markdown = preg_replace('/\r\n?/', "\n", $markdown); // Нормализация переносов
$sections = [];
$currentHeading = null;
$currentContent = [];
$lines = explode("\n", $markdown);

foreach ($lines as $line) {
    if (preg_match('/^#{1,2}\s+(.+?)\s*$/', $line, $matches)) {
        if ($currentHeading !== null) {
            $sections[$currentHeading] = trim(implode("\n", $currentContent));
        }
        $currentHeading = trim($matches[1]);
        $currentContent = [$line];
    } else {
        $currentContent[] = $line;
    }
}
if ($currentHeading !== null) {
    $sections[$currentHeading] = trim(implode("\n", $currentContent));
}

// 4. Поиск нужной секции
$targetAnchor = normalizeAnchor($anchor);
$content = '';

foreach ($sections as $heading => $sectionContent) {
    $headingAnchor = normalizeAnchor($heading);
    
    if ($headingAnchor == $targetAnchor) {
        $content = $sectionContent;
        break;
    }
    //if (empty($content) && (strpos($headingAnchor, $targetAnchor) !== false || strpos($targetAnchor, $headingAnchor) !== false)) {
    //     $modx->log(modX::LOG_LEVEL_ERROR, "{$targetAnchor} Не удалось сравнить {$headingAnchor}");
    //    $content = $sectionContent;
    //    break;
    //}
}

// 5. Обработка отсутствия секции
if (empty($content)) {
    $available = array_map('normalizeAnchor', array_keys($sections));
    $logMsg = "Раздел '{$anchor}' (нормализовано: '{$targetAnchor}') не найден. Доступные якоря: " . implode(', ', $available);
    $modx->log(modX::LOG_LEVEL_WARN, "[NpmReadmeSection] {$logMsg}");
    return $debug ? "<!-- DEBUG: {$logMsg} -->" : '<!-- NpmReadmeSection: Раздел не найден -->';
}

// 6. Конвертация Markdown в HTML
$html = simpleMarkdownToHtml($content);

if ($cache_ttl > 0) {
    $modx->cacheManager->set($cacheKey, $html, $cache_ttl);
}

return $html;

// ==========================================
// Helper Functions
// ==========================================

/**
 * Разбивает строку markdown-таблицы на ячейки.
 * Экранированный \| внутри ячейки не считается разделителем.
 */
function splitTableRow($row) {
    $row = trim($row);
    if ($row !== '' && $row[0] === '|') {
        $row = substr($row, 1);
    }
    if ($row !== '' && substr($row, -1) === '|') {
        $row = substr($row, 0, -1);
    }

    $cells = [];
    $current = '';
    $len = strlen($row);

    for ($i = 0; $i < $len; $i++) {
        if ($row[$i] === '\\' && $i + 1 < $len && $row[$i + 1] === '|') {
            $current .= '|';
            $i++;
            continue;
        }
        if ($row[$i] === '|') {
            $cells[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $row[$i];
    }
    $cells[] = trim($current);

    return $cells;
}

function normalizeAnchor($text) {
    $text = strip_tags($text);
    $text = preg_replace('/[`*\[\](){}<>\/|~_]/', '', $text);
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function simpleMarkdownToHtml($markdown) {
    if (trim($markdown) === '') return '';
    
    $lines = explode("\n", $markdown);
    $result = [];
    $inCodeBlock = false;
    $codeContent = [];
    $codeLang = 'text';
    $inList = false;
    $inTable = false;
    $tableData = [];
    $paraBuffer = [];

    $flushParagraph = function() use (&$paraBuffer, &$result) {
        if (!empty($paraBuffer)) {
            $text = trim(implode("\n", $paraBuffer));
            if ($text !== '') {
                $result[] = processInlineMarkdown('<p>' . $text . '</p>');
            }
        }
        $paraBuffer = [];
    };

    $flushTable = function() use (&$tableData, &$result) {
        if (empty($tableData) || count($tableData) < 2) {
            $tableData = [];
            return;
        }
        
        $alignments = [];
        $sepCells = splitTableRow($tableData[1]);
        foreach ($sepCells as $cell) {
            $cell = trim($cell);
            if (strlen($cell) > 1 && strpos($cell, ':') === 0 && strrpos($cell, ':') === strlen($cell) - 1) {
                $alignments[] = 'center';
            } elseif (strrpos($cell, ':') === strlen($cell) - 1) {
                $alignments[] = 'right';
            } else {
                $alignments[] = 'left';
            }
        }
        
        $html = '<table>';
        foreach ($tableData as $rowIdx => $row) {
            if ($rowIdx === 1) continue; // skip separator row
            $cells = splitTableRow($row);
            $tag = ($rowIdx === 0) ? 'th' : 'td';
            $html .= ($rowIdx === 0) ? '<thead><tr>' : '<tr>';
            foreach ($cells as $colIdx => $cell) {
                $align = $alignments[$colIdx] ?? 'left';
                $class = ($align !== 'left') ? ' class="align-' . $align . '"' : '';
                $content = processInlineMarkdown($cell);
                $html .= "<{$tag}{$class}>{$content}</{$tag}>";
            }
            $html .= ($rowIdx === 0) ? '</tr></thead><tbody>' : '</tr>';
        }
        $html .= '</tbody></table>';
        $result[] = $html;
        $tableData = [];
    };

    foreach ($lines as $line) {
        // Code blocks
        if (preg_match('/^```(\w*)\s*$/', $line, $m)) {
            if ($inCodeBlock) {
                $html = '<pre><code class="language-' . htmlspecialchars($codeLang) . '">' . htmlspecialchars(implode("\n", $codeContent)) . '</code></pre>';
                $result[] = $html;
                $codeContent = [];
                $inCodeBlock = false;
            } else {
                $flushParagraph();
                $flushTable();
                if ($inList) { $result[] = '</ul>'; $inList = false; }
                $inCodeBlock = true;
                $codeLang = $m[1] ?: 'text';
            }
            continue;
        }
        if ($inCodeBlock) {
            $codeContent[] = $line;
            continue;
        }

        // Horizontal rule: ---, ***, ___ (3+ chars, optional spaces)
        if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
            $flushParagraph();
            $flushTable();
            if ($inList) { $result[] = '</ul>'; $inList = false; }
            $result[] = '<hr>';
            continue;
        }

        // Headers (h1-h6)
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $hm)) {
            $flushParagraph();
            $flushTable();
            if ($inList) { $result[] = '</ul>'; $inList = false; }
            $level = strlen($hm[1]);
            $content = processInlineMarkdown(trim($hm[2]));
            $result[] = "<h{$level}>{$content}</h{$level}>";
            continue;
        }

        // Tables
        if (preg_match('/^\|.+?\|\s*$/', $line) || preg_match('/^\|\s*[-:|]+\s*\|\s*$/', $line)) {
            $flushParagraph();
            if ($inList) { $result[] = '</ul>'; $inList = false; }
            if (!$inTable) {
                $inTable = true;
                $tableData = [];
            }
            $tableData[] = $line;
            continue;
        } elseif ($inTable) {
            $flushTable();
            $inTable = false;
        }

        // List items
        if (preg_match('/^[\-\*]\s+(.+)$/', $line, $lm)) {
            $flushParagraph();
            $flushTable();
            if (!$inList) {
                $result[] = '<ul>';
                $inList = true;
            }
            $result[] = '<li>' . processInlineMarkdown(trim($lm[1])) . '</li>';
        } else {
            if ($inList) {
                $result[] = '</ul>';
                $inList = false;
            }
            $paraBuffer[] = $line;
        }
    }

    // Close unclosed blocks
    if ($inCodeBlock) {
        $result[] = '<pre><code class="language-' . htmlspecialchars($codeLang) . '">' . htmlspecialchars(implode("\n", $codeContent)) . '</code></pre>';
    }
    if ($inTable) $flushTable();
    $flushParagraph();
    if ($inList) $result[] = '</ul>';

    // Remove trailing <hr> if present
    $output = trim(implode("\n", $result));
    $output = preg_replace('/(<hr>\s*)+$/', '', $output);

    return $output;
}

function processInlineMarkdown($text) {
    $text = preg_replace_callback('/`([^`]+)`/', fn($m) => '<code>' . htmlspecialchars($m[1]) . '</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    return $text;
}