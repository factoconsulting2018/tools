<?php

namespace app\models;

use yii\base\Model;

class HaciendaSearchForm extends Model
{
    public const TYPE_FISICA = 'fisica';
    public const TYPE_JURIDICA = 'juridica';
    public const TYPE_DIMEX = 'dimex';
    public const TYPE_NITE = 'nite';
    public const TYPE_EXTRANJERO = 'extranjero';

    public string $type = self::TYPE_FISICA;
    public string $identificacion = '';

    public function rules(): array
    {
        return [
            [['type', 'identificacion'], 'required'],
            ['type', 'in', 'range' => array_keys($this->getTypeOptions())],
            ['identificacion', 'match', 'pattern' => '/^\d{9,12}$/', 'message' => 'Ingrese solo números (9 a 12 dígitos).'],
            ['identificacion', 'validateByType'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'type' => 'Tipo de identificación',
            'identificacion' => 'Número de identificación',
        ];
    }

    public function getTypeOptions(): array
    {
        return [
            self::TYPE_FISICA => 'Física',
            self::TYPE_JURIDICA => 'Jurídica',
            self::TYPE_DIMEX => 'Dimex',
            self::TYPE_NITE => 'NITE',
            self::TYPE_EXTRANJERO => 'Extranjero',
        ];
    }

    public function validateByType(): void
    {
        if ($this->hasErrors('identificacion')) {
            return;
        }

        $length = strlen($this->identificacion);
        switch ($this->type) {
            case self::TYPE_FISICA:
                if ($length !== 9) {
                    $this->addError('identificacion', 'La cédula física debe tener 9 dígitos.');
                }
                break;
            case self::TYPE_JURIDICA:
                if ($length !== 10) {
                    $this->addError('identificacion', 'La cédula jurídica debe tener 10 dígitos.');
                }
                break;
            case self::TYPE_DIMEX:
                if ($length < 11 || $length > 12) {
                    $this->addError('identificacion', 'El número DIMEX debe tener entre 11 y 12 dígitos.');
                }
                break;
            default:
                // NITE y Extranjero no tienen validación rígida adicional
                break;
        }
    }

    public function formName(): string
    {
        return 'HaciendaSearchForm';
    }

    public function getNormalizedIdentificacion(): string
    {
        return preg_replace('/\D+/', '', $this->identificacion) ?? '';
    }
}


