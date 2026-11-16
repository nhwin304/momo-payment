<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Exceptions;

use RuntimeException;

/** Ngoại lệ cấu hình và phản hồi MoMo không hợp lệ, không chứa bí mật. */
final class MoMoPaymentException extends RuntimeException {}
