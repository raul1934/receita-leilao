<?php

namespace App\Providers;

use App\Services\Sle\SleClient;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton para que a pausa entre requisições valha para o processo todo.
        $this->app->singleton(SleClient::class, fn () => new SleClient(config('sle')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As views não usam Tailwind; a paginação usa a marcação simples do Bootstrap 4.
        Paginator::useBootstrapFour();
    }
}
