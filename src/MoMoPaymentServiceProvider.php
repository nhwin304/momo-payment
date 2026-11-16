<?php

declare(strict_types=1);

namespace Nhwin\MoMoPayment;

use Illuminate\Support\ServiceProvider;
use Nhwin\MoMoPayment\Services\MoMoConfiguration;
use Nhwin\MoMoPayment\Services\MoMoPaymentService;
use Nhwin\MoMoPayment\Settings\MoMoSettingsDefinition;

/** Bootstrap gói Composer, không đăng ký route IPN cho ứng dụng sử dụng. */
final class MoMoPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/momo-payment.php', 'momo-payment');

        // Registry của nhwin/settings đọc definitions từ config khi được khởi tạo.
        $definitions = (array) $this->app['config']->get('settings.definitions', []);
        if (! in_array(MoMoSettingsDefinition::class, $definitions, true)) {
            $definitions[] = MoMoSettingsDefinition::class;
            $this->app['config']->set('settings.definitions', $definitions);
        }

        // Scoped để tránh giữ settings/credentials giữa các request Octane hoặc queue.
        $this->app->scoped(MoMoConfiguration::class);
        $this->app->scoped(MoMoPaymentService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'momo-payment');

        $this->publishes([
            __DIR__.'/../config/momo-payment.php' => config_path('momo-payment.php'),
        ], 'momo-payment-config');
    }
}
