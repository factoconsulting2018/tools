<?php

/* @var $this yii\web\View */
/* @var $credentialsForm \app\models\forms\MailReceptionForm */
/* @var $requestForm \app\models\forms\MailReceptionRequestForm */
/* @var $archives \app\models\MailArchive[] */
/* @var $mailAccounts array */

use app\models\MailArchive;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\helpers\Json;

$this->title = 'Recepcionar Facturas';
$this->params['breadcrumbs'][] = ['label' => 'Panel de Administración', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$connectionUrl = Url::to(['admin/mail-connection-test']);
$processUrl = Url::to(['admin/mail-process']);
$downloadUrl = Url::to(['admin/mail-archive-download']);
$deleteUrl = Url::to(['admin/mail-archive-delete']);
$accountSaveUrl = Url::to(['admin/mail-account-save']);
$accountDeleteUrl = Url::to(['admin/mail-account-delete']);
$accountListUrl = Url::to(['admin/mail-account-list']);
$accountsJson = Json::encode($mailAccounts);

$this->registerJsFile('@web/js/admin-invoice-reception.js', [
    'depends' => \yii\web\JqueryAsset::class,
]);
$this->registerJs(
    "window.invoiceReceptionConfig = {
        connectionUrl: '{$connectionUrl}',
        processUrl: '{$processUrl}',
        deleteUrl: '{$deleteUrl}',
        accountSaveUrl: '{$accountSaveUrl}',
        accountDeleteUrl: '{$accountDeleteUrl}',
        accountListUrl: '{$accountListUrl}',
        accounts: {$accountsJson},
        csrf: '" . Yii::$app->request->getCsrfToken() . "'
    };",
    yii\web\View::POS_HEAD
);
?>

