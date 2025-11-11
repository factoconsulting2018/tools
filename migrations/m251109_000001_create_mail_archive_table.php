<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%mail_archive}}`.
 */
class m251109_000001_create_mail_archive_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mail_archive}}', [
            'id' => $this->primaryKey(),
            'account_email' => $this->string()->notNull(),
            'period_start' => $this->date()->notNull(),
            'period_end' => $this->date()->notNull(),
            'file_type' => $this->string(20)->notNull(),
            'file_name' => $this->string()->notNull(),
            'file_path' => $this->string()->notNull(),
            'total_messages' => $this->integer()->notNull()->defaultValue(0),
            'total_invoices' => $this->integer()->notNull()->defaultValue(0),
            'status' => $this->string(20)->notNull()->defaultValue('stored'),
            'metadata' => $this->text(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_mail_archive_account_email', '{{%mail_archive}}', 'account_email');
        $this->createIndex('idx_mail_archive_created_at', '{{%mail_archive}}', 'created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mail_archive}}');
    }
}

