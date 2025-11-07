<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

use yii\helpers\Html;

$this->title = $name;
?>
<div class="site-error" style="padding: 3rem; text-align: center;">
    <h1><?= Html::encode($this->title) ?></h1>
    <div class="alert alert-danger">
        <?= nl2br(Html::encode($message)) ?>
    </div>
    <p>
        El error anterior ocurrió mientras el servidor Web procesaba su solicitud.
    </p>
    <p>
        Por favor contáctenos si cree que esto es un error del servidor. Gracias.
    </p>
</div>

