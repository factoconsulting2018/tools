<?php

/* @var $this yii\web\View */
/* @var $model app\models\Slide */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = $model->isNewRecord ? 'Crear Slide' : 'Actualizar Slide';
$this->params['breadcrumbs'][] = ['label' => 'Admin', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Slides', 'url' => ['slides']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'order')->textInput(['type' => 'number']) ?>

    <?= $form->field($model, 'imageFile')->fileInput() ?>
    
    <?php if (!$model->isNewRecord && $model->image_path): ?>
        <div class="form-group">
            <label>Imagen Actual:</label>
            <br>
            <?= Html::img($model->getImageUrl(), ['style' => 'max-width: 400px; max-height: 200px; margin-top: 10px;']) ?>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Cancelar', ['slides'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

