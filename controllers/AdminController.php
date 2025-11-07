<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\filters\AccessControl;
use app\models\Slide;
use app\models\Button;
use app\models\UsageLog;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Json;

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
                        'actions' => ['index', 'slides', 'buttons', 'create-slide', 'update-slide', 'delete-slide', 'create-button', 'update-button', 'delete-button', 'usage-export'],
                        'roles' => ['?'], // Allow guest access for now
                    ],
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
}

