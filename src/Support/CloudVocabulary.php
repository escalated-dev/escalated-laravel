<?php

namespace Escalated\Laravel\Support;

/**
 * Translation table between this package's ticket vocabulary and the one
 * cloud.escalated.dev speaks. Used by CloudDriver on the way out and by
 * the cloud webhook receiver on the way in. Values not listed pass
 * through unchanged.
 */
final class CloudVocabulary
{
    public const PRIORITY_TO_CLOUD = [
        'medium' => 'normal',
        'critical' => 'urgent',
    ];

    public const PRIORITY_FROM_CLOUD = [
        'normal' => 'medium',
    ];

    public const STATUS_TO_CLOUD = [
        'waiting_on_customer' => 'waiting',
        'waiting_on_agent' => 'waiting',
        'escalated' => 'open',
        'reopened' => 'open',
        'live' => 'open',
    ];

    public const STATUS_FROM_CLOUD = [
        'waiting' => 'waiting_on_customer',
        'snoozed' => 'open',
    ];

    public static function priorityToCloud(string $value): string
    {
        return self::PRIORITY_TO_CLOUD[$value] ?? $value;
    }

    public static function priorityFromCloud(string $value): string
    {
        return self::PRIORITY_FROM_CLOUD[$value] ?? $value;
    }

    public static function statusToCloud(string $value): string
    {
        return self::STATUS_TO_CLOUD[$value] ?? $value;
    }

    public static function statusFromCloud(string $value): string
    {
        return self::STATUS_FROM_CLOUD[$value] ?? $value;
    }
}
