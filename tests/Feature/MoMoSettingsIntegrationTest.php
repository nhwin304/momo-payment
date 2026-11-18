<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nhwin\MoMoPayment\Services\MoMoConfiguration;
use Nhwin\MoMoPayment\Settings\MoMoSettingsDefinition;
use Nhwin\MoMoPayment\Tests\TestCase;
use Nhwin\Settings\Definitions\DefinitionRegistry;
use Nhwin\Settings\Facades\Setting;

final class MoMoSettingsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dùng bảng thử nghiệm tương thích schema của nhwin/settings 2.x.
        config()->set('app.key', 'base64:bmjN9j7vX8tBhjczHDWz3wkR7RZP8x4LL8CM3l2lQn8=');
        config()->set('app.cipher', 'AES-256-CBC');

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default('global');
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->unique(['scope', 'group', 'key']);
            $table->timestamps();
        });
    }

    public function test_package_registers_definition_and_encrypts_nested_keys(): void
    {
        self::assertInstanceOf(
            MoMoSettingsDefinition::class,
            app(DefinitionRegistry::class)->get('momo'),
        );

        Setting::forScope('global')->setMany('momo', [
            'enabled' => true,
            'mode' => 'sandbox',
            'sandbox' => [
                'partner_code' => 'P001',
                'access_key' => 'SENSITIVE-ACCESS',
                'secret_key' => 'SENSITIVE-SECRET',
            ],
        ]);

        $stored = (string) DB::table('settings')
            ->where('scope', 'global')->where('group', 'momo')->where('key', 'sandbox')
            ->value('value');

        self::assertStringNotContainsString('SENSITIVE-ACCESS', $stored);
        self::assertStringNotContainsString('SENSITIVE-SECRET', $stored);
        self::assertSame('SENSITIVE-SECRET', Setting::forScope('global')->get('momo.sandbox.secret_key'));
        self::assertTrue(app(MoMoConfiguration::class)->enabled());
        self::assertSame('sandbox', app(MoMoConfiguration::class)->mode());
    }

    public function test_empty_secret_fields_preserve_previous_encrypted_values(): void
    {
        Setting::forScope('global')->setMany('momo', [
            'sandbox' => [
                'partner_code' => 'P001',
                'access_key' => 'KEEP-ACCESS',
                'secret_key' => 'KEEP-SECRET',
            ],
        ]);

        Setting::forScope('global')->setMany('momo', [
            'sandbox' => ['partner_code' => 'P002', 'access_key' => '', 'secret_key' => ''],
        ]);

        self::assertSame('P002', Setting::forScope('global')->get('momo.sandbox.partner_code'));
        self::assertSame('KEEP-ACCESS', Setting::forScope('global')->get('momo.sandbox.access_key'));
        self::assertSame('KEEP-SECRET', Setting::forScope('global')->get('momo.sandbox.secret_key'));
    }

    public function test_configuration_reads_global_scope_not_tenant_scope(): void
    {
        Setting::forScope('global')->setMany('momo', ['enabled' => true, 'mode' => 'sandbox']);
        Setting::forScope('tenant_test')->setMany('momo', ['enabled' => false, 'mode' => 'production']);

        $config = app(MoMoConfiguration::class);

        self::assertTrue($config->enabled());
        self::assertSame('sandbox', $config->mode());
    }
}
