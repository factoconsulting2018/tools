<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "buttons".
 *
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string|null $icon
 * @property int $order
 * @property int $created_at
 */
class Button extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%buttons}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'url'], 'required'],
            [['order', 'created_at'], 'integer'],
            [['title', 'icon'], 'string', 'max' => 255],
            [['url'], 'string', 'max' => 500],
            [['url'], 'url'],
            [['created_at'], 'default', 'value' => function () {
                return time();
            }],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Título',
            'url' => 'URL',
            'icon' => 'Icono',
            'order' => 'Orden',
            'created_at' => 'Fecha de Creación',
        ];
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

