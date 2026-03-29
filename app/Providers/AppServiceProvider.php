<?php

namespace App\Providers;

use App\Actions\BuildIpsQrDataStringAction;
use App\Actions\ExtractPdfTextAction;
use App\Actions\ParsePaymentSlipPdfAction;
use App\Contracts\BuildsIpsQrDataString;
use App\Contracts\ExtractsPdfText;
use App\Contracts\ParsesPaymentSlipPdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(ExtractsPdfText::class, ExtractPdfTextAction::class);
        $this->app->bind(BuildsIpsQrDataString::class, BuildIpsQrDataStringAction::class);
        $this->app->bind(ParsesPaymentSlipPdf::class, ParsePaymentSlipPdfAction::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
