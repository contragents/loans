<?php

namespace app\controllers;

use app\models\Request;
use app\models\RequestInput;
use TheSeer\Tokenizer\Exception;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\db\Transaction;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

//use app\models\LoanRequest;

class ApiController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Настройка ответа в формате JSON
        $behaviors['contentNegotiator'] = [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        return $behaviors;
    }

    public function actionForbidden()
    {
        Yii::$app->response->statusCode = 403;

        return ['result' => 'Forbidden'];
    }

    /**
     * Эндпоинт: POST /requests
     * Подача новой заявки на займ.
     * @return array ['result' => true, 'id' => <new_request_id>] если заявка пользователя принятв, ['result' => false] в случае ошибки
     */
    public function actionRequests(): array
    {
        try {
            $postData = json_decode(Yii::$app->request->getRawBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Got JSON encoding error: ' . json_last_error());
            }

            $modelInput = new RequestInput();
            $modelInput->load($postData, '');

            if ($modelInput->validate()) {
                $model = new Request();

                // Копируем только безопасные, уже валидированные атрибуты из RequestInput
                $model->user_id = $modelInput->user_id;
                $model->amount = $modelInput->amount;
                $model->term = $modelInput->term;
                // Статус 'pending' устанавливается по умолчанию (см. Request::rules)

                // Сохраняем с повторной валидацией для проверки, что нет одобренных заявок данному user_id
                if ($model->save(true)) {
                    Yii::$app->response->statusCode = 201; // Created

                    return ['result' => true, 'id' => $model->id];
                } else {
                    throw new \Exception('Model validation errors: ' . print_r($model->getErrors(), true));
                }
            } else {
                throw new \Exception('Input validation errors: ' . print_r($modelInput->getErrors(), true));
            }
        } catch (\Throwable $e) {
            // Отдаем текст исключения и ошибки валидации, если DEV
            if (YII_ENV_DEV) {
                Yii::$app->response->statusCode = 500;

                return ['result' => false, 'errors' => $e->__toString(), 'env' => ['YII_ENV_PROD' => YII_ENV_PROD, 'YII_ENV' => YII_ENV, '$_ENV[YII_ENV]' => $_ENV['YII_ENV']]];
            }
        }

        // Если валидация не пройдена или возникли исключения, возвращаем HTTP-статус Bad Request
        Yii::$app->response->statusCode = 400;

        return ['result' => false];
    }

    /**
     * Эндпоинт: GET /processor
     * Запуск обработки заявок.
     * @param int $delay
     * @return array ['result' => true] если обработка прошла без ошибок, ['result' => false] в случае ошибки
     */
    public function actionProcessor($delay = 5): array
    {
        $delayInt = (int)$delay;
        if ($delayInt < 0) {
            $delayInt = 0;
        }

        // Обрабатываем заявки в цикле, пока есть "pending"
        while (true) {
            try {
                // 1. Находим ОДНУ заявку, которая принадлежит пользователю НЕ в статусе approved или processing
                $subQuery = (new Query())
                    ->select(['user_id'])
                    ->from(Request::tableName())
                    ->where(['status' => [Request::STATUS_APPROVED, Request::STATUS_PROCESSING]]);

                $requestModel = Request::find()
                    ->where(
                        [
                            'and',
                            ['status' => Request::STATUS_PENDING],
                            [
                                'NOT IN',
                                'user_id',
                                $subQuery
                            ]
                        ]
                    )
                    ->one();

                // Если заявок не найдено, выходим из цикла
                if ($requestModel === null) {
                    break;
                }

                // 2. Атомарно блокируем найденную заявку, проверяя, что данный пользователь еще не ушел в статус approved или processing
                $lockedCount = Yii::$app->db->createCommand()
                    ->update(
                        Request::tableName(),
                        ['status' => Request::STATUS_PROCESSING],
                        [
                            'and',
                            ['id' => $requestModel->id],
                            [
                                'NOT IN',
                                'user_id',
                                $subQuery
                            ],
                        ]
                    )
                    ->execute();

                // Если заявка не залочена (параллельный процесс успел залочить заявку данного юзера), продолжаем цикл
                if (!$lockedCount) {
                    continue;
                }

                // 3. Эмулируем долгую обработку
                sleep($delayInt);

                // 3. Принимаем решение (10% шанс аппрува)
                $decision = rand(1, 100) > 10 ? Request::STATUS_DECLINED : Request::STATUS_APPROVED;

                // 4. Сохраняем решение
                $requestModel->status = $decision;
                $requestModel->save(false); // Валидация не нужна - мы исключили конкурирующие блокировки user_id
            } catch (\Exception $e) {
                // Прерываем выполнение, чтобы не уйти в бесконечный цикл
                return ['result' => false]
                    + (YII_ENV_DEV
                        ? ['errors' => $e->__toString()]
                        : []);
            }
        }

        return ['result' => true];
    }
}