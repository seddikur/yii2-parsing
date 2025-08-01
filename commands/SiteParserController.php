<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Html;
use DOMDocument;
use DOMXPath;

/**
 * Консольная команда для парсинга сайтов
 */
class SiteParserController extends Controller
{
    /**
     * Парсит указанный URL и сохраняет данные
     * @param string $url URL для парсинга
     * @param string $type Тип парсинга (links, images, text, all)
     * @return int
     */
    public function actionParse($url, $type = 'all')
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->stderr("Неверный URL: {$url}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Начинаем парсинг сайта: {$url}\n", Console::FG_GREEN);

        try {
            // Получаем содержимое страницы
            $content = $this->fetchUrl($url);
            if (!$content) {
                $this->stderr("Не удалось получить содержимое страницы\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            // Парсим HTML
            $dom = new DOMDocument();
            @$dom->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);

            $results = [];

            switch ($type) {
                case 'links':
                    $results = $this->parseLinks($xpath, $url);
                    break;
                case 'images':
                    $results = $this->parseImages($xpath, $url);
                    break;
                case 'text':
                    $results = $this->parseText($xpath);
                    break;
                case 'all':
                default:
                    $results = [
                        'links' => $this->parseLinks($xpath, $url),
                        'images' => $this->parseImages($xpath, $url),
                        'text' => $this->parseText($xpath),
                        'meta' => $this->parseMeta($xpath),
                        'title' => $this->parseTitle($xpath)
                    ];
                    break;
            }

            // Выводим результаты
            $this->displayResults($results, $type);

            // Сохраняем в файл
            $filename = $this->saveResults($results, $url, $type);
            $this->stdout("Результаты сохранены в файл: {$filename}\n", Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("Ошибка парсинга: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Получает содержимое URL
     * @param string $url
     * @return string|false
     */
    private function fetchUrl($url)
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                ],
                'timeout' => 30,
                'follow_location' => true,
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    /**
     * Парсит ссылки
     * @param DOMXPath $xpath
     * @param string $baseUrl
     * @return array
     */
    private function parseLinks($xpath, $baseUrl)
    {
        $links = [];
        $nodes = $xpath->query('//a[@href]');

        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            $text = trim($node->textContent);
            
            // Преобразуем относительные ссылки в абсолютные
            if (strpos($href, 'http') !== 0) {
                $href = $this->resolveUrl($baseUrl, $href);
            }

            if ($href && $text) {
                $links[] = [
                    'url' => $href,
                    'text' => $text,
                    'title' => $node->getAttribute('title') ?: ''
                ];
            }
        }

        return $links;
    }

    /**
     * Парсит изображения
     * @param DOMXPath $xpath
     * @param string $baseUrl
     * @return array
     */
    private function parseImages($xpath, $baseUrl)
    {
        $images = [];
        $nodes = $xpath->query('//img[@src]');

        foreach ($nodes as $node) {
            $src = $node->getAttribute('src');
            $alt = $node->getAttribute('alt');
            
            // Преобразуем относительные ссылки в абсолютные
            if (strpos($src, 'http') !== 0) {
                $src = $this->resolveUrl($baseUrl, $src);
            }

            if ($src) {
                $images[] = [
                    'url' => $src,
                    'alt' => $alt,
                    'title' => $node->getAttribute('title') ?: '',
                    'width' => $node->getAttribute('width') ?: '',
                    'height' => $node->getAttribute('height') ?: ''
                ];
            }
        }

        return $images;
    }

    /**
     * Парсит текст
     * @param DOMXPath $xpath
     * @return array
     */
    private function parseText($xpath)
    {
        $texts = [];
        
        // Удаляем скрипты и стили
        $xpath->query('//script')->item(0) && $xpath->query('//script')->item(0)->parentNode->removeChild($xpath->query('//script')->item(0));
        $xpath->query('//style')->item(0) && $xpath->query('//style')->item(0)->parentNode->removeChild($xpath->query('//style')->item(0));

        // Получаем заголовки
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        foreach ($headings as $heading) {
            $texts['headings'][] = [
                'tag' => $heading->tagName,
                'text' => trim($heading->textContent)
            ];
        }

        // Получаем параграфы
        $paragraphs = $xpath->query('//p');
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);
            if ($text) {
                $texts['paragraphs'][] = $text;
            }
        }

        return $texts;
    }

    /**
     * Парсит мета-теги
     * @param DOMXPath $xpath
     * @return array
     */
    private function parseMeta($xpath)
    {
        $meta = [];
        $nodes = $xpath->query('//meta[@name or @property]');

        foreach ($nodes as $node) {
            $name = $node->getAttribute('name') ?: $node->getAttribute('property');
            $content = $node->getAttribute('content');
            
            if ($name && $content) {
                $meta[$name] = $content;
            }
        }

        return $meta;
    }

    /**
     * Парсит заголовок страницы
     * @param DOMXPath $xpath
     * @return string
     */
    private function parseTitle($xpath)
    {
        $titleNode = $xpath->query('//title')->item(0);
        return $titleNode ? trim($titleNode->textContent) : '';
    }

