<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property int $term
 * @property string $status
 */
class Request extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_DECLINED = 'declined';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'request';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // Обязательные поля
            [['user_id', 'amount', 'term'], 'required'],

            // Типы полей
            [['user_id', 'amount', 'term'], 'integer'],

            // Позитивные значения
            [['amount', 'term'], 'integer', 'min' => 1],

            // Статус по умолчанию
            ['status', 'default', 'value' => self::STATUS_PENDING],

            // Допустимые значения статуса
            [
                'status',
                'in',
                'range' => [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_APPROVED, self::STATUS_DECLINED]
            ],

            // Кастомная валидация: "у пользователя не должно быть одобренных заявок"
            ['user_id', 'validateNoApprovedLoans'],
        ];
    }

    /**
     * Кастомный валидатор для проверки наличия одобренных заявок у пользователя.
     * @param string $attribute
     */
    public function validateNoApprovedLoans($attribute)
    {
        $hasApproved = static::find()
            ->where(['user_id' => $this->user_id, 'status' => self::STATUS_APPROVED])
            ->exists();

        if ($hasApproved) {
            $this->addError($attribute, 'User already has an approved loan.');
        }
    }
}
