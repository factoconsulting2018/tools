<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%usage_log}}`.
 */
class m240607_000001_create_usage_log_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%usage_log}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(32)->notNull(),
            'identifier' => $this->string(128),
            'metadata' => $this->json()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx-usage_log-type-created_at', '{{%usage_log}}', ['type', 'created_at']);
        $this->createIndex('idx-usage_log-created_at', '{{%usage_log}}', ['created_at']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%usage_log}}');
    }
}


