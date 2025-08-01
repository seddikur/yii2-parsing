<?php

use yii\db\Migration;

/**
 * Class m240101_000002_create_nginx_logs_table
 */
class m240101_000002_create_nginx_logs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%nginx_logs}}', [
            'id' => $this->primaryKey(),
            'ip_address' => $this->string(45)->notNull()->comment('IP адрес'),
            'request_datetime' => $this->dateTime()->notNull()->comment('Дата/время запроса'),
            'url' => $this->text()->notNull()->comment('URL запроса'),
            'user_agent' => $this->text()->notNull()->comment('User-Agent'),
            'operating_system' => $this->string(100)->comment('Операционная система'),
            'architecture' => $this->string(20)->comment('Архитектура (x86/x64)'),
            'browser' => $this->string(100)->comment('Браузер'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // Индексы для оптимизации запросов
        $this->createIndex('idx_nginx_logs_request_datetime', '{{%nginx_logs}}', 'request_datetime');
        $this->createIndex('idx_nginx_logs_ip_address', '{{%nginx_logs}}', 'ip_address');
        $this->createIndex('idx_nginx_logs_operating_system', '{{%nginx_logs}}', 'operating_system');
        $this->createIndex('idx_nginx_logs_architecture', '{{%nginx_logs}}', 'architecture');
        $this->createIndex('idx_nginx_logs_browser', '{{%nginx_logs}}', 'browser');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%nginx_logs}}');
    }
} 