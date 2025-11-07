<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%slides}}`.
 */
class m240101_000001_create_slides_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%slides}}', [
            'id' => $this->primaryKey(),
            'image_path' => $this->string(255)->notNull(),
            'title' => $this->string(255),
            'order' => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%slides}}');
    }
}

