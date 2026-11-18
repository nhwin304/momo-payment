# nhwin/momo-payment

Package tích hợp **MoMo Payment API v2** cho **Laravel 13 + Filament 5**. Cấu hình lưu bằng **nhwin/settings 2.x**, có **Sandbox / Production**, hỗ trợ HMAC-SHA256, tạo thanh toán, truy vấn giao dịch và xác minh IPN. Code được tách khỏi các class Botble để dùng độc lập trong Ozzdem hoặc ứng dụng Laravel khác.

> **Quan trọng:** Đây là thư viện tích hợp cổng thanh toán, không phải hệ thống kế toán. Package không tự cập nhật order, không tự tạo route IPN và không được coi redirect của trình duyệt là bằng chứng đã thanh toán. Cần kiểm thử end-to-end bằng tài khoản MoMo Sandbox trước khi đưa vào production.

## 1. Yêu cầu

| Thư viện | Phiên bản |
| --- | --- |
| PHP | ^8.3 |
| ext-bcmath | * |
| Laravel | ^13.0 |
| Filament | ^5.0 |
| Filament Shield | ^4.3 |
| nhwin/settings | ^2.0 |
| Tiền tệ | VND (số nguyên, không dùng float) |

Package name trong Composer: **`nhwin/momo-payment`**. Namespace: `Nhwin\MoMoPayment`.

## 2. Cài đặt

Khi package đã được xuất bản trên Packagist:

```bash
composer require nhwin/momo-payment
```

Nếu package chưa được đưa lên Packagist, khai báo VCS trong **composer.json của ứng dụng Laravel**, rồi cài theo nhánh:

```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/nhwin304/momo-payment"}
  ]
}
```

```bash
composer require nhwin/momo-payment:dev-main
```

Composer tự động load `Nhwin\MoMoPayment\MoMoPaymentServiceProvider`. Cài bảng settings của `nhwin/settings` (bỏ qua nếu đã cài):

```bash
php artisan settings:install
php artisan migrate
php artisan vendor:publish --tag=momo-payment-config
```

Khi `settings:install` có hỏi thao tác publish/migrate, thực hiện theo hướng dẫn tương tác. Có thể dùng `php artisan vendor:publish --tag=settings-migrations` trước `migrate` nếu muốn publish thủ công.

