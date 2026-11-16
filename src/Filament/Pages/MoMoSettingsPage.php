<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Nhwin\MoMoPayment\Settings\MoMoSettingsDefinition;
use Nhwin\Settings\Abstracts\AbstractPageSettings;

/**
 * Trang Filament 5 lưu trong nhóm momo của nhwin/settings.
 * Khóa bí mật không được fill ra form nhờ SettingsDefinition::encrypted().
 */
final class MoMoSettingsPage extends AbstractPageSettings
{
    protected static ?string $title = 'Cấu hình thanh toán MoMo';

    protected static ?string $navigationLabel = 'MoMo Payment';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'momo-payment';

    protected string $view = 'momo-payment::filament.pages.momo-settings';

    protected static function canAccessSettingsPage(): bool
    {
        // Shield có thể cấp quyền này hoặc cho super admin thông qua Gate::before().
        return auth()->user()?->can((string) config('momo-payment.permission', 'manage_momo_payment')) ?? false;
    }

    protected function settingName(): string
    {
        return 'momo';
    }

    public function getDefaultData(): array
    {
        return (new MoMoSettingsDefinition)->defaults();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trạng thái')
                    ->description('Bật hoặc tắt MoMo và chọn môi trường đang hoạt động.')
                    ->schema([
                        Toggle::make('enabled')->label('Enable')->default(false),
                        Select::make('mode')
                            ->label('Chế độ')
                            ->options(['sandbox' => 'Sandbox (thử nghiệm)', 'production' => 'Production (thực tế)'])
                            ->required()
                            ->default('sandbox'),
                    ]),
                Section::make('Sandbox')
                    ->description('Khóa thử nghiệm, không sử dụng cho thanh toán thực tế.')
                    ->schema([
                        TextInput::make('sandbox.partner_code')->label('Partner Code')->maxLength(100),
                        TextInput::make('sandbox.access_key')
                            ->label('Access Key')->password()->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('sandbox.secret_key')
                            ->label('Secret Key')->password()->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('sandbox.endpoint_create')->label('API Endpoint')
                            ->default(config('momo-payment.endpoints.sandbox.create'))
                            ->readOnly()->dehydrated(false),
                        TextInput::make('sandbox.endpoint_query')->label('API Check Point')
                            ->default(config('momo-payment.endpoints.sandbox.query'))
                            ->readOnly()->dehydrated(false),
                    ])->collapsible(),
                Section::make('Production')
                    ->description('Khóa thật; xác nhận chính xác môi trường trước khi bật.')
                    ->schema([
                        TextInput::make('production.partner_code')->label('Partner Code')->maxLength(100),
                        TextInput::make('production.access_key')
                            ->label('Access Key')->password()->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('production.secret_key')
                            ->label('Secret Key')->password()->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('production.endpoint_create')->label('API Endpoint')
                            ->default(config('momo-payment.endpoints.production.create'))
                            ->readOnly()->dehydrated(false),
                        TextInput::make('production.endpoint_query')->label('API Check Point')
                            ->default(config('momo-payment.endpoints.production.query'))
                            ->readOnly()->dehydrated(false),
                    ])->collapsible(),
            ])
            ->statePath('data');
    }
}
