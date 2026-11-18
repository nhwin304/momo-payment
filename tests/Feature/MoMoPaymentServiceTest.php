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

    public function test_query_can_reconcile_old_sandbox_order_after_gateway_is_disabled(): void
    {
        // Cấu hình hiện tại đã chuyển qua Production, nhưng đơn gốc ở Sandbox.
        $this->mockSettings(['momo.enabled' => false, 'momo.mode' => 'production']);
        Http::fake(['test-payment.momo.vn/*' => Http::response([
            'partnerCode' => 'P001', 'orderId' => 'O1', 'requestId' => 'Q1',
            'amount' => 10000, 'resultCode' => 0, 'transId' => 11223344,
        ])]);

        $service = app(MoMoPaymentService::class);
        $result = $service->check('O1', 'Q1', 'sandbox');

        self::assertTrue($service->isSuccessfulQuery($result, 'O1', 'Q1', 10000, 'sandbox'));
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://test-payment.momo.vn/v2/gateway/api/query'
            && $request['partnerCode'] === 'P001'
        );
    }

    public function test_query_rejects_mismatched_or_malformed_result(): void
    {
        $this->mockSettings();
        $service = app(MoMoPaymentService::class);
        $result = [
            'partnerCode' => 'P001', 'orderId' => 'O1',
            'requestId' => 'Q1', 'amount' => 10000, 'resultCode' => 0,
            'transId' => 11223344,
        ];

        self::assertTrue($service->isSuccessfulQuery($result, 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'partnerCode' => 'X'], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'orderId' => ['O1']], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'requestId' => 'Q2'], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'amount' => '1e4'], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'amount' => 9999], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'resultCode' => 9000], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'transId' => 0], 'O1', 'Q1', 10000));
        self::assertFalse($service->isSuccessfulQuery([...$result, 'transId' => []], 'O1', 'Q1', 10000));
    }

    public function test_create_rejects_order_ids_outside_momo_format(): void
    {
        $this->mockSettings();
        Http::fake();

        foreach (['O:123', 'O123-', 'O..', str_repeat('A', 51)] as $orderId) {
            try {
                app(MoMoPaymentService::class)->create(
                    $orderId, 'R1', 10000, 'captureWallet', 'Don hang 1',
                    'https://example.com/return', 'https://example.com/ipn',
                );
                self::fail("Đã chấp nhận orderId không hợp lệ: {$orderId}");
            } catch (InvalidArgumentException) {
                // Đúng: từ chối trước khi gọi API.
            }
        }

        Http::assertNothingSent();
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