**Để dùng trang cấu hình Filament 5**, thêm plugin vào Panel Provider (ví dụ `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Nhwin\MoMoPayment\Filament\MoMoPlugin;

public function panel(\Filament\Panel $panel): \Filament\Panel
{
    return $panel
        // ... cấu hình panel đang có ...
        ->plugin(MoMoPlugin::make());
}
```

Nếu ứng dụng cũng có `SettingsPlugin::make()` của `nhwin/settings`, hãy đăng ký cả hai plugin. `MoMoPlugin` tự đăng ký trang `MoMoSettingsPage`; không cần thêm class này lần thứ hai vào `pages()`.

### Cấp quyền quản trị

Trang `MoMo Payment` sử dụng `canAccessSettingsPage()` và yêu cầu quyền `manage_momo_payment`. Tạo quyền và gán cho vai trò phù hợp qua Filament Shield hoặc Spatie Permission:

```php
use Spatie\Permission\Models\Permission;

// Thực hiện một lần trong Tinker/Seeder, theo guard của ứng dụng.
Permission::findOrCreate('manage_momo_payment', 'web');
$user->givePermissionTo('manage_momo_payment');
```

Thay guard `web` theo guard đăng nhập của panel nếu khác; không gán quyền này cho tài khoản khách. Super admin của Shield có thể được cấp quyền thông qua cơ chế Gate của ứng dụng. Có thể đổi tên permission trong `config/momo-payment.php`.

## 3. Giao diện cấu hình

Vào **Admin → MoMo Payment**, gồm:

- **Enable**: mặc định tắt. Khi bật hoặc đổi môi trường, server kiểm tra đủ Partner Code, Access Key và Secret Key của môi trường đang chọn. Không bắt buộc điền khóa của môi trường không sử dụng.
- **Chế độ**: `sandbox` hoặc `production` (mặc định Sandbox).
- **Sandbox**: Partner Code, Access Key, Secret Key, API Endpoint, API Check Point.
- **Production**: bộ Partner Code, Access Key, Secret Key riêng cùng các URL API tương ứng.
- **Lưu cấu hình MoMo**: lưu bằng `nhwin/settings`, group `momo`.

`Access Key` và `Secret Key` được **mã hóa bằng cơ chế encrypted() của nhwin/settings**, không hiển thị lại plaintext khi mở form. Để cập nhật, nhập khóa mới; để giữ khóa hiện tại, để trống. Livewire action kiểm tra lại quyền trước khi lưu. **Không thay `APP_KEY`** khi chưa có quy trình giải mã/mã hóa lại dữ liệu.

Hai endpoint hiển thị **chỉ đọc** trên form nhằm tránh chuyển hướng khóa thanh toán đến địa chỉ khác; quản trị máy chủ có thể điều chỉnh trong `config/momo-payment.php`, nhưng code chỉ cho phép HTTPS tới các hostname MoMo được phép.

| Môi trường | Tạo giao dịch | Kiểm tra |
| --- | --- | --- |
| Sandbox | `https://test-payment.momo.vn/v2/gateway/api/create` | `https://test-payment.momo.vn/v2/gateway/api/query` |
| Production | `https://payment.momo.vn/v2/gateway/api/create` | `https://payment.momo.vn/v2/gateway/api/query` |

**Cấu trúc settings trong DB**:

```text
momo.enabled
momo.mode
momo.sandbox.partner_code
momo.sandbox.access_key  # encrypted
momo.sandbox.secret_key  # encrypted
momo.production.partner_code
momo.production.access_key # encrypted
momo.production.secret_key # encrypted
```

MoMo config của package được đọc ở `scope=global` để request thanh toán và IPN đều dùng chung cấu hình. Trang quản trị mặc định **luôn đọc và ghi vào scope global**, kể cả khi ứng dụng cấu hình `settings.scope_resolver` theo tenant.

## 4. Sử dụng service tại dự án Ozzdem

Có thể inject `Nhwin\MoMoPayment\Services\MoMoPaymentService` vào Controller, Livewire, Job hoặc service. **Phải tạo bản ghi pending trước khi gọi MoMo**, lưu `orderId`, `requestId`, `amount` và `mode` để đối chiếu IPN.

```php
use Nhwin\MoMoPayment\Services\MoMoPaymentService;
use Nhwin\MoMoPayment\Services\MoMoConfiguration;
use Illuminate\Support\Str;

public function checkout(int $orderIdInput, MoMoPaymentService $momo, MoMoConfiguration $config)
{
    // Ví dụ giả định Ozzdem dùng App\\Models\\Order; tùy chỉnh theo model thực tế.
    $order = \App\Models\Order::findOrFail($orderIdInput);
    $orderId = 'O'.$order->id;
    $requestId = (string) Str::uuid();
    $amount = (int) $order->total_vnd; // Số nguyên VND

    // Lưu vào bảng payment của ỨNG DỤNG: orderId, requestId, amount,
    // $config->mode(), trạng thái pending; đặt unique cho orderId.
    // Không được đổi tổng tiền sau thời điểm này.
    $result = $momo->create(
        orderId: $orderId,
        requestId: $requestId,
        amount: $amount,
        requestType: 'captureWallet', // Hoặc 'payWithMethod'
        orderInfo: 'Thanh toan don Ozzdem '.$order->id,
        redirectUrl: route('payments.momo.return'),
        ipnUrl: route('payments.momo.ipn'),
        extraData: '',
    );

    if (($result['resultCode'] ?? -1) !== 0 || ! is_string($result['payUrl'] ?? null)) {
        // Xử lý lỗi, không tự đánh dấu đã thanh toán.
        abort(502, 'Không thể tạo phiên thanh toán MoMo.');
    }

    return redirect()->away($result['payUrl']);
}
```

`redirectUrl` và `ipnUrl` phải là URL HTTPS công khai. **Không** dùng cùng một URL để xử lý cả redirect trình duyệt và IPN server-to-server. `requestId` phải duy nhất; khi request timeout, hãy đối chiếu bằng `check()` trước khi quyết định tạo yêu cầu mới.

### Truy vấn giao dịch

```php
$momo = app(MoMoPaymentService::class);
$payment = \App\Models\Payment::where('gateway_order_id', 'O123')->firstOrFail();
$queryRequestId = (string) \Illuminate\Support\Str::uuid(); // ID mới cho lần query

$result = $momo->check(
    orderId: $payment->gateway_order_id,
    requestId: $queryRequestId,
    mode: $payment->gateway_mode, // Môi trường đã lưu cùng giao dịch
);

if ($momo->isSuccessfulQuery(
    result: $result,
    expectedOrderId: $payment->gateway_order_id,
    expectedQueryRequestId: $queryRequestId,
    expectedAmount: (int) $payment->amount_vnd,
    mode: $payment->gateway_mode,
)) {
    // Chỉ xử lý pending -> paid trong DB transaction có lock + idempotency.
    // Kiểm tra/lưu transId duy nhất theo nghiệp vụ ứng dụng.
}
```

`check()` đối soát được cả giao dịch cũ sau khi tắt cổng hoặc đổi môi trường; việc tạo giao dịch mới vẫn bị chặn khi cổng tắt. Mã `orderId` cần đúng định dạng chữ/số và dấu `-`, `_`, `.` nằm giữa các cụm chữ/số theo quy định của MoMo. Dữ liệu query chỉ được tin cậy khi lấy từ kết nối HTTPS tới host MoMo chính thức; **không đưa phản hồi từ trình duyệt vào `isSuccessfulQuery()`**.

## 5. Xử lý IPN bảo mật và idempotent

Khai báo route POST trong ứng dụng Ozzdem. Cho phép MoMo gửi IPN **không có CSRF token** chỉ với route này (Laravel 13: khai báo ngoại lệ tại `bootstrap/app.php` theo cấu hình middleware của ứng dụng). Không yêu cầu người dùng đăng nhập tại route IPN.

```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MoMoIpnController;

Route::post('/payments/momo/ipn', MoMoIpnController::class)
    ->name('payments.momo.ipn');
```

Pseudo-code xử lý trong `MoMoIpnController`:

```php
public function __invoke(\Illuminate\Http\Request $request, \Nhwin\MoMoPayment\Services\MoMoPaymentService $momo)
{
    $ipn = $request->all();
    // Dùng mã đơn từ IPN CHỈ để tìm payment; không tin số tiền/trạng thái IPN.
    $payment = \App\Models\Payment::where('gateway', 'momo')
        ->where('gateway_order_id', $ipn['orderId'] ?? '')
        ->firstOrFail();

    $valid = $momo->verifyIpn(
        notification: $ipn,
        expectedOrderId: $payment->gateway_order_id,
        expectedRequestId: $payment->gateway_request_id,
        expectedAmount: (int) $payment->amount_vnd,
        mode: $payment->gateway_mode, // sandbox/production tại lúc tạo thanh toán
    );

    if (! $valid) {
        return response()->json(['message' => 'Invalid signature'], 400);
    }

    // Phía ứng dụng: DB::transaction + lockForUpdate() để tránh xử lý trùng IPN.
    // Chỉ chuyển pending => paid MỘT LẦN nếu resultCode === 0.
    // Lưu transId (unique), đối chiếu mã đơn và số tiền; ghi nhận log không có secret.
    // resultCode != 0 => trạng thái thất bại/đang xử lý theo nghiệp vụ.
    // Kết quả redirect trên trình duyệt không được gọi cập nhật số dư/đơn hàng.

    // Chỉ ACK sau khi hoàn thành ghi DB/idempotency.
    return response()->noContent(); // HTTP 204 theo tài liệu MoMo
}
```

`verifyIpn()` kiểm tra chữ ký HMAC-SHA256, Partner Code, orderId, requestId và số tiền. `isSuccessfulIpn()` còn kiểm tra `resultCode = 0`. Cả hai **không** tự cập nhật DB, vì model/bảng payment của Ozzdem phụ thuộc dự án.

Nếu cần xử lý IPN tới khi đơn được tạo xong, hãy dùng queue, callback idempotent và cơ chế reconciliation của ứng dụng. Trường hợp đổi khóa khi còn đơn pending có thể khiến IPN cũ không xác minh được; nên hoàn tất/đối soát giao dịch trước khi xoay khóa.

## 6. API của package

| Phương thức | Chức năng |
| --- | --- |
| `MoMoPaymentService::create(...)` | Tạo yêu cầu MoMo, trả mảng response |
| `MoMoPaymentService::check($orderId, $requestId, $mode)` | Truy vấn theo môi trường gốc, kể cả sau khi gateway bị tắt |
| `MoMoPaymentService::verifyIpn($ipn, $orderId, $requestId, $amount, $mode)` | Kiểm tra dữ liệu thông báo và chữ ký |
| `MoMoPaymentService::isSuccessfulIpn(...)` | Xác thực IPN và resultCode=0 |
| `MoMoPaymentService::isSuccessfulQuery(...)` | Kiểm tra partner, mã đơn, mã query, số tiền, `resultCode=0` và `transId` dương |
| `MoMoConfiguration::enabled()` | Kiểm tra đã bật MoMo |
| `MoMoConfiguration::mode()` | Trả `sandbox` / `production` |

Ngoại lệ cấu hình, network và lỗi HTTP: `Nhwin\MoMoPayment\Exceptions\MoMoPaymentException`. Dữ liệu đầu vào sai định dạng: `InvalidArgumentException`. Các phản hồi business `resultCode != 0` được trả để caller xử lý.

## 7. Test và CI

```bash
composer validate --strict
composer install
find src tests config -name '*.php' -print0 | xargs -0 -n1 php -l
composer test
```

Workflow GitHub Actions nằm tại `.github/workflows/tests.yml` dùng PHP 8.3, `actions/checkout@v6` (Node 24, quyền chỉ đọc), kiểm tra cú pháp và chạy PHPUnit. Test tích hợp kiểm tra `nhwin/settings`: mã hóa, scope global, giữ khóa khi để trống và chặn kích hoạt nếu thiếu credentials. Phải xác minh status workflow **pass** trước khi triển khai production. Test API thật với sandbox credentials **không được commit lên git**.

## 8. Bảo mật & giới hạn

- Không đưa `secret_key` hoặc `access_key` vào JS, log, exception hoặc README.
- Không tính số tiền VND bằng float; lấy số nguyên từ DB, kiểm tra đối chiếu giá trị chính xác.
- Không auto retry POST khi request timeout; `requestId` là khóa idempotency theo MoMo.
- Trả `204 No Content` khi xử lý IPN hoàn tất (trong 15 giây theo tài liệu MoMo); dữ liệu sai chữ ký phải bị từ chối.
- Phân biệt hai bộ credentials, không lấy khóa Sandbox để kiểm tra Production.
- Host app phải tự xử lý: schema payment, callback, trạng thái, idempotency, retry/reconciliation, hoàn tiền và webhook observability.
- Chỉ hỗ trợ `captureWallet` và `payWithMethod` trong `create()`, không tự hỗ trợ refund/confirm/tokenization.
- Thiết lập `config('momo-payment.timeout')` mặc định 35 giây nhằm đáp ứng yêu cầu thời gian gọi query tối thiểu 30 giây của MoMo.

## Tài liệu tham chiếu

- [MoMo Developers – Payment API](https://developers.momo.vn/v3/docs/payment/api/collection-link/)
- [MoMo Developers – Query transaction](https://developers.momo.vn/v3/docs/payment/api/payment-api/query/)
- [MoMo Developers – API Idempotency](https://developers.momo.vn/v3/docs/payment/api/result-handling/idempotency/)
- [MoMo Developers – IPN Notification](https://developers.momo.vn/v3/docs/payment/api/result-handling/notification/)
- [nhwin/settings](https://github.com/nhwin304/settings)
- [Plugin Botble tham khảo](https://github.com/binjuhor/botble-momo-payment-plugin)

## Giấy phép

MIT. Xem [LICENSE](LICENSE).
