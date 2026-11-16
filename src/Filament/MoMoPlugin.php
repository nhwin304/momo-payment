<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Nhwin\MoMoPayment\Filament\Pages\MoMoSettingsPage;

/**
 * Đăng ký tại PanelProvider: ->plugin(MoMoPlugin::make()).
 * Permission của trang được kiểm tra cả khi truy cập trực tiếp bằng URL.
 */
final class MoMoPlugin implements Plugin
{
    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return 'momo-payment';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MoMoSettingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
