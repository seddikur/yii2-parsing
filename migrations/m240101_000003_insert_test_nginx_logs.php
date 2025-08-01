<?php

use yii\db\Migration;

/**
 * Вставляет тестовые данные в таблицу nginx_logs
 */
class m240101_000003_insert_test_nginx_logs extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $testData = $this->generateTestData();
        
        foreach ($testData as $data) {
            $this->insert('{{%nginx_logs}}', $data);
        }
        
        echo "Добавлено " . count($testData) . " тестовых записей в таблицу nginx_logs\n";
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%nginx_logs}}', ['id' => range(1, 30)]);
        echo "Удалены тестовые записи из таблицы nginx_logs\n";
    }

    /**
     * Генерирует тестовые данные
     * @return array
     */
    private function generateTestData()
    {
        $data = [];
        $now = time();
        $oneMonthAgo = $now - (30 * 24 * 60 * 60); // 30 дней назад
        
        // Тестовые IP адреса
        $ipAddresses = [
            '192.168.1.100',
            '192.168.1.101',
            '192.168.1.102',
            '10.0.0.50',
            '10.0.0.51',
            '172.16.0.10',
            '172.16.0.11',
            '203.0.113.1',
            '203.0.113.2',
            '198.51.100.1'
        ];
        
        // Тестовые URL
        $urls = [
            '/',
            '/about',
            '/contact',
            '/products',
            '/services',
            '/blog',
            '/news',
            '/faq',
            '/support',
            '/login',
            '/register',
            '/admin',
            '/api/users',
            '/api/products',
            '/images/logo.png',
            '/css/style.css',
            '/js/app.js',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml'
        ];
        
        // Тестовые User-Agent строки
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/91.0.864.59',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36'
        ];
        
        // Операционные системы
        $operatingSystems = ['Windows', 'macOS', 'Linux', 'iOS', 'Android'];
        
        // Архитектуры
        $architectures = ['x64', 'x86'];
        
        // Браузеры
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge'];
        
        for ($i = 0; $i < 30; $i++) {
            // Генерируем случайную дату в пределах последнего месяца
            $randomTimestamp = rand($oneMonthAgo, $now);
            $requestDatetime = date('Y-m-d H:i:s', $randomTimestamp);
            
            // Выбираем случайные данные
            $ipAddress = $ipAddresses[array_rand($ipAddresses)];
            $url = $urls[array_rand($urls)];
            $userAgent = $userAgents[array_rand($userAgents)];
            $os = $operatingSystems[array_rand($operatingSystems)];
            $architecture = $architectures[array_rand($architectures)];
            $browser = $browsers[array_rand($browsers)];
            
            $data[] = [
                'ip_address' => $ipAddress,
                'request_datetime' => $requestDatetime,
                'url' => $url,
                'user_agent' => $userAgent,
                'operating_system' => $os,
                'architecture' => $architecture,
                'browser' => $browser,
                'created_at' => $randomTimestamp,
                'updated_at' => $randomTimestamp,
            ];
        }
        
        return $data;
    }
} 