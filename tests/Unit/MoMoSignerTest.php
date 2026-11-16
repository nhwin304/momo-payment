<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests\Unit;

use Nhwin\MoMoPayment\Services\MoMoSigner;
use PHPUnit\Framework\TestCase;

final class MoMoSignerTest extends TestCase
{
    public function test_create_signature_uses_exact_documented_field_order(): void
    {
        $payload = [
            'requestType' => 'captureWallet',
            'redirectUrl' => 'https://example.test/return',
            'partnerCode' => 'P001',
            'amount' => 10000,
            'extraData' => '',
            'ipnUrl' => 'https://example.test/ipn',
            'orderId' => 'O1',
            'orderInfo' => 'Don hang 1',
            'requestId' => 'R1',
        ];
        $raw = 'accessKey=ACCESS&amount=10000&extraData=&ipnUrl=https://example.test/ipn'
            .'&orderId=O1&orderInfo=Don hang 1&partnerCode=P001'
            .'&redirectUrl=https://example.test/return&requestId=R1&requestType=captureWallet';

        self::assertSame(hash_hmac('sha256', $raw, 'SECRET'), MoMoSigner::signCreate($payload, 'ACCESS', 'SECRET'));
    }

    public function test_query_signature_uses_only_the_four_signed_fields(): void
    {
        $payload = ['requestId' => 'R1', 'orderId' => 'O1', 'partnerCode' => 'P001', 'lang' => 'vi'];
        $raw = 'accessKey=ACCESS&orderId=O1&partnerCode=P001&requestId=R1';

        self::assertSame(hash_hmac('sha256', $raw, 'SECRET'), MoMoSigner::signQuery($payload, 'ACCESS', 'SECRET'));
    }

    public function test_notification_signature_detects_modification_and_missing_fields(): void
    {
        $ipn = [
            'amount' => 10000, 'extraData' => '', 'message' => 'Successful.',
            'orderId' => 'O1', 'orderInfo' => 'Don hang 1', 'orderType' => 'momo_wallet',
            'partnerCode' => 'P001', 'payType' => 'qr', 'requestId' => 'R1',
            'responseTime' => 1700000000000, 'resultCode' => 0, 'transId' => 123456,
        ];
        $raw = 'accessKey=ACCESS&amount=10000&extraData=&message=Successful.'
            .'&orderId=O1&orderInfo=Don hang 1&orderType=momo_wallet'
            .'&partnerCode=P001&payType=qr&requestId=R1'
            .'&responseTime=1700000000000&resultCode=0&transId=123456';
        $ipn['signature'] = hash_hmac('sha256', $raw, 'SECRET');

        self::assertTrue(MoMoSigner::verifyNotification($ipn, 'ACCESS', 'SECRET'));
        self::assertFalse(MoMoSigner::verifyNotification([...$ipn, 'amount' => 10001], 'ACCESS', 'SECRET'));
        self::assertFalse(MoMoSigner::verifyNotification([...$ipn, 'signature' => 'bad'], 'ACCESS', 'SECRET'));
        unset($ipn['transId']);
        self::assertFalse(MoMoSigner::verifyNotification($ipn, 'ACCESS', 'SECRET'));
    }
}
