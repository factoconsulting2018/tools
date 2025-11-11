<?php

namespace app\models\forms;

use app\models\MailArchive;
use yii\base\Model;

class MailReceptionRequestForm extends Model
{
    public $startDate;
    public $endDate;
    public $outputType = MailArchive::TYPE_PDF;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['startDate', 'endDate', 'outputType'], 'required'],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            ['outputType', 'in', 'range' => [MailArchive::TYPE_PDF, MailArchive::TYPE_ZIP, MailArchive::TYPE_RAR]],
            ['endDate', 'compare', 'compareAttribute' => 'startDate', 'operator' => '>=', 'type' => 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'startDate' => 'Fecha inicial',
            'endDate' => 'Fecha final',
            'outputType' => 'Tipo de archivo',
        ];
    }
}

