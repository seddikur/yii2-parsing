<?php

namespace app\commands;

use app\models\NginxLog;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Консольная команда для парсинга логов nginx
 */
class LogParserController extends Controller
{
    /**
     * Парсит файл логов nginx и загружает данные в базу
     * @param string $file Путь к файлу логов
     * @return int
     */
    public function actionParse($file)
    {
        if (!file_exists($file)) {
            $this->stderr("Файл {$file} не найден!\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Начинаем парсинг файла: {$file}\n", Console::FG_GREEN);

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->stderr("Не удалось открыть файл {$file}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $processed = 0;
        $saved = 0;
        $errors = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $processed++;

            // Парсим строку лога
            $logData = NginxLog::parseLogLine($line);
            if (!$logData) {
                $errors++;
                continue;
            }

            // Проверяем, не существует ли уже такая запись
            $exists = NginxLog::find()
                ->where([
                    'ip_address' => $logData['ip_address'],
                    'request_datetime' => $logData['request_datetime'],
                    'url' => $logData['url'],
                    'user_agent' => $logData['user_agent'],
                ])
                ->exists();

            if ($exists) {
                continue;
            }

            // Создаем новую запись
            $log = new NginxLog();
            $log->setAttributes($logData);

            if ($log->save()) {
                $saved++;
            } else {
                $errors++;
            }

            // Показываем прогресс каждые 1000 записей
            if ($processed % 1000 === 0) {
                $this->stdout("Обработано: {$processed}, Сохранено: {$saved}, Ошибок: {$errors}\n", Console::FG_YELLOW);
            }
        }

        fclose($handle);

        $this->stdout("\nПарсинг завершен!\n", Console::FG_GREEN);
        $this->stdout("Всего обработано: {$processed}\n", Console::FG_BLUE);
        $this->stdout("Сохранено: {$saved}\n", Console::FG_GREEN);
        $this->stdout("Ошибок: {$errors}\n", Console::FG_RED);

        return ExitCode::OK;
    }

    /**
     * Создает тестовые данные для демонстрации
     * @return int
     */
    public function actionCreateTestData()
    {
        $this->stdout("Создаем тестовые данные...\n", Console::FG_GREEN);

        $testLogs = [
            '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 "http://modimio.loc/icms/catalog/catalog_edit?id=4" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"',
            '192.168.1.100 - - [21/Mar/2019:01:15:30 +0300] "GET /api/users HTTP/1.1" 200 2048 "http://example.com/dashboard" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"',
            '10.0.0.50 - - [21/Mar/2019:02:45:12 +0300] "POST /api/login HTTP/1.1" 200 512 "http://example.com/login" "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"',
            '172.16.0.25 - - [21/Mar/2019:03:30:45 +0300] "GET /css/style.css HTTP/1.1" 200 1024 "http://example.com/page" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0"',
            '192.168.1.200 - - [21/Mar/2019:04:12:18 +0300] "GET /js/app.js HTTP/1.1" 200 2048 "http://example.com/app" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"',
            '127.0.0.1 - - [22/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 "http://modimio.loc/icms/catalog/catalog_edit?id=4" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"',
            '192.168.1.100 - - [22/Mar/2019:01:15:30 +0300] "GET /api/users HTTP/1.1" 200 2048 "http://example.com/dashboard" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"',
            '10.0.0.50 - - [22/Mar/2019:02:45:12 +0300] "POST /api/login HTTP/1.1" 200 512 "http://example.com/login" "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"',
            '172.16.0.25 - - [22/Mar/2019:03:30:45 +0300] "GET /css/style.css HTTP/1.1" 200 1024 "http://example.com/page" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0"',
            '192.168.1.200 - - [22/Mar/2019:04:12:18 +0300] "GET /js/app.js HTTP/1.1" 200 2048 "http://example.com/app" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"',
        ];

        $saved = 0;
        foreach ($testLogs as $logLine) {
            $logData = NginxLog::parseLogLine($logLine);
            if ($logData) {
                $log = new NginxLog();
                $log->setAttributes($logData);
                if ($log->save()) {
                    $saved++;
                }
            }
        }

        $this->stdout("Создано тестовых записей: {$saved}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Очищает все данные из таблицы логов
     * @return int
     */
    public function actionClear()
    {
        $this->stdout("Очищаем таблицу логов...\n", Console::FG_YELLOW);
        
        $deleted = NginxLog::deleteAll();
        
        $this->stdout("Удалено записей: {$deleted}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
} 