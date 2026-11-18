<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Settings;

use Illuminate\Validation\ValidationException;

/**
 * Kiểm tra dữ liệu lưu từ Livewire trên máy chủ, không chỉ dựa vào required() của form.
 * Giá trị secret cũ được phép giữ nguyên khi người quản trị để trống ô nhập.
 */
final class MoMoSettingsValidator
{
    /**
     * @param array<string, mixed> $data Trạng thái form đã được Filament validate
     * @param array<string, mixed> $current Cấu hình đang lưu trong scope global
     * @return array<string, mixed>
     */
    public function validate(array $data, array $current): array
    {
        $mode = $data['mode'] ?? null;
        if (! is_string($mode) || ! in_array($mode, ['sandbox', 'production'], true)) {
            throw ValidationException::withMessages([
                'data.mode' => 'Vui lòng chọn một môi trường MoMo hợp lệ.',
            ]);
        }

        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            throw ValidationException::withMessages([
                'data.enabled' => 'Trạng thái kích hoạt MoMo không hợp lệ.',
            ]);
        }

        $data['enabled'] = $enabled;
        if (! $enabled) {
            return $data;
        }

        foreach (['partner_code', 'access_key', 'secret_key'] as $field) {
            $path = "{$mode}.{$field}";

            // Mật khẩu không được hydrate lại lên trình duyệt; kiểm tra bản lưu ở server.
            if ($this->hasText(data_get($data, $path)) || $this->hasText(data_get($current, $path))) {
                continue;
            }

            throw ValidationException::withMessages([
                "data.{$path}" => 'Phải cung cấp đủ Partner Code, Access Key và Secret Key trước khi bật MoMo.',
            ]);
        }

        return $data;
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