    /**
     * Преобразует относительный URL в абсолютный
     * @param string $baseUrl
     * @param string $relativeUrl
     * @return string
     */
    private function resolveUrl($baseUrl, $relativeUrl)
    {
        if (strpos($relativeUrl, 'http') === 0) {
            return $relativeUrl;
        }

        $base = parse_url($baseUrl);
        
        if (strpos($relativeUrl, '//') === 0) {
            return $base['scheme'] . ':' . $relativeUrl;
        }

        if (strpos($relativeUrl, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . $relativeUrl;
        }

        $path = isset($base['path']) ? $base['path'] : '/';
        $path = dirname($path) . '/' . $relativeUrl;
        
        return $base['scheme'] . '://' . $base['host'] . $path;
    }

    /**
     * Выводит результаты парсинга
     * @param array $results
     * @param string $type
     */
    private function displayResults($results, $type)
    {
        $this->stdout("\nРезультаты парсинга:\n", Console::FG_YELLOW);
        $this->stdout("=" . str_repeat("=", 50) . "\n", Console::FG_YELLOW);

        if ($type === 'all' || $type === 'title') {
            if (isset($results['title']) && $results['title']) {
                $this->stdout("Заголовок: {$results['title']}\n", Console::FG_GREEN);
            }
        }

        if ($type === 'all' || $type === 'meta') {
            if (isset($results['meta']) && !empty($results['meta'])) {
                $this->stdout("\nМета-теги:\n", Console::FG_CYAN);
                foreach ($results['meta'] as $name => $content) {
                    $this->stdout("  {$name}: {$content}\n", Console::FG_BLUE);
                }
            }
        }

        if ($type === 'all' || $type === 'links') {
            $links = is_array($results) && isset($results['links']) ? $results['links'] : $results;
            if (!empty($links)) {
                $this->stdout("\nСсылки (" . count($links) . "):\n", Console::FG_CYAN);
                foreach (array_slice($links, 0, 10) as $link) {
                    $this->stdout("  {$link['text']} -> {$link['url']}\n", Console::FG_BLUE);
                }
                if (count($links) > 10) {
                    $this->stdout("  ... и еще " . (count($links) - 10) . " ссылок\n", Console::FG_YELLOW);
                }
            }
        }

        if ($type === 'all' || $type === 'images') {
            $images = is_array($results) && isset($results['images']) ? $results['images'] : $results;
            if (!empty($images)) {
                $this->stdout("\nИзображения (" . count($images) . "):\n", Console::FG_CYAN);
                foreach (array_slice($images, 0, 5) as $image) {
                    $this->stdout("  {$image['url']}\n", Console::FG_BLUE);
                }
                if (count($images) > 5) {
                    $this->stdout("  ... и еще " . (count($images) - 5) . " изображений\n", Console::FG_YELLOW);
                }
            }
        }

        if ($type === 'all' || $type === 'text') {
            $texts = is_array($results) && isset($results['text']) ? $results['text'] : $results;
            if (!empty($texts)) {
                if (isset($texts['headings'])) {
                    $this->stdout("\nЗаголовки:\n", Console::FG_CYAN);
                    foreach (array_slice($texts['headings'], 0, 5) as $heading) {
                        $this->stdout("  {$heading['tag']}: {$heading['text']}\n", Console::FG_BLUE);
                    }
                }
                
                if (isset($texts['paragraphs'])) {
                    $this->stdout("\nПараграфы (" . count($texts['paragraphs']) . "):\n", Console::FG_CYAN);
                    foreach (array_slice($texts['paragraphs'], 0, 3) as $paragraph) {
                        $this->stdout("  " . substr($paragraph, 0, 100) . "...\n", Console::FG_BLUE);
                    }
                }
            }
        }
    }

    /**
     * Сохраняет результаты в файл
     * @param array $results
     * @param string $url
     * @param string $type
     * @return string
     */
    private function saveResults($results, $url, $type)
    {
        $filename = 'parsed_' . date('Y-m-d_H-i-s') . '_' . md5($url) . '.json';
        $runtimePath = \Yii::getAlias('@app/runtime/');
        $filepath = $runtimePath . $filename;
        
        // Проверяем и создаем runtime директорию если нужно
        if (!is_dir($runtimePath)) {
            mkdir($runtimePath, 0755, true);
        }
        
        $data = [
            'url' => $url,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results
        ];
        
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filepath, $jsonData) === false) {
            $this->stderr("Не удалось сохранить файл: {$filename}\n", Console::FG_RED);
            return 'error_saving_file';
        }
        
        return $filename;
    }

    /**
     * Показывает справку по командам
     * @return int
     */
    public function actionHelp()
    {
        $this->stdout("Справка по командам парсинга сайтов:\n\n", Console::FG_GREEN);
        $this->stdout("site-parser/parse <url> [type] - парсит указанный URL\n", Console::FG_BLUE);
        $this->stdout("\nТипы парсинга:\n", Console::FG_YELLOW);
        $this->stdout("  all     - все данные (по умолчанию)\n", Console::FG_BLUE);
        $this->stdout("  links   - только ссылки\n", Console::FG_BLUE);
        $this->stdout("  images  - только изображения\n", Console::FG_BLUE);
        $this->stdout("  text    - только текст\n", Console::FG_BLUE);
        $this->stdout("  meta    - только мета-теги\n", Console::FG_BLUE);
        
        $this->stdout("\nПримеры:\n", Console::FG_YELLOW);
        $this->stdout("  ./yii site-parser/parse https://example.com\n", Console::FG_BLUE);
        $this->stdout("  ./yii site-parser/parse https://example.com links\n", Console::FG_BLUE);
        $this->stdout("  ./yii site-parser/parse https://example.com images\n", Console::FG_BLUE);
        
        return ExitCode::OK;
    }
} 