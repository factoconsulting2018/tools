<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\InvalidArgumentException;
use yii\helpers\Console;
use app\models\MailAccount;
use app\models\forms\MailReceptionForm;
use app\components\InvoiceMailboxService;

/**
 * Comandos de consola relacionados con conexiones de correo.
 */
class MailController extends Controller
{
    /**
     * Prueba la conexión IMAP utilizando un ID de cuenta guardada.
     *
     * Ejemplo:
     * php yii mail/test-connection --account=1
     *
     * @param int $account identificador de la cuenta en la tabla mail_account.
     * @return int
     */
    public function actionTestConnection(int $account)
    {
        $model = MailAccount::findOne($account);
        if (!$model) {
            $this->stderr("La cuenta con ID {$account} no existe.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $form = new MailReceptionForm();
        $form->username = $model->username ?: $model->email;
        $form->email = $model->email;
        $form->password = $model->password;
        $form->host = $model->host;
        $form->port = (int) $model->port;
        $form->encryption = $model->encryption ?: 'ssl';
        $form->folder = $model->folder ?: 'INBOX';
        $form->validateCertificate = (bool) $model->validate_certificate;

        $service = Yii::createObject(InvoiceMailboxService::class);

        try {
            $service->testConnection($form);
            $this->stdout("Conexión exitosa para la cuenta {$model->email}.\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("Error al conectar: {$e->getMessage()}\n", Console::FG_RED);
            Yii::error([
                'action' => 'mail/test-connection',
                'accountId' => $model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

