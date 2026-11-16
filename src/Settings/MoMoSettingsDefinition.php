<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Settings;

use Nhwin\Settings\Definitions\SettingsDefinition;

/**
 * Định nghĩa nhóm momo và khóa bí mật được mã hóa bằng APP_KEY của Laravel.
 * Không ghi plaintext access_key/secret_key xuống bảng settings.
 */
final class MoMoSettingsDefinition extends SettingsDefinition
{
    public static function group(): string
    {
        return 'momo';
    }

    public function defaults(): array
    {
        return [
            'enabled' => false,
            'mode' => 'sandbox',
            'sandbox' => ['partner_code' => '', 'access_key' => '', 'secret_key' => ''],
            'production' => ['partner_code' => '', 'access_key' => '', 'secret_key' => ''],
        ];
    }

    public function casts(): array
    {
        return ['enabled' => 'boolean', 'mode' => 'string'];
    }

    public function encrypted(): array
    {
        return [
            'sandbox.access_key',
            'sandbox.secret_key',
            'production.access_key',
            'production.secret_key',
        ];
    }
}
