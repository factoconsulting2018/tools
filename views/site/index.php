<?php

/* @var $this yii\web\View */
/* @var $slides app\models\Slide[] */
/* @var $buttons app\models\Button[] */
/* @var $searchModel app\models\HaciendaSearchForm */
/* @var $appConfig array */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Json;
use yii\web\View;

$this->title = 'Inicio';
$this->params['breadcrumbs'][] = $this->title;

$config = array_merge($appConfig, [
    'csrfToken' => Yii::$app->request->getCsrfToken(),
]);
$this->registerJs('window.appConfig = ' . Json::encode($config) . ';', View::POS_HEAD);
?>

<div class="site-index">
    <!-- Slides Carousel -->
    <div class="slides-container">
        <?php if (empty($slides)): ?>
            <!-- Default slide if no images uploaded -->
            <div class="slide active" style="background-image: linear-gradient(135deg, #0F1D41 0%, #17285C 100%);">
                <div class="slide-overlay"></div>
            </div>
        <?php else: ?>
            <?php foreach ($slides as $index => $slide): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= $slide->getImageUrl() ?>');">
                    <div class="slide-overlay"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Search Overlay -->
        <div class="slide-search-overlay">
            <div class="hacienda-search-card">
                <?php $haciendaForm = ActiveForm::begin([
                    'id' => 'hacienda-form',
                    'action' => ['site/buscar-cedula'],
                    'options' => ['class' => 'hacienda-form'],
                ]); ?>

                <h3>Consulta tributaria</h3>
                <p class="hacienda-form__subtitle">Busca información oficial en Hacienda ingresando la identificación sin guiones.</p>

                <div class="hacienda-form__fields">
                    <?= $haciendaForm->field($searchModel, 'type', [
                        'options' => ['class' => 'form-group hacienda-form__field'],
                    ])->dropDownList($searchModel->getTypeOptions(), [
                        'class' => 'form-control',
                    ])->label('Tipo de identificación') ?>

                    <?= $haciendaForm->field($searchModel, 'identificacion', [
                        'options' => ['class' => 'form-group hacienda-form__field hacienda-form__field--wide'],
                    ])->textInput([
                        'class' => 'form-control',
                        'placeholder' => 'Ej. 2100042005',
                        'maxlength' => 12,
                    ])->label('Número de identificación *') ?>
                </div>

                <div class="form-actions">
                    <?= Html::button('Consultar en Hacienda', ['class' => 'btn-submit', 'id' => 'hacienda-submit', 'type' => 'button']) ?>
                </div>

                <?php ActiveForm::end(); ?>

                <div class="mass-query">
                    <button type="button" class="btn-massive" id="toggle-mass-query">Consulta masiva en Hacienda</button>
                    <div class="mass-query__panel hidden" id="mass-query-panel">
                        <p class="mass-query__help">Pega varias identificaciones (una por línea). Se consultarán en orden y podrás navegar cada resultado en el modal.</p>
                        <textarea id="mass-query-input" class="form-control mass-query__textarea" rows="6" placeholder="Ej.&#10;112610049&#10;3101784532&#10;3101881473"></textarea>
                        <div class="mass-query__actions">
                            <button type="button" class="btn btn-primary" id="mass-query-submit">Consultar listado</button>
                            <span class="mass-query__status" id="mass-query-status">
                                <span class="mass-query__spinner hidden" id="mass-query-spinner"></span>
                                <span id="mass-query-status-text"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="hacienda-modal" class="hacienda-modal hidden" role="dialog" aria-modal="true" aria-labelledby="hacienda-modal-title">
        <div class="hacienda-modal__dialog">
            <button type="button" class="hacienda-modal__close" aria-label="Cerrar resultado">&times;</button>
            <div class="hacienda-modal__header">
                <h4 id="hacienda-modal-title">Resultado de la consulta</h4>
            </div>
            <div class="hacienda-modal__body">
                <p class="placeholder">Realiza una búsqueda para visualizar los datos del contribuyente.</p>
            </div>
            <div class="hacienda-modal__footer">
                <div class="hacienda-modal__nav">
                    <button type="button" class="btn hacienda-nav-btn" id="mass-prev" disabled>&laquo; Anterior</button>
                    <span class="hacienda-modal__indicator" id="mass-indicator"></span>
                    <button type="button" class="btn hacienda-nav-btn" id="mass-next" disabled>Siguiente &raquo;</button>
                </div>
                <div class="hacienda-modal__actions">
                    <button type="button" class="btn hacienda-copy-actividades" data-copy="actividades">Copiar número de actividad económica</button>
                    <button type="button" class="btn hacienda-copy-completa" data-copy="completa">Copiar toda la información</button>
                    <button type="button" class="btn hacienda-export" id="mass-export" disabled>Descargar Excel</button>
                </div>
                <button type="button" class="btn btn-primary hacienda-modal__close-btn">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Funciones especiales Section -->
    <div class="special-section">
        <h2>Funciones especiales</h2>
        <div class="special-grid">
            <a href="#" class="action-button" id="xml-viewer-button">
                <i class="material-icons">bolt</i>
                <span>Visor XML Gráfico</span>
            </a>
            <a href="#" class="action-button" id="dollar-exchange-button">
                <i class="material-icons">attach_money</i>
                <span>Precio del Dólar<br><small>Costa Rica</small></span>
            </a>
            <a href="https://mail.factoenlanube.com/mail/" class="action-button" target="_blank" rel="noopener noreferrer">
                <i class="material-icons">mail_outline</i>
                <span>Correo de Facturación</span>
            </a>
        </div>
        <input type="file" id="xml-upload" accept=".xml" multiple hidden>
    </div>

    <!-- Buttons Section -->
    <div class="buttons-section">
        <h2>Nuestras Herramientas</h2>
        <div class="buttons-grid">
            <?php if (empty($buttons)): ?>
                <!-- Default buttons if none configured -->
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <a href="#" class="action-button">
                        <i class="material-icons">settings</i>
                        <span>Botón <?= $i ?></span>
                    </a>
                <?php endfor; ?>
            <?php else: ?>
                <?php 
                // Fill remaining slots if less than 6 buttons
                $buttonCount = count($buttons);
                for ($i = 0; $i < 6; $i++): 
                    if ($i < $buttonCount):
                        $button = $buttons[$i];
                ?>
                    <a href="<?= Html::encode($button->url) ?>" target="_blank" class="action-button">
                        <i class="material-icons"><?= $button->icon ?: 'link' ?></i>
                        <span><?= Html::encode($button->title) ?></span>
                    </a>
                <?php else: ?>
                    <a href="#" class="action-button">
                        <i class="material-icons">settings</i>
                        <span>Botón <?= $i + 1 ?></span>
                    </a>
                <?php endif; endfor; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

