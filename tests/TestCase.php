<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment\Tests;

use Nhwin\MoMoPayment\MoMoPaymentServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Nhwin\Settings\Providers\SettingServiceProvider::class,
            MoMoPaymentServiceProvider::class,
        ];
    }
}
