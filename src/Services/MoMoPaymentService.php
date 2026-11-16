<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Nhwin\MoMoPayment\Exceptions\MoMoPaymentException;

/**
 * API công khai cho Controller, Livewire và service của ứng dụng (ví dụ Ozzdem).
 *
 * Package không tự cập nhật đơn hàng; ứng dụng chịu trách nhiệm lưu payment
 * pending, nhận IPN, khóa bản ghi và bảo đảm hoàn tất đúng một lần.
 */
final class MoMoPaymentService
{
    public function __construct(private readonly MoMoConfiguration $configuration) {}

    /**
     * Tạo yêu cầu thanh toán MoMo v2, trả nguyên JSON phản hồi, có payUrl khi thành công.
     * Cố ý yêu cầu cả hai callback URL; không phụ thuộc route của một website cụ thể.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $orderId,
        string $requestId,
        int $amount,
        string $requestType = 'captureWallet',
        string $orderInfo = 'Thanh toán đơn hàng',
        string $redirectUrl = '',
        string $ipnUrl = '',
        string $extraData = '',
    ): array {
        $this->assertIdentifier($orderId, 'orderId');
        $this->assertIdentifier($requestId, 'requestId');
        if ($amount < 1000 || $amount > 50000000) {
            throw new InvalidArgumentException('Số tiền phải từ 1.000 đến 50.000.000 VND.');
        }
        if (! in_array($requestType, ['captureWallet', 'payWithMethod'], true)) {
            throw new InvalidArgumentException('requestType không được hỗ trợ.');
        }
        if (trim($orderInfo) === '' || mb_strlen($orderInfo) > 200) {
            throw new InvalidArgumentException('orderInfo không hợp lệ.');
        }

        $this->assertHttpsUrl($redirectUrl, 'redirectUrl');
        $this->assertHttpsUrl($ipnUrl, 'ipnUrl');
        $mode = $this->configuration->mode();
        $keys = $this->configuration->credentialsFor($mode);

        $payload = [
            'partnerCode' => $keys['partnerCode'],
            'partnerName' => (string) config('momo-payment.partner_name', config('app.name', 'Laravel')),
            'storeId' => (string) config('momo-payment.store_id', 'MainStore'),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
        ];
        $payload['signature'] = MoMoSigner::signCreate($payload, $keys['accessKey'], $keys['secretKey']);

        return $this->post('create', $payload, $mode);
    }

    /** Truy vấn trạng thái thực tế khi cần đối chiếu, không suy đoán từ redirect. */
    public function check(string $orderId, string $requestId): array
    {
        $this->assertIdentifier($orderId, 'orderId');
        $this->assertIdentifier($requestId, 'requestId');
        $keys = $this->configuration->credentials();

        $payload = [
            'partnerCode' => $keys['partnerCode'],
            'orderId' => $orderId,
            'requestId' => $requestId,
            'lang' => 'vi',
        ];
        $payload['signature'] = MoMoSigner::signQuery($payload, $keys['accessKey'], $keys['secretKey']);

        return $this->post('query', $payload, $mode);
    }

    /**
     * Kiểm tra IPN cả chữ ký, partnerCode, mã đơn, requestId và số tiền.
     * Truyền thông tin kỳ vọng từ payment record của ứng dụng, KHÔNG từ IPN.
     * Có thể truyền mode đã lưu cùng giao dịch để xử lý IPN sau khi đổi môi trường.
     */
    public function verifyIpn(
        array $notification,
        string $expectedOrderId,
        string $expectedRequestId,
        int $expectedAmount,
        ?string $mode = null,
    ): bool {
        if ($expectedAmount <= 0 || $expectedOrderId === '' || $expectedRequestId === '') {
            return false;
        }

        $keys = $this->configuration->credentialsFor($mode, requireEnabled: false);
        if (
            ! is_string($notification['partnerCode'] ?? null) ||
            ! hash_equals($keys['partnerCode'], $notification['partnerCode']) ||
            (string) ($notification['orderId'] ?? '') !== $expectedOrderId ||
            (string) ($notification['requestId'] ?? '') !== $expectedRequestId ||
            ! is_numeric($notification['amount'] ?? null) ||
            (string) $notification['amount'] !== (string) $expectedAmount
        ) {
            return false;
        }

        return MoMoSigner::verifyNotification($notification, $keys['accessKey'], $keys['secretKey']);
    }

    /** Chỉ xác nhận thành công khi IPN hợp lệ và resultCode là 0. */
    public function isSuccessfulIpn(
        array $notification,
        string $expectedOrderId,
        string $expectedRequestId,
        int $expectedAmount,
        ?string $mode = null,
    ): bool {
        return $this->verifyIpn($notification, $expectedOrderId, $expectedRequestId, $expectedAmount, $mode)
            && in_array($notification['resultCode'] ?? null, [0, '0'], true);
    }

    private function post(string $operation, array $payload, string $mode): array
    {
        // Không tự retry POST; requestId giúp đối tác chủ động xử lý yêu cầu trùng.
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout((int) config('momo-payment.connect_timeout', 10))
                ->timeout((int) config('momo-payment.timeout', 35))
                ->post($this->configuration->endpoint($operation, $mode), $payload);
        } catch (ConnectionException) {
            // Không để lọt URL/query hoặc bí mật vào thông báo lỗi cho người dùng.
            throw new MoMoPaymentException('Không kết nối được với MoMo, cần đối chiếu trạng thái trước khi thử lại.');
        }

        if (! $response->successful()) {
            throw new MoMoPaymentException('MoMo trả mã HTTP '.$response->status().'.');
        }

        $data = $response->json();
        if (! is_array($data) || ! array_key_exists('resultCode', $data)) {
            throw new MoMoPaymentException('Phản hồi MoMo không hợp lệ.');
        }

        return $data;
    }

    private function assertIdentifier(string $value, string $name): void
    {
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,49}$/D', $value)) {
            throw new InvalidArgumentException("{$name} phải dài 1-50 ký tự an toàn.");
        }
    }

    private function assertHttpsUrl(string $url, string $name): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException("{$name} phải là URL HTTPS hợp lệ.");
        }
    }
}
