<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "mail_archive".
 *
 * @property int $id
 * @property string $account_email
 * @property string $period_start
 * @property string $period_end
 * @property string $file_type
 * @property string $file_name
 * @property string $file_path
 * @property int $total_messages
 * @property int $total_invoices
 * @property string $status
 * @property string|null $metadata
 * @property string $created_at
 * @property string $updated_at
 */
class MailArchive extends ActiveRecord
{
    public const TYPE_PDF = 'pdf';
    public const TYPE_ZIP = 'zip';
    public const TYPE_RAR = 'rar';

    public const STATUS_STORED = 'stored';
    public const STATUS_DELETED = 'deleted';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%mail_archive}}';
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['account_email', 'period_start', 'period_end', 'file_type', 'file_name', 'file_path'], 'required'],
            [['period_start', 'period_end', 'created_at', 'updated_at'], 'safe'],
            [['total_messages', 'total_invoices'], 'integer'],
            [['metadata'], 'string'],
            [['account_email', 'file_name', 'file_path'], 'string', 'max' => 255],
            [['file_type', 'status'], 'string', 'max' => 20],
            ['file_type', 'in', 'range' => [self::TYPE_PDF, self::TYPE_ZIP, self::TYPE_RAR]],
            ['status', 'default', 'value' => self::STATUS_STORED],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'account_email' => 'Correo analizado',
            'period_start' => 'Inicio del periodo',
            'period_end' => 'Fin del periodo',
            'file_type' => 'Tipo de archivo',
            'file_name' => 'Nombre del archivo',
            'file_path' => 'Ruta del archivo',
            'total_messages' => 'Correos procesados',
            'total_invoices' => 'Facturas encontradas',
            'status' => 'Estado',
            'metadata' => 'Metadatos',
            'created_at' => 'Creado',
            'updated_at' => 'Actualizado',
        ];
    }

    /**
     * Obtiene los metadatos como arreglo.
     *
     * @return array|null
     */
    public function getMetadataArray(): ?array
    {
        if (empty($this->metadata)) {
            return null;
        }

        $data = json_decode($this->metadata, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Devuelve la ruta absoluta del archivo si existe.
     */
    public function getAbsolutePath(): ?string
    {
        $path = Yii::getAlias($this->file_path);

        return is_string($path) ? $path : null;
    }

    /**
     * Devuelve una etiqueta legible para el tipo de archivo.
     */
    public function getFileTypeLabel(): string
    {
        switch ($this->file_type) {
            case self::TYPE_PDF:
                return 'PDF';
            case self::TYPE_ZIP:
                return 'ZIP';
            case self::TYPE_RAR:
                return 'WinRAR';
            default:
                return strtoupper($this->file_type);
        }
    }
}

