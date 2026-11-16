<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Services;

use Nhwin\MoMoPayment\Exceptions\MoMoPaymentException;
use Nhwin\Settings\Contracts\SettingsManagerContract;

/**
 * Đọc thiết lập lúc sử dụng thay vì giữ cấu hình trong singleton/worker dài hạn.
 * Scope global bảo đảm IPN và trang quản trị nhìn thấy cùng một cấu hình.
 */
final class MoMoConfiguration
{
    public function __construct(private readonly SettingsManagerContract $settings) {}

    public function enabled(): bool
    {
        return $this->settings->forScope('global')->get('momo.enabled', false) === true;
    }

    public function mode(): string
    {
        $mode = $this->settings->forScope('global')->get('momo.mode', 'sandbox');

        if (! in_array($mode, ['sandbox', 'production'], true)) {
            throw new MoMoPaymentException('Chế độ MoMo không hợp lệ.');
        }

        return $mode;
    }

    /** @return array{partnerCode: string, accessKey: string, secretKey: string} */
    public function credentials(): array
    {
        return $this->credentialsFor();
    }

    /**
     * Truy xuất credential đúng môi trường đã lưu cùng đơn hàng.
     * Cho phép xác minh IPN khi cổng đã tắt, nhưng không cho phép tạo giao dịch mới.
     *
     * @return array{partnerCode: string, accessKey: string, secretKey: string}
     */
    public function credentialsFor(?string $mode = null, bool $requireEnabled = true): array
    {
        if ($requireEnabled && ! $this->enabled()) {
            throw new MoMoPaymentException('MoMo đang tắt trong phần cấu hình.');
        }

        $mode ??= $this->mode();
        if (! in_array($mode, ['sandbox', 'production'], true)) {
            throw new MoMoPaymentException('Chế độ MoMo không hợp lệ.');
        }

        $values = [];
        foreach (['partner_code' => 'partnerCode', 'access_key' => 'accessKey', 'secret_key' => 'secretKey'] as $key => $alias) {
            $value = $this->settings->forScope('global')->get("momo.{$mode}.{$key}", '');
            if (! is_string($value) || trim($value) === '') {
                throw new MoMoPaymentException("Thiếu cấu hình MoMo cho môi trường {$mode}.");
            }
            $values[$alias] = $value;
        }

        return $values;
    }

    public function endpoint(string $operation, ?string $mode = null): string
    {
        if (! in_array($operation, ['create', 'query'], true)) {
            throw new MoMoPaymentException('Thao tác MoMo không hợp lệ.');
        }

        $mode ??= $this->mode();
        if (! in_array($mode, ['sandbox', 'production'], true)) {
            throw new MoMoPaymentException('Chế độ MoMo không hợp lệ.');
        }
        $url = config("momo-payment.endpoints.{$mode}.{$operation}");
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new MoMoPaymentException('Endpoint MoMo phải sử dụng HTTPS.');
        }

        // Chống gửi nhầm khóa Sandbox sang Production hoặc tới endpoint giả mạo.
        $host = parse_url($url, PHP_URL_HOST);
        $expectedHost = $mode === 'sandbox' ? 'test-payment.momo.vn' : 'payment.momo.vn';
        if (
            $host !== $expectedHost ||
            parse_url($url, PHP_URL_PATH) !== "/v2/gateway/api/{$operation}" ||
            parse_url($url, PHP_URL_PORT) !== null ||
            parse_url($url, PHP_URL_USER) !== null ||
            parse_url($url, PHP_URL_PASS) !== null ||
            parse_url($url, PHP_URL_QUERY) !== null ||
            parse_url($url, PHP_URL_FRAGMENT) !== null
        ) {
            throw new MoMoPaymentException('Endpoint MoMo không đúng môi trường hoặc đường dẫn chính thức.');
        }

        return $url;
    }
}
