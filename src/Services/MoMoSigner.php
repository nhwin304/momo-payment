<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Services;

use InvalidArgumentException;

/**
 * HMAC SHA-256 v2: thứ tự và tên trường PHẢI đúng theo tài liệu MoMo.
 * Không ký dựa trên toàn bộ request hoặc thứ tự mảng được truyền vào.
 */
final class MoMoSigner
{
    private const CREATE_FIELDS = [
        'accessKey', 'amount', 'extraData', 'ipnUrl', 'orderId',
        'orderInfo', 'partnerCode', 'redirectUrl', 'requestId', 'requestType',
    ];

    private const QUERY_FIELDS = ['accessKey', 'orderId', 'partnerCode', 'requestId'];

    private const NOTIFICATION_FIELDS = [
        'accessKey', 'amount', 'extraData', 'message', 'orderId',
        'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId',
        'responseTime', 'resultCode', 'transId',
    ];

    public static function signCreate(array $payload, string $accessKey, string $secretKey): string
    {
        return self::sign($payload, self::CREATE_FIELDS, $accessKey, $secretKey);
    }

    public static function signQuery(array $payload, string $accessKey, string $secretKey): string
    {
        return self::sign($payload, self::QUERY_FIELDS, $accessKey, $secretKey);
    }

    /** Chỉ kiểm tra tính toàn vẹn chữ ký; resultCode = 0 phải kiểm tra riêng. */
    public static function verifyNotification(array $notification, string $accessKey, string $secretKey): bool
    {
        $received = $notification['signature'] ?? null;
        if (! is_string($received) || ! preg_match('/^[a-f0-9]{64}$/i', $received)) {
            return false;
        }

        try {
            $expected = self::sign($notification, self::NOTIFICATION_FIELDS, $accessKey, $secretKey);
        } catch (InvalidArgumentException) {
            return false;
        }

        return hash_equals($expected, strtolower($received));
    }

    /**
     * Tiện ích phục vụ kiểm thử dữ liệu IPN mẫu, không phải khóa ký độc lập.
     * @param list<string> $fields
     */
    private static function sign(array $payload, array $fields, string $accessKey, string $secretKey): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = $field === 'accessKey' ? $accessKey : ($payload[$field] ?? null);
            if ($value === null || (! is_scalar($value))) {
                throw new InvalidArgumentException("Thiếu trường ký MoMo: {$field}");
            }
            $parts[] = $field.'='.(string) $value;
        }

        return hash_hmac('sha256', implode('&', $parts), $secretKey);
    }
}
