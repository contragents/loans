<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%request}}`.
 */
class m251108_112842_create_request_table extends Migration
{
    private $tableName = '{{%request}}';

    private const INDICES = [
        'idx-request-status' => 'status',
        'idx-request-user_id_status' => ['user_id', 'status'],
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(
            $this->tableName,
            [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'amount' => $this->integer()->notNull(),
                'term' => $this->integer()->notNull(),
                'status' => $this->string(10)->notNull()->defaultValue('pending'),
            ]
        );

        // Создаем индексы для ускорения выборок
        foreach (self::INDICES as $name => $fields) {
            $this->createIndex($name, $this->tableName, $fields);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем таблицу
        $this->dropTable($this->tableName);
    }
}
