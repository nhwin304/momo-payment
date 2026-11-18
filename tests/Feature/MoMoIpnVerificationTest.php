<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests\Feature;

use Mockery;
use Nhwin\MoMoPayment\Services\MoMoPaymentService;
use Nhwin\MoMoPayment\Tests\TestCase;
use Nhwin\Settings\Contracts\SettingsManagerContract;

final class MoMoIpnVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $values = [
            'momo.enabled' => false, // Đơn cũ vẫn được xác minh sau khi tắt cổng.
            'momo.mode' => 'production',
            'momo.sandbox.partner_code' => 'PSB',
            'momo.sandbox.access_key' => 'ACCESS-SB',
            'momo.sandbox.secret_key' => 'SECRET-SB',
            'momo.production.partner_code' => 'PPROD',
            'momo.production.access_key' => 'ACCESS-PROD',
            'momo.production.secret_key' => 'SECRET-PROD',
        ];
        $manager = Mockery::mock(SettingsManagerContract::class);
        $manager->shouldReceive('forScope')->with('global')->andReturnSelf();
        $manager->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => $values[$key] ?? $default
        );
        $this->app->instance(SettingsManagerContract::class, $manager);
    }

    private function signedIpn(int $resultCode = 0): array
    {
        $ipn = [
            'amount' => 10000, 'extraData' => '', 'message' => 'Successful.',
            'orderId' => 'O123', 'orderInfo' => 'Ozzdem 123', 'orderType' => 'momo_wallet',
            'partnerCode' => 'PSB', 'payType' => 'qr', 'requestId' => 'R123',
            'responseTime' => 1700000000000, 'resultCode' => $resultCode, 'transId' => 123456,
        ];
        $raw = 'accessKey=ACCESS-SB&amount=10000&extraData=&message=Successful.'
            .'&orderId=O123&orderInfo=Ozzdem 123&orderType=momo_wallet'
            .'&partnerCode=PSB&payType=qr&requestId=R123'
            ."&responseTime=1700000000000&resultCode={$resultCode}&transId=123456";
        $ipn['signature'] = hash_hmac('sha256', $raw, 'SECRET-SB');

        return $ipn;
    }

    public function test_ipn_uses_original_sandbox_mode_even_when_production_is_active(): void
    {
        $service = app(MoMoPaymentService::class);
        $ipn = $this->signedIpn();

        self::assertTrue($service->isSuccessfulIpn($ipn, 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->isSuccessfulIpn($ipn, 'O123', 'R123', 10000, 'production'));
        self::assertFalse($service->isSuccessfulIpn($ipn, 'O123', 'R123', 9999, 'sandbox'));
        self::assertFalse($service->isSuccessfulIpn($ipn, 'O123', 'R999', 10000, 'sandbox'));
        self::assertFalse($service->isSuccessfulIpn([...$ipn, 'transId' => 999], 'O123', 'R123', 10000, 'sandbox'));
    }

    public function test_invalid_ipn_field_types_fail_without_implicit_string_cast(): void
    {
        $service = app(MoMoPaymentService::class);
        $ipn = $this->signedIpn();

        self::assertFalse($service->verifyIpn([...$ipn, 'orderId' => ['O123']], 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->verifyIpn([...$ipn, 'requestId' => ['R123']], 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->verifyIpn([...$ipn, 'amount' => '1e4'], 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->verifyIpn([...$ipn, 'amount' => '010000'], 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->verifyIpn([...$ipn, 'amount' => 10000.0], 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->verifyIpn([...$ipn, 'partnerCode' => ['PSB']], 'O123', 'R123', 10000, 'sandbox'));
    }

    public function test_authentic_failed_ipn_must_not_be_marked_as_paid(): void
    {
        $ipn = $this->signedIpn(1006);
        $service = app(MoMoPaymentService::class);

        self::assertTrue($service->verifyIpn($ipn, 'O123', 'R123', 10000, 'sandbox'));
        self::assertFalse($service->isSuccessfulIpn($ipn, 'O123', 'R123', 10000, 'sandbox'));
    }
}
