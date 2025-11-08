<?php

namespace app\models;

use yii\base\Model;

/**
 * Модель для загрузки и валидации входящих данных заявки (Data Transfer Object)
 */
class RequestInput extends Model
{
    public $user_id;
    public $amount;
    public $term;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'amount', 'term'], 'required'],
            [['user_id', 'amount', 'term'], 'integer', 'min' => 1],
        ];
    }
}