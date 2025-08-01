<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "nginx_logs".
 *
 * @property int $id
 * @property string $ip_address
 * @property string $request_datetime
 * @property string $url
 * @property string $user_agent
 * @property string|null $operating_system
 * @property string|null $architecture
 * @property string|null $browser
 * @property int $created_at
 * @property int $updated_at
 */
class NginxLog extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%nginx_logs}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ip_address', 'request_datetime', 'url', 'user_agent'], 'required'],
            [['request_datetime'], 'safe'],
            [['url', 'user_agent'], 'string'],
            [['ip_address'], 'string', 'max' => 45],
            [['operating_system', 'browser'], 'string', 'max' => 100],
            [['architecture'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ip_address' => 'IP адрес',
            'request_datetime' => 'Дата/время запроса',
            'url' => 'URL',
            'user_agent' => 'User-Agent',
            'operating_system' => 'Операционная система',
            'architecture' => 'Архитектура',
            'browser' => 'Браузер',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    /**
     * Парсит строку лога nginx
     * @param string $logLine
     * @return array|false
     */
    public static function parseLogLine($logLine)
    {
        // Регулярное выражение для парсинга лога nginx
        $pattern = '/^(\S+) - - \[([^\]]+)\] "([^"]+)" (\d+) (\d+) "([^"]*)" "([^"]*)"$/';
        
        if (!preg_match($pattern, $logLine, $matches)) {
            return false;
        }

        $ipAddress = $matches[1];
        $datetime = $matches[2];
        $request = $matches[3];
        $status = $matches[4];
        $bytes = $matches[5];
        $referer = $matches[6];
        $userAgent = $matches[7];

        // Парсим метод и URL из запроса
        $requestParts = explode(' ', $request, 2);
        $method = $requestParts[0];
        $url = $requestParts[1] ?? '';

        // Парсим User-Agent
        $uaData = self::parseUserAgent($userAgent);

        return [
            'ip_address' => $ipAddress,
            'request_datetime' => self::parseDateTime($datetime),
            'url' => $url,
            'user_agent' => $userAgent,
            'operating_system' => $uaData['os'],
            'architecture' => $uaData['architecture'],
            'browser' => $uaData['browser'],
        ];
    }

    /**
     * Парсит дату и время из лога
     * @param string $datetime
     * @return string
     */
    private static function parseDateTime($datetime)
    {
        // Формат: 21/Mar/2019:00:20:06 +0300
        $date = \DateTime::createFromFormat('d/M/Y:H:i:s O', $datetime);
        return $date ? $date->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }

    /**
     * Парсит User-Agent для извлечения ОС, архитектуры и браузера
     * @param string $userAgent
     * @return array
     */
    private static function parseUserAgent($userAgent)
    {
        $result = [
            'os' => 'Unknown',
            'architecture' => 'Unknown',
            'browser' => 'Unknown',
        ];

        // Определяем браузер
        if (preg_match('/Chrome\/(\d+)/', $userAgent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox\/(\d+)/', $userAgent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/Safari\/(\d+)/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/Edge\/(\d+)/', $userAgent)) {
            $result['browser'] = 'Edge';
        } elseif (preg_match('/MSIE (\d+)/', $userAgent)) {
            $result['browser'] = 'Internet Explorer';
        }

        // Определяем операционную систему
        if (preg_match('/Windows NT (\d+\.\d+)/', $userAgent)) {
            $result['os'] = 'Windows';
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/', $userAgent)) {
            $result['os'] = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $result['os'] = 'Linux';
        } elseif (preg_match('/Android (\d+)/', $userAgent)) {
            $result['os'] = 'Android';
        } elseif (preg_match('/iPhone OS (\d+)/', $userAgent)) {
            $result['os'] = 'iOS';
        }

        // Определяем архитектуру
        if (preg_match('/x86_64|Win64|WOW64/', $userAgent)) {
            $result['architecture'] = 'x64';
        } elseif (preg_match('/x86|Win32/', $userAgent)) {
            $result['architecture'] = 'x86';
        }

        return $result;
    }

    /**
     * Получает статистику по датам
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $os
     * @param string|null $architecture
     * @return array
     */
    public static function getDailyStats($dateFrom = null, $dateTo = null, $os = null, $architecture = null)
    {
        $query = self::find()
            ->select([
                'DATE(request_datetime) as date',
                'COUNT(*) as request_count'
            ])
            ->groupBy('DATE(request_datetime)')
            ->orderBy('date DESC');

        if ($dateFrom) {
            $query->andWhere(['>=', 'request_datetime', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo) {
            $query->andWhere(['<=', 'request_datetime', $dateTo . ' 23:59:59']);
        }
        if ($os) {
            $query->andWhere(['operating_system' => $os]);
        }
        if ($architecture) {
            $query->andWhere(['architecture' => $architecture]);
        }

        $stats = $query->asArray()->all();
        
        // Добавляем самый популярный URL и браузер для каждой даты
        foreach ($stats as &$stat) {
            $date = $stat['date'];
            
            // Самый популярный URL
            $popularUrl = self::find()
                ->select(['url', 'COUNT(*) as count'])
                ->where(['DATE(request_datetime)' => $date])
                ->groupBy('url')
                ->orderBy(['count' => SORT_DESC])
                ->limit(1)
                ->asArray()
                ->one();
            
            $stat['most_popular_url'] = $popularUrl ? $popularUrl['url'] : '';
            
            // Самый популярный браузер
            $popularBrowser = self::find()
                ->select(['browser', 'COUNT(*) as count'])
                ->where(['DATE(request_datetime)' => $date])
                ->andWhere(['not', ['browser' => null]])
                ->groupBy('browser')
                ->orderBy(['count' => SORT_DESC])
                ->limit(1)
                ->asArray()
                ->one();
            
            $stat['most_popular_browser'] = $popularBrowser ? $popularBrowser['browser'] : '';
        }
        
        return $stats;
    }

    /**
     * Получает топ-3 браузера по популярности
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $os
     * @param string|null $architecture
     * @return array
     */
    public static function getTopBrowsers($dateFrom = null, $dateTo = null, $os = null, $architecture = null)
    {
        $query = self::find()
            ->select([
                'browser',
                'COUNT(*) as count'
            ])
            ->groupBy('browser')
            ->orderBy('count DESC')
            ->limit(3);

        if ($dateFrom) {
            $query->andWhere(['>=', 'request_datetime', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo) {
            $query->andWhere(['<=', 'request_datetime', $dateTo . ' 23:59:59']);
        }
        if ($os) {
            $query->andWhere(['operating_system' => $os]);
        }
        if ($architecture) {
            $query->andWhere(['architecture' => $architecture]);
        }

        return $query->asArray()->all();
    }

    /**
     * Получает список доступных операционных систем
     * @return array
     */
    public static function getAvailableOperatingSystems()
    {
        return self::find()
            ->select('operating_system')
            ->distinct()
            ->where(['not', ['operating_system' => null]])
            ->column();
    }

    /**
     * Получает список доступных архитектур
     * @return array
     */
    public static function getAvailableArchitectures()
    {
        return self::find()
            ->select('architecture')
            ->distinct()
            ->where(['not', ['architecture' => null]])
            ->column();
    }

    /**
     * Получает детальную статистику с IP, User-Agent и ОС
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $os
     * @param string|null $architecture
     * @return array
     */
    public static function getDetailedStats($dateFrom = null, $dateTo = null, $os = null, $architecture = null)
    {
        $query = self::find()
            ->select([
                'ip_address',
                'user_agent',
                'operating_system',
                'browser',
                'url',
                'request_datetime'
            ])
            ->orderBy('request_datetime DESC')
            ->limit(5); // Показываем только 5 последних записей

        if ($dateFrom) {
            $query->andWhere(['>=', 'request_datetime', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo) {
            $query->andWhere(['<=', 'request_datetime', $dateTo . ' 23:59:59']);
        }
        if ($os) {
            $query->andWhere(['operating_system' => $os]);
        }
        if ($architecture) {
            $query->andWhere(['architecture' => $architecture]);
        }

        return $query->asArray()->all();
    }
} 