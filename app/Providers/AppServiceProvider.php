<?php

namespace App\Providers;

use App\Support\JalaliDate;
use App\Support\NumberFormat;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.default');
        Blade::directive('jalali', fn (string $expression): string => "<?php echo e(app('".JalaliDate::class."')->format({$expression})); ?>");
        Blade::directive('jalaliDateTime', fn (string $expression): string => "<?php echo e(app('".JalaliDate::class."')->format({$expression}, true)); ?>");
        Blade::directive('weight', fn (string $expression): string => "<?php echo e(app('".NumberFormat::class."')->weight({$expression})); ?>");
        Blade::directive('rate', fn (string $expression): string => "<?php echo e(app('".NumberFormat::class."')->rate({$expression})); ?>");
        Blade::directive('money', fn (string $expression): string => "<?php echo e(app('".NumberFormat::class."')->money({$expression})); ?>");
    }
}
