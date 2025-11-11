<?php

namespace app\controllers;

use Yii;
use app\models\Slide;
use app\models\Button;
use app\models\UsageLog;
use app\models\MailArchive;
use app\models\MailAccount;
use app\models\forms\MailReceptionForm;
use app\models\forms\MailReceptionRequestForm;
use app\components\InvoiceMailboxService;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class AdminController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'index',
                            'slides',
                            'buttons',
                            'create-slide',
                            'update-slide',
                            'delete-slide',
                            'create-button',
                            'update-button',
                            'delete-button',
                            'usage-export',
                            'receive-invoices',
                            'mail-connection-test',
                            'mail-process',
                            'mail-archive-download',
                            'mail-archive-delete',
                            'mail-account-list',
                            'mail-account-save',
                            'mail-account-delete',
                            'db-status',
                        ],
                        'roles' => ['?'], // Allow guest access for now
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'mail-archive-delete' => ['post'],
                    'mail-account-delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Admin dashboard
     */
    public function actionIndex()
    {
        $slidesCount = Slide::find()->count();
        $buttonsCount = Button::find()->count();

        $usageTotals = (new Query())
            ->select([
                'type',
                'total' => new Expression('COUNT(*)')
            ])
            ->from(UsageLog::tableName())
            ->groupBy(['type'])
            ->indexBy('type')
            ->all();

        $allowedTypes = ['hacienda', 'hacienda_error', 'xml', 'xml_error', 'dolar', 'dolar_error'];
        $usageSummary = [];
        $totalConsultas = 0;
        foreach ($allowedTypes as $type) {
            $count = isset($usageTotals[$type]) ? (int) $usageTotals[$type]['total'] : 0;
            $usageSummary[$type] = $count;
            $totalConsultas += $count;
        }

        $recentHacienda = UsageLog::find()
            ->where(['type' => ['hacienda', 'hacienda_error']])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(10)
            ->asArray()
            ->all();

        $chartStart = (new \DateTimeImmutable('-14 days'))->setTime(0, 0);
        $chartRows = (new Query())
            ->select([
                'date' => new Expression('DATE([[created_at]])'),
                'type',
                'total' => new Expression('COUNT(*)')
            ])
            ->from(UsageLog::tableName())
            ->where(['>=', 'created_at', $chartStart->format('Y-m-d H:i:s')])
            ->andWhere(['type' => ['hacienda', 'xml', 'dolar']])
            ->groupBy(['date', 'type'])
            ->orderBy(['date' => SORT_ASC])
            ->all();

        $chartLabels = [];
        $chartSeries = [
            'hacienda' => [],
            'xml' => [],
            'dolar' => [],
        ];

        foreach ($chartRows as $row) {
            $label = $row['date'];
            if (!in_array($label, $chartLabels, true)) {
                $chartLabels[] = $label;
            }
        }

        sort($chartLabels);

        foreach ($chartSeries as $type => &$series) {
            foreach ($chartLabels as $label) {
                $series[] = 0;
            }
        }
        unset($series);

        $labelIndex = array_flip($chartLabels);
        foreach ($chartRows as $row) {
            $type = $row['type'];
            $date = $row['date'];
            if (isset($chartSeries[$type], $labelIndex[$date])) {
                $chartSeries[$type][$labelIndex[$date]] = (int) $row['total'];
            }
        }

        $monthlyRows = (new Query())
            ->select([
                'period' => new Expression("DATE_FORMAT([[created_at]], '%Y-%m')"),
                'type',
                'total' => new Expression('COUNT(*)')
            ])
            ->from(UsageLog::tableName())
            ->where(['type' => ['hacienda', 'xml', 'dolar']])
            ->groupBy(['period', 'type'])
            ->orderBy(['period' => SORT_DESC])
            ->limit(36)
            ->all();

        $yearlyRows = (new Query())
            ->select([
                'period' => new Expression("DATE_FORMAT([[created_at]], '%Y')"),
                'type',
                'total' => new Expression('COUNT(*)')
            ])
            ->from(UsageLog::tableName())
            ->where(['type' => ['hacienda', 'xml', 'dolar']])
            ->groupBy(['period', 'type'])
            ->orderBy(['period' => SORT_DESC])
            ->limit(10)
            ->all();

        return $this->render('index', [
            'slidesCount' => $slidesCount,
            'buttonsCount' => $buttonsCount,
            'totalConsultas' => $totalConsultas,
            'usageSummary' => $usageSummary,
            'recentHacienda' => $recentHacienda,
            'chartLabels' => $chartLabels,
            'chartSeries' => $chartSeries,
            'monthlyRows' => $monthlyRows,
            'yearlyRows' => $yearlyRows,
        ]);
    }

    /**
     * Manage slides
     */
    public function actionSlides()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Slide::find()->orderBy(['order' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('slides', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Create slide
     */
    public function actionCreateSlide()
    {
        // Check limit of 5 slides
        $slidesCount = Slide::find()->count();
        if ($slidesCount >= 5) {
            Yii::$app->session->setFlash('error', 'Ya has alcanzado el límite máximo de 5 slides. Elimina uno antes de crear otro.');
            return $this->redirect(['slides']);
        }

        $model = new Slide();
        $maxOrder = Slide::find()->max('`order`');
        $model->order = $maxOrder !== null ? $maxOrder + 1 : 0;
        $model->scenario = 'create';

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate()) {
                if ($model->upload() && $model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Slide creado exitosamente.');
                    return $this->redirect(['slides']);
                }
            }
        }

        return $this->render('slide-form', [
            'model' => $model,
        ]);
    }

    /**
     * Update slide
     */
    public function actionUpdateSlide($id)
    {
        $model = $this->findSlide($id);
        $model->scenario = 'update';

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate()) {
                if ($model->imageFile) {
                    $model->upload();
                }
                if ($model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Slide actualizado exitosamente.');
                    return $this->redirect(['slides']);
                }
            }
        }

        return $this->render('slide-form', [
            'model' => $model,
        ]);
    }

    /**
     * Delete slide
     */
    public function actionDeleteSlide($id)
    {
        $model = $this->findSlide($id);
        
        // Delete image file
        if ($model->image_path && file_exists(Yii::getAlias('@webroot') . $model->image_path)) {
            unlink(Yii::getAlias('@webroot') . $model->image_path);
        }
        
        $model->delete();
        Yii::$app->session->setFlash('success', 'Slide eliminado exitosamente.');
        return $this->redirect(['slides']);
    }

    /**
     * Manage buttons
     */
    public function actionButtons()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Button::find()->orderBy(['order' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('buttons', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Create button
     */
    public function actionCreateButton()
    {
        // Check limit of 6 buttons
        $buttonsCount = Button::find()->count();
        if ($buttonsCount >= 6) {
            Yii::$app->session->setFlash('error', 'Ya has alcanzado el límite máximo de 6 botones. Elimina uno antes de crear otro.');
            return $this->redirect(['buttons']);
        }

        $model = new Button();
        $maxOrder = Button::find()->max('`order`');
        $model->order = $maxOrder !== null ? $maxOrder + 1 : 0;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Botón creado exitosamente.');
            return $this->redirect(['buttons']);
        }

        return $this->render('button-form', [
            'model' => $model,
        ]);
    }

    /**
     * Update button
     */
    public function actionUpdateButton($id)
    {
        $model = $this->findButton($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Botón actualizado exitosamente.');
            return $this->redirect(['buttons']);
        }

        return $this->render('button-form', [
            'model' => $model,
        ]);
    }

    /**
     * Delete button
     */
    public function actionDeleteButton($id)
    {
        $this->findButton($id)->delete();
        Yii::$app->session->setFlash('success', 'Botón eliminado exitosamente.');
        return $this->redirect(['buttons']);
    }

    /**
     * Find slide model
     */
    protected function findSlide($id)
    {
        if (($model = Slide::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('El slide solicitado no existe.');
    }

    /**
     * Find button model
     */
    protected function findButton($id)
    {
        if (($model = Button::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('El botón solicitado no existe.');
    }

    public function actionUsageExport()
    {
        $logs = UsageLog::find()->orderBy(['created_at' => SORT_DESC])->asArray()->all();

        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, ['ID', 'Tipo', 'Identificador', 'Metadata', 'Fecha']);
        foreach ($logs as $log) {
            fputcsv($fp, [
                $log['id'],
                $log['type'],
                $log['identifier'],
                $log['metadata'] ? Json::encode($log['metadata']) : '',
                $log['created_at'],
            ]);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        $filename = 'uso_consultas_' . date('Ymd_His') . '.csv';

        return Yii::$app->response->sendContentAsFile($content, $filename, [
            'mimeType' => 'text/csv',
        ]);
    }

    /**
     * Pantalla para recepcionar facturas desde correo.
     */
    public function actionReceiveInvoices()
    {
        $credentialsForm = new MailReceptionForm();
        $requestForm = new MailReceptionRequestForm();

        $mailAccounts = MailAccount::find()
            ->orderBy(['label' => SORT_ASC, 'email' => SORT_ASC])
            ->asArray()
            ->all();

        if ($mailAccounts) {
            $first = $mailAccounts[0];
            $credentialsForm->setAttributes([
                'username' => $first['username'],
                'email' => $first['email'],
                'password' => $first['password'],
                'host' => $first['host'],
                'port' => $first['port'],
                'encryption' => $first['encryption'],
                'folder' => $first['folder'],
                'validateCertificate' => (bool) $first['validate_certificate'],
            ], false);
        }

        $archives = MailArchive::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();

        return $this->render('receive-invoices', [
            'credentialsForm' => $credentialsForm,
            'requestForm' => $requestForm,
            'archives' => $archives,
            'mailAccounts' => $mailAccounts,
        ]);
    }

    /**
     * Endpoint para validar credenciales de correo.
     */
    public function actionMailConnectionTest()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $form = new MailReceptionForm();
        $data = Yii::$app->request->getBodyParams();

        if (!$form->load($data, '') || !$form->validate()) {
            return [
                'success' => false,
                'message' => 'Los datos del formulario no son válidos.',
                'errors' => $form->getErrors(),
            ];
        }

        try {
            $service = new InvoiceMailboxService();
            $service->testConnection($form);

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Procesa el buzón y genera los archivos solicitados.
     */
    public function actionMailProcess()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $credentialsForm = new MailReceptionForm();
        $requestForm = new MailReceptionRequestForm();
        $data = Yii::$app->request->getBodyParams();

        $credentialsValid = $credentialsForm->load($data['credentials'] ?? [], '') && $credentialsForm->validate();
        $requestValid = $requestForm->load($data['request'] ?? [], '') && $requestForm->validate();

        if (!$credentialsValid || !$requestValid) {
            return [
                'success' => false,
                'message' => 'Revisa los datos proporcionados.',
                'errors' => array_filter([
                    'credentials' => $credentialsForm->getErrors(),
                    'request' => $requestForm->getErrors(),
                ]),
            ];
        }

        try {
            $service = new InvoiceMailboxService();
            $result = $service->processMailbox($credentialsForm, $requestForm);

            if (!$result['archive']) {
                return [
                    'success' => true,
                    'empty' => true,
                    'summary' => $result['summary'],
                    'warnings' => $result['warnings'],
                ];
            }

            /** @var MailArchive $archive */
            $archive = $result['archive'];

            return [
                'success' => true,
                'archiveId' => $archive->id,
                'downloadUrl' => Url::to(['admin/mail-archive-download', 'id' => $archive->id], true),
                'summary' => $result['summary'],
                'warnings' => $result['warnings'],
                'archive' => [
                    'fileName' => $archive->file_name,
                    'fileType' => $archive->getFileTypeLabel(),
                    'createdAt' => $archive->created_at,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Permite descargar un archivo generado.
     *
     * @param int $id
     * @return Response
     */
    public function actionMailArchiveDownload($id)
    {
        $archive = $this->findMailArchive($id);
        $path = $archive->getAbsolutePath();

        if (!$path || !file_exists($path)) {
            throw new NotFoundHttpException('El archivo solicitado no se encuentra disponible.');
        }

        return Yii::$app->response->sendFile($path, $archive->file_name);
    }

    /**
     * Elimina el archivo generado.
     *
     * @param int $id
     * @return Response
     */
    public function actionMailArchiveDelete($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $archive = $this->findMailArchive($id);
        $path = $archive->getAbsolutePath();

        if ($path && file_exists($path)) {
            @unlink($path);
        }

        $archive->status = MailArchive::STATUS_DELETED;
        $archive->save(false, ['status', 'updated_at']);
        $archive->delete();

        return ['success' => true];
    }

    public function actionMailAccountList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $accounts = MailAccount::find()
            ->orderBy(['label' => SORT_ASC, 'email' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'success' => true,
            'accounts' => array_map(static function (array $account) {
                $account['validate_certificate'] = (bool) $account['validate_certificate'];
                return $account;
            }, $accounts),
        ];
    }

    public function actionMailAccountSave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $data = Yii::$app->request->getBodyParams();

        $id = $data['id'] ?? null;
        if ($id) {
            $model = MailAccount::findOne($id);
            if (!$model) {
                return ['success' => false, 'message' => 'La cuenta seleccionada no existe.'];
            }
        } else {
            $model = new MailAccount();
        }

        Yii::info(['rawPayload' => $data], 'mailAccountSave');

        if (isset($data['MailAccount']) && is_array($data['MailAccount'])) {
            $data = $data['MailAccount'];
        }

        Yii::info(['normalizedPayload' => $data], 'mailAccountSave');

        $model->label = trim((string)($data['label'] ?? ''));
        $model->username = trim((string)($data['username'] ?? ''));
        $model->email = trim((string)($data['email'] ?? ''));
        $model->password = (string)($data['password'] ?? '');
        $model->host = trim((string)($data['host'] ?? ''));
        $model->port = (int)($data['port'] ?? 993);
        $model->encryption = (string)($data['encryption'] ?? 'ssl');
        $model->folder = trim((string)($data['folder'] ?? 'INBOX'));
        $model->validate_certificate = !empty($data['validate_certificate']);

        $now = date('Y-m-d H:i:s');
        if ($model->isNewRecord) {
            $model->created_at = $now;
        }
        $model->updated_at = $now;

        try {
            if (!$model->save(false)) {
                return ['success' => false, 'message' => 'No fue posible guardar la cuenta.'];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar la cuenta: ' . $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'account' => [
                'id' => $model->id,
                'label' => $model->label,
                'username' => $model->username,
                'email' => $model->email,
                'password' => $model->password,
                'host' => $model->host,
                'port' => $model->port,
                'encryption' => $model->encryption,
                'validate_certificate' => (bool) $model->validate_certificate,
                'folder' => $model->folder,
            ],
        ];
    }

    public function actionMailAccountDelete()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = Yii::$app->request->post('id');

        if (!$id) {
            return ['success' => false, 'message' => 'No se indicó la cuenta a eliminar.'];
        }

        $account = MailAccount::findOne($id);
        if (!$account) {
            return ['success' => false, 'message' => 'La cuenta seleccionada no existe.'];
        }

        $account->delete();

        return ['success' => true];
    }

    /**
     * Busca un archivo almacenado.
     *
     * @param int $id
     * @return MailArchive
     */
    protected function findMailArchive($id)
    {
        if (($model = MailArchive::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('El recurso solicitado no existe.');
    }

    public function actionDbStatus()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $start = microtime(true);
            $result = Yii::$app->db->createCommand('SELECT 1')->queryScalar();
            $duration = (int) round((microtime(true) - $start) * 1000);

            return [
                'success' => (int) $result === 1,
                'durationMs' => $duration,
            ];
        } catch (\Throwable $e) {
            Yii::error('DB status check failed: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}

