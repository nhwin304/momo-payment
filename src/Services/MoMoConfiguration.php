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
        if (! $this->enabled()) {
            throw new MoMoPaymentException('MoMo đang tắt trong phần cấu hình.');
        }

        $mode = $this->mode();
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

    public function endpoint(string $operation): string
    {
        if (! in_array($operation, ['create', 'query'], true)) {
            throw new MoMoPaymentException('Thao tác MoMo không hợp lệ.');
        }

        $url = config("momo-payment.endpoints.{$this->mode()}.{$operation}");
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new MoMoPaymentException('Endpoint MoMo phải sử dụng HTTPS.');
        }

        // Chống SSRF do cấu hình endpoint bị thay đổi trái phép.
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($host, ['test-payment.momo.vn', 'payment.momo.vn'], true)) {
            throw new MoMoPaymentException('Endpoint MoMo không nằm trong danh sách cho phép.');
        }

        return $url;
    }
}
