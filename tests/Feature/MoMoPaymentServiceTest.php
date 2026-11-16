<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use Nhwin\MoMoPayment\Exceptions\MoMoPaymentException;
use Nhwin\MoMoPayment\Services\MoMoPaymentService;
use Nhwin\MoMoPayment\Services\MoMoSigner;
use Nhwin\MoMoPayment\Tests\TestCase;
use Nhwin\Settings\Contracts\SettingsManagerContract;

final class MoMoPaymentServiceTest extends TestCase
{
    private function mockSettings(array $overrides = []): void
    {
        $values = array_replace([
            'momo.enabled' => true,
            'momo.mode' => 'sandbox',
            'momo.sandbox.partner_code' => 'P001',
            'momo.sandbox.access_key' => 'ACCESS',
            'momo.sandbox.secret_key' => 'SECRET',
        ], $overrides);

        $manager = Mockery::mock(SettingsManagerContract::class);
        $manager->shouldReceive('forScope')->with('global')->andReturnSelf();
        $manager->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => $values[$key] ?? $default
        );
        $this->app->instance(SettingsManagerContract::class, $manager);
    }

    public function test_create_calls_sandbox_and_signs_request(): void
    {
        $this->mockSettings();
        Http::fake([
            'test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://pay.test/1']),
        ]);

        $result = app(MoMoPaymentService::class)->create(
            'O1', 'R1', 10000, 'captureWallet', 'Don hang 1',
            'https://example.com/return', 'https://example.com/ipn',
        );

        self::assertSame(0, $result['resultCode']);
        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->url(), 'test-payment.momo.vn/v2/gateway/api/create')
            && $request['partnerCode'] === 'P001'
            && ! isset($request['accessKey'])
            && ! isset($request['secretKey'])
            && $request['signature'] === MoMoSigner::signCreate($request->data(), 'ACCESS', 'SECRET')
        );
    }

    public function test_query_uses_query_endpoint(): void
    {
        $this->mockSettings();
        Http::fake(['test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'amount' => 10000])]);
        $result = app(MoMoPaymentService::class)->check('O1', 'R2');
        self::assertSame(10000, $result['amount']);
        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/v2/gateway/api/query')
            && $request['signature'] === MoMoSigner::signQuery($request->data(), 'ACCESS', 'SECRET')
        );
    }

    public function test_disabled_gateway_rejects_payment_without_http_call(): void
    {
        $this->mockSettings(['momo.enabled' => false]);
        Http::fake();
        $this->expectException(MoMoPaymentException::class);

        try {
            app(MoMoPaymentService::class)->create(
                'O1', 'R1', 10000, 'captureWallet', 'Don hang 1',
                'https://example.com/return', 'https://example.com/ipn',
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_non_https_callback_is_rejected(): void
    {
        $this->mockSettings();
        $this->expectException(InvalidArgumentException::class);
        app(MoMoPaymentService::class)->create(
            'O1', 'R1', 10000, 'captureWallet', 'Don hang 1',
            'http://example.com/return', 'https://example.com/ipn',
        );
    }
}
