<?php

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Gestionar Slides';
$this->params['breadcrumbs'][] = ['label' => 'Admin', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><?= Html::encode($this->title) ?></h1>
        <p>Sube y gestiona las imágenes del banner principal (máximo 5 slides)</p>
    </div>

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success">
            <?= Yii::$app->session->getFlash('success') ?>
        </div>
    <?php endif; ?>
    
    <?php if (Yii::$app->session->hasFlash('error')): ?>
        <div class="alert alert-error">
            <?= Yii::$app->session->getFlash('error') ?>
        </div>
    <?php endif; ?>

    <p>
        <?= Html::a('Crear Nuevo Slide', ['create-slide'], ['class' => 'btn btn-success']) ?>
        <?= Html::a('Volver al Panel', ['index'], ['class' => 'btn btn-primary']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'image_path',
                'label' => 'Imagen',
                'format' => 'raw',
                'value' => function ($model) {
                    return Html::img($model->getImageUrl(), ['style' => 'max-width: 200px; max-height: 100px;']);
                },
            ],
            'title',
            'order',
            'created_at:datetime',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<i class="material-icons">edit</i>', $url, ['class' => 'btn btn-primary btn-sm', 'title' => 'Editar']);
                    },
                    'delete' => function ($url, $model) {
                        return Html::a('<i class="material-icons">delete</i>', $url, [
                            'class' => 'btn btn-danger btn-sm',
                            'title' => 'Eliminar',
                            'data-confirm' => '¿Está seguro que desea eliminar este slide?',
                            'data-method' => 'post',
                        ]);
                    },
                ],
                'urlCreator' => function ($action, $model, $key, $index) {
                    switch ($action) {
                        case 'update':
                            return ['update-slide', 'id' => $model->id];
                        case 'delete':
                            return ['delete-slide', 'id' => $model->id];
                        default:
                            return [$action, 'id' => $model->id];
                    }
                },
            ],
        ],
    ]); ?>
</div>