<div class="invoice-reception-container">
    <div class="page-title">
        <h1><?= Html::encode($this->title) ?></h1>
        <p>Conecta una cuenta de correo y genera reportes o paquetes de facturas electrónicas.</p>
    </div>

    <div class="admin-card">
        <div class="admin-card__header">
            <h3>Credenciales de conexión</h3>
        </div>
        <div class="admin-card__body">
            <div class="account-selector mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <label for="mail-accounts-select" class="form-label mb-0">Mostrar cuentas</label>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Selecciona una cuenta guardada para rellenar automáticamente los datos.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select id="mail-accounts-select" class="form-control" style="min-width: 220px;">
                            <option value="">Selecciona una cuenta guardada</option>
                            <?php foreach ($mailAccounts as $account): ?>
                                <option value="<?= (int)$account['id'] ?>"><?= Html::encode($account['label'] . ' (' . $account['email'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary" id="create-account-btn">
                            Crear una cuenta
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="edit-account-btn" disabled>
                            Editar cuenta
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="delete-account-btn" disabled>
                            Eliminar cuenta
                        </button>
                    </div>
                </div>
                <div class="feedback-message" id="account-feedback"></div>
            </div>

            <?php $form = ActiveForm::begin([
                'id' => 'mail-credentials-form',
                'enableClientValidation' => false,
            ]); ?>

            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'label')
                        ->textInput(['placeholder' => 'Nombre de la cuenta', 'id' => 'credentials-label'])
                        ->hint('Ejemplo: Cuenta Hacienda Principal') ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'username')
                        ->textInput(['placeholder' => 'Nombre de usuario IMAP'])
                        ->hint('Ejemplo: usuario.imap@factoenlanube.com') ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'email')
                        ->textInput(['placeholder' => 'correo@factoenlanube.com'])
                        ->hint('Ejemplo: 3102934042@factoenlanube.com') ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'password')
                        ->passwordInput(['placeholder' => 'Contraseña del correo'])
                        ->hint('Ejemplo: Facto2024+ (la contraseña real del buzón)') ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'folder')
                        ->textInput(['placeholder' => 'INBOX'])
                        ->hint('Ejemplo: INBOX, Sent, Facturas/Entrantes') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-5">
                    <?= $form->field($credentialsForm, 'host')
                        ->textInput()
                        ->hint('Ejemplo: mail.factoenlanube.com') ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($credentialsForm, 'port')
                        ->textInput(['type' => 'number'])
                        ->hint('Ejemplo: 993 para SSL/TLS, 143 para STARTTLS o sin cifrado') ?>
                </div>
                <?= Html::activeHiddenInput($credentialsForm, 'encryption', ['id' => 'credentials-encryption']) ?>
                <?= Html::hiddenInput('MailReceptionForm[validateCertificate]', $credentialsForm->validateCertificate ? 1 : 0, [
                    'id' => 'validate-certificate-value',
                ]) ?>
            </div>

            <?php ActiveForm::end(); ?>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" id="test-connection-btn">
                    Verificar conexión
                </button>
                <button type="button" class="btn btn-success" id="open-request-modal-btn" disabled>
                    Consultar facturas
                </button>
                <span class="connection-spinner hidden" id="connection-spinner" aria-hidden="true"></span>
                <div class="connection-status" id="connection-status"></div>
            </div>
        </div>
    </div>

    <div class="admin-card" style="margin-top: 2rem;">
        <div class="admin-card__header">
            <h3>Archivos generados</h3>
        </div>
        <div class="admin-card__body">
            <?php if (empty($archives)): ?>
                <p class="text-muted">Todavía no se han generado paquetes o reportes de facturas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm" id="archives-table">
                        <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Periodo</th>
                            <th>Tipo</th>
                            <th>Facturas</th>
                            <th>Correos</th>
                            <th>Generado</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($archives as $archive): ?>
                            <tr data-archive-id="<?= (int)$archive->id ?>">
                                <td><?= Html::encode($archive->file_name) ?></td>
                                <td><?= Html::encode($archive->period_start) ?> &rarr; <?= Html::encode($archive->period_end) ?></td>
                                <td><?= Html::encode($archive->getFileTypeLabel()) ?></td>
                                <td><?= Html::encode($archive->total_invoices) ?></td>
                                <td><?= Html::encode($archive->total_messages) ?></td>
                                <td><?= Html::encode($archive->created_at) ?></td>
                                <td>
                                    <?= Html::a('Descargar', ['admin/mail-archive-download', 'id' => $archive->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger js-delete-archive"
                                            data-id="<?= (int)$archive->id ?>">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Cuenta -->
<div class="modal fade" id="mail-account-modal" tabindex="-1" role="dialog" aria-labelledby="mail-account-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mail-account-modal-label">Guardar cuenta de correo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="mail-account-form">
                    <input type="hidden" name="id" id="mail-account-id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mail-account-label">Nombre de la cuenta</label>
                                <input type="text" class="form-control" id="mail-account-label" name="MailAccount[label]" required>
                                <small class="form-text text-muted">Ejemplo: Cuenta Hacienda Principal</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mail-account-username">Usuario</label>
                                <input type="text" class="form-control" id="mail-account-username" name="MailAccount[username]" required>
                                <small class="form-text text-muted">Ejemplo: usuario.imap@factoenlanube.com</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mail-account-email">Correo electrónico</label>
                                <input type="email" class="form-control" id="mail-account-email" name="MailAccount[email]" required>
                                <small class="form-text text-muted">Ejemplo: 3102934042@factoenlanube.com</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mail-account-password">Contraseña</label>
                                <input type="text" class="form-control" id="mail-account-password" name="MailAccount[password]" required>
                                <small class="form-text text-muted">Ejemplo: Facto2024+ (contraseña exacta del buzón)</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="mail-account-host">Servidor IMAP</label>
                                <input type="text" class="form-control" id="mail-account-host" name="MailAccount[host]" required>
                                <small class="form-text text-muted">Ejemplo: mail.factoenlanube.com</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="mail-account-port">Puerto</label>
                                <input type="number" class="form-control" id="mail-account-port" name="MailAccount[port]" required>
                                <small class="form-text text-muted">Ejemplo: 993 o 143 según el servidor</small>
                            </div>
                        </div>
                        <input type="hidden" id="mail-account-encryption" name="MailAccount[encryption]" value="ssl">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="mail-account-folder">Carpeta</label>
                                <input type="text" class="form-control" id="mail-account-folder" name="MailAccount[folder]" value="INBOX" required>
                                <small class="form-text text-muted">Ejemplo: INBOX, Sent, Facturas/Entrantes</small>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="validate_certificate" id="mail-account-validate" value="1">
                </form>
                <div class="alert alert-info mt-3 d-none" id="mail-account-status"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="save-mail-account-btn">Guardar cuenta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="invoice-request-modal" tabindex="-1" role="dialog" aria-labelledby="invoice-request-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoice-request-modal-label">Consultar facturas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php $modalForm = ActiveForm::begin([
                    'id' => 'invoice-request-form',
                    'enableClientValidation' => false,
                ]); ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $modalForm->field($requestForm, 'startDate')->input('date') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $modalForm->field($requestForm, 'endDate')->input('date') ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <?= $modalForm->field($requestForm, 'outputType')->radioList([
                            MailArchive::TYPE_PDF => 'PDF con resumen de facturas',
                            MailArchive::TYPE_ZIP => 'ZIP con facturas XML',
                            MailArchive::TYPE_RAR => 'WinRAR con facturas XML',
                        ]) ?>
                    </div>
                </div>

                <?php ActiveForm::end(); ?>

                <div class="alert alert-info" id="modal-status" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <span class="spinner-border text-primary me-auto" id="modal-spinner" role="status" style="display: none;"></span>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="submit-invoice-request">Procesar</button>
            </div>
        </div>
    </div>
</div>

