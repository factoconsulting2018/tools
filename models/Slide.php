<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * This is the model class for table "slides".
 *
 * @property int $id
 * @property string $image_path
 * @property string|null $title
 * @property int $order
 * @property int $created_at
 */
class Slide extends ActiveRecord
{
    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%slides}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order', 'created_at'], 'integer'],
            [['image_path', 'title'], 'string', 'max' => 255],
            [['created_at'], 'default', 'value' => function () {
                return time();
            }],
            [['imageFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg, jpeg, gif', 'maxSize' => 5242880, 'on' => 'create'], // 5MB, required on create
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, gif', 'maxSize' => 5242880, 'on' => 'update'], // 5MB, optional on update
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'image_path' => 'Ruta de Imagen',
            'title' => 'Título',
            'order' => 'Orden',
            'created_at' => 'Fecha de Creación',
            'imageFile' => 'Imagen',
        ];
    }

    /**
     * Upload image
     */
    public function upload()
    {
        if ($this->imageFile) {
            $uploadPath = Yii::getAlias('@webroot/uploads/slides/');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            
            $fileName = time() . '_' . uniqid() . '.' . $this->imageFile->extension;
            $filePath = $uploadPath . $fileName;
            
            if ($this->imageFile->saveAs($filePath)) {
                $this->image_path = '/uploads/slides/' . $fileName;
                return true;
            }
        }
        return false;
    }

    /**
     * Get image URL
     */
    public function getImageUrl()
    {
        return Yii::getAlias('@web') . $this->image_path;
    }

    /**
     * Before save
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = time();
            }
            return true;
        }
        return false;
    }
}

