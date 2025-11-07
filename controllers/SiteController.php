<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\components\HaciendaApiClient;
use app\components\HaciendaApiException;
use app\models\Button;
use app\models\Contact;
use app\models\HaciendaSearchForm;
use app\models\Slide;
use app\models\UsageLog;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $slides = Slide::find()->orderBy(['order' => SORT_ASC])->limit(5)->all();
        $buttons = Button::find()->orderBy(['order' => SORT_ASC])->limit(6)->all();
        $searchModel = new HaciendaSearchForm();
        
        return $this->render('index', [
            'slides' => $slides,
            'buttons' => $buttons,
            'searchModel' => $searchModel,
            'appConfig' => [
                'logUsageUrl' => \yii\helpers\Url::to(['site/log-usage']),
                'bccrEmail' => Yii::$app->params['bccrEmail'] ?? '',
                'bccrToken' => Yii::$app->params['bccrToken'] ?? '',
                'bccrNombre' => Yii::$app->params['bccrNombre'] ?? '',
            ],
        ]);
    }

    /**
     * Handle contact form submission
     */
    public function actionContact()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $model = new Contact();
        $model->created_at = time();
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return ['success' => true, 'message' => 'Contacto guardado exitosamente'];
        }
        
        return ['success' => false, 'errors' => $model->errors];
    }

    public function actionBuscarCedula()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new HaciendaSearchForm();
        $request = Yii::$app->request;

        if ($model->load($request->post()) && $model->validate()) {
            $client = new HaciendaApiClient();

            try {
                $data = $client->getContribuyente($model->getNormalizedIdentificacion());
                return [
                    'success' => true,
                    'data' => $data,
                ];
            } catch (HaciendaApiException $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'errors' => $model->getErrors(),
        ];
    }

    public function actionLogUsage()
    {
        $request = Yii::$app->request;
        if (!$request->isPost) {
            throw new BadRequestHttpException('Método no permitido.');
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        $payload = Json::decode($request->getRawBody(), true);
        if (!is_array($payload) || empty($payload['entries']) || !is_array($payload['entries'])) {
            throw new BadRequestHttpException('Datos inválidos.');
        }

        $entries = $payload['entries'];
        $allowedTypes = ['hacienda', 'hacienda_error', 'xml', 'xml_error', 'dolar', 'dolar_error'];
        $saved = 0;
        $errors = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || empty($entry['type']) || !in_array($entry['type'], $allowedTypes, true)) {
                $errors[] = $entry;
                continue;
            }

            $log = new UsageLog();
            $log->type = $entry['type'];
            $log->identifier = isset($entry['identifier']) && $entry['identifier'] !== ''
                ? (string) $entry['identifier']
                : null;
            if (isset($entry['metadata']) && is_array($entry['metadata'])) {
                $log->metadata = $entry['metadata'];
            }

            if ($log->save()) {
                $saved += 1;
            } else {
                $errors[] = $entry;
            }
        }

        return [
            'success' => true,
            'saved' => $saved,
            'errors' => $errors,
        ];
    }
}

