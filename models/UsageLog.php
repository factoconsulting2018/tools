<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $type
 * @property string|null $identifier
 * @property array|null $metadata
 * @property string $created_at
 */
class UsageLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%usage_log}}';
    }

    public function rules(): array
    {
        return [
            [['type'], 'required'],
            [['metadata'], 'safe'],
            [['type'], 'string', 'max' => 32],
            [['identifier'], 'string', 'max' => 128],
            [['created_at'], 'safe'],
        ];
    }
}


