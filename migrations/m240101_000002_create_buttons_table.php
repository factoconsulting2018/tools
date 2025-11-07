<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%buttons}}`.
 */
class m240101_000002_create_buttons_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%buttons}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'url' => $this->string(500)->notNull(),
            'icon' => $this->string(100),
            'order' => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%buttons}}');
    }
}

