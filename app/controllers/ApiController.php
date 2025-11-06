<?php

namespace app\controllers;

use Yii;
use yii\rest\Controller;
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

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        return parent::beforeAction($action);
    }

    /**
     * Эндпоинт: POST /requests
     * Подача новой заявки на займ.
     */
    public function actionRequests()
    {
        return ['result' => false];

        $model = new LoanRequest();

        // Загружаем данные из POST-запроса (без имени модели)
        $model->load(Yii::$app->request->post(), '');

        if ($model->validate()) {
            // Статус 'pending' устанавливается по умолчанию (см. rules)
            if ($model->save(false)) { // Сохраняем без повторной валидации
                Yii::$app->response->statusCode = 201; // Created
                return ['result' => true, 'id' => $model->id];
            }
        }

        // Если валидация не пройдена
        Yii::$app->response->statusCode = 400; // Bad Request
        return ['result' => false];
    }

    /**
     * Эндпоинт: GET /processor
     * Запуск обработки заявок.
     * @param int $delay Время задержки в секундах.
     */
public function actionProcessor($delay = 5)
{
    return ['result' => false];

    $delay = (int)$delay;
    if ($delay < 0) {
        $delay = 0;
    }

    // Обрабатываем заявки в цикле, пока есть "pending"
    while (true) {
        $transaction = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);

        try {
            // 1. Находим и блокируем ОДНУ заявку
            // "FOR UPDATE SKIP LOCKED" — ключ к параллельной обработке.
            // Несколько запущенных /processor не схватят одну и ту же запись.
            $request = LoanRequest::findBySql(
                "SELECT * FROM loan_requests WHERE status = :status ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED",
                [':status' => LoanRequest::STATUS_PENDING]
            )->one();

            // Если заявок не найдено, выходим из цикла
            if ($request === null) {
                $transaction->commit();
                break;
            }

            // 2. Эмулируем долгую обработку
            sleep($delay);

            // 3. Принимаем решение (10% шанс аппрува)
            $decision = LoanRequest::STATUS_DECLINED;
            $approveChance = rand(1, 100);

            if ($approveChance <= 10) {
                // 4. Проверяем доп. условие: нет ли уже одобренных у этого юзера
                // Эта проверка критична, т.к. заявки могут обрабатываться параллельно
                $hasApproved = LoanRequest::find()
                    ->where([
                                'user_id' => $request->user_id,
                                'status' => LoanRequest::STATUS_APPROVED
                            ])
                    ->exists();

                if (!$hasApproved) {
                    $decision = LoanRequest::STATUS_APPROVED;
                }
            }

            // 5. Сохраняем решение
            $request->status = $decision;
            $request->save(false); // Валидация не нужна

            // 6. Коммитим транзакцию, освобождая лок
            $transaction->commit();

        } catch (\Exception $e) {
            // В случае ошибки откатываем транзакцию
            $transaction->rollBack();
            Yii::error($e->getMessage(), 'processor');
            // Прерываем выполнение, чтобы не уйти в бесконечный цикл
            return ['result' => false, 'error' => 'Processing failed'];
        }
    }

    return ['result' => true];
}
}