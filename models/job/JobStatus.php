<?php

namespace PalPalych\AiTranslator\Models\Job;

enum JobStatus: int
{
    case pending = 0;
    case processing = 1;
    case review = 2;
    case applied = 3;
    case failed = 4;
    case rejected = 5;

    public static function getOptions(): array
    {
        return [
            self::pending->value => 'Ожидает',
            self::processing->value => 'В процессе',
            self::review->value => 'На проверке',
            self::applied->value => 'Активна',
            self::failed->value => 'Ошибка',
            self::rejected->value => 'Отклонено',
        ];
    }
}
