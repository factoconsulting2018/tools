<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Modelo para cuentas de correo reutilizables.
 *
 * @property int $id
 * @property string $label
 * @property string $email
 * @property string $username
 * @property string $password
 * @property string $host
 * @property int $port
 * @property string $encryption
 * @property string $folder
 * @property int $validate_certificate
 * @property string $created_at
 * @property string $updated_at
 */
class MailAccount extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%mail_account}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => static function () {
                    return date('Y-m-d H:i:s');
                },
            ],
        ];
    }

    public function rules()
    {
        return [
            [['label', 'email', 'username', 'password', 'host'], 'required'],
            [['label', 'email', 'username', 'host', 'folder'], 'string', 'max' => 255],
            ['email', 'email'],
            [['password'], 'string'],
            ['port', 'integer', 'min' => 1, 'max' => 65535],
            ['encryption', 'in', 'range' => ['ssl', 'tls', 'none']],
            ['encryption', 'default', 'value' => 'ssl'],
            ['folder', 'default', 'value' => 'INBOX'],
            ['validate_certificate', 'boolean'],
            ['validate_certificate', 'default', 'value' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'label' => 'Nombre de la cuenta',
            'email' => 'Correo electrónico',
            'username' => 'Usuario IMAP',
            'password' => 'Contraseña',
            'host' => 'Servidor IMAP',
            'port' => 'Puerto',
            'encryption' => 'Seguridad',
            'folder' => 'Carpeta',
            'validate_certificate' => 'Validar certificado SSL/TLS',
        ];
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'folder' => $this->folder,
            'validate_certificate' => (int)$this->validate_certificate,
        ];
    }
}

