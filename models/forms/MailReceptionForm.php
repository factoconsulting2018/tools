<?php

namespace app\models\forms;

use yii\base\Model;

class MailReceptionForm extends Model
{
    public $username;
    public $email;
    public $password;
    public $host = 'mail.factoenlanube.com';
    public $port = 993;
    public $encryption = 'ssl';
    public $validateCertificate = true;
    public $folder = 'INBOX';
    public $label;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['username', 'email', 'password', 'host', 'port', 'encryption', 'folder'], 'required'],
            ['label', 'string', 'max' => 255],
            [['username'], 'string', 'max' => 255],
            ['email', 'email'],
            [['host', 'encryption', 'folder'], 'string', 'max' => 255],
            ['port', 'filter', 'filter' => 'intval'],
            ['port', 'integer', 'min' => 1, 'max' => 65535],
            ['validateCertificate', 'boolean'],
            [
                'validateCertificate',
                'filter',
                'filter' => static function ($value) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'email' => 'Correo electrónico',
            'username' => 'Usuario',
            'password' => 'Contraseña',
            'host' => 'Servidor IMAP',
            'port' => 'Puerto',
            'encryption' => 'Seguridad',
            'validateCertificate' => 'Validar certificado SSL/TLS',
            'folder' => 'Carpeta',
        ];
    }

    /**
     * Construye la cadena de conexión IMAP en formato {host:port/options}FOLDER.
     */
    public function getImapMailboxString(): string
    {
        $encryption = strtolower($this->encryption);
        $options = '/imap';

        if ($encryption === 'ssl') {
            $options .= '/ssl';
        } elseif ($encryption === 'tls') {
            $options .= '/tls';
        } else {
            $options .= '/notls';
        }

        if (!$this->validateCertificate) {
            $options .= '/novalidate-cert';
        }

        return sprintf('{%s:%d%s}%s', $this->host, $this->port, $options, $this->folder);
    }

    public function getLoginIdentifier(): string
    {
        return $this->username ?: $this->email;
    }
}

