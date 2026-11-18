<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Nhwin\MoMoPayment\Settings\MoMoSettingsValidator;
use Nhwin\MoMoPayment\Tests\TestCase;

final class MoMoSettingsValidatorTest extends TestCase
{
    private function sandboxData(bool $enabled = true): array
    {
        return [
            'enabled' => $enabled,
            'mode' => 'sandbox',
            'sandbox' => [
                'partner_code' => 'P001',
                'access_key' => 'ACCESS',
                'secret_key' => 'SECRET',
            ],
        ];
    }

    public function test_disabled_gateway_can_be_saved_without_credentials(): void
    {
        $data = ['enabled' => false, 'mode' => 'sandbox'];

        self::assertSame($data, app(MoMoSettingsValidator::class)->validate($data, []));
    }

    public function test_enabled_gateway_requires_all_credentials(): void
    {
        $this->expectException(ValidationException::class);

        app(MoMoSettingsValidator::class)->validate([
            'enabled' => true,
            'mode' => 'sandbox',
            'sandbox' => ['partner_code' => 'P001', 'access_key' => 'ACCESS'],
        ], []);
    }

    public function test_blank_password_fields_reuse_existing_server_side_secrets(): void
    {
        $data = $this->sandboxData();
        $data['sandbox']['access_key'] = '';
        $data['sandbox']['secret_key'] = '';

        $saved = app(MoMoSettingsValidator::class)->validate($data, [
            'sandbox' => [
                'partner_code' => 'P001',
                'access_key' => 'ACCESS-OLD',
                'secret_key' => 'SECRET-OLD',
            ],
        ]);

        // Validator không đưa khóa đã lưu trở lại trạng thái của Livewire.
        self::assertSame('', $saved['sandbox']['access_key']);
        self::assertSame('', $saved['sandbox']['secret_key']);
    }

    public function test_switching_environment_requires_production_credentials(): void
    {
        $this->expectException(ValidationException::class);

        app(MoMoSettingsValidator::class)->validate([
            ...$this->sandboxData(),
            'mode' => 'production',
        ], [
            'sandbox' => $this->sandboxData()['sandbox'],
        ]);
    }

    public function test_invalid_mode_is_rejected_even_when_disabled(): void
    {
        $this->expectException(ValidationException::class);

        app(MoMoSettingsValidator::class)->validate([
            'enabled' => false,
            'mode' => 'unknown',
        ], []);
    }

    public function test_enabled_value_is_normalized_without_truthy_string_bypass(): void
    {
        $data = $this->sandboxData();
        $data['enabled'] = '1';
        $saved = app(MoMoSettingsValidator::class)->validate($data, []);

        self::assertTrue($saved['enabled']);
    }
}
