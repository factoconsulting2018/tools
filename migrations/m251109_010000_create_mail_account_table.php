<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%mail_account}}`.
 */
class m251109_010000_create_mail_account_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%mail_account}}', [
            'id' => $this->primaryKey(),
            'label' => $this->string()->notNull(),
            'username' => $this->string()->notNull(),
            'email' => $this->string()->notNull(),
            'password' => $this->text()->notNull(),
            'host' => $this->string()->notNull(),
            'port' => $this->integer()->notNull()->defaultValue(993),
            'encryption' => $this->string(20)->notNull()->defaultValue('ssl'),
            'validate_certificate' => $this->boolean()->notNull()->defaultValue(true),
            'folder' => $this->string()->notNull()->defaultValue('INBOX'),
            'metadata' => $this->text(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_mail_account_email', '{{%mail_account}}', 'email');
        $this->createIndex('idx_mail_account_label', '{{%mail_account}}', 'label');
    }

    public function safeDown()
    {
        $this->dropTable('{{%mail_account}}');
    }
}

