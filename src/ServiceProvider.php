<?php

namespace Centeron\Permissions;

use Centeron\Permissions\Commands\AttachAuthItems;
use Centeron\Permissions\Commands\CreateAuthItem;
use Centeron\Permissions\Commands\DetachAuthItems;
use Centeron\Permissions\Commands\DisinheritAuthItem;
use Centeron\Permissions\Commands\InheritAuthItem;
use Centeron\Permissions\Commands\RemoveAuthItems;
use Centeron\Permissions\Contracts\AuthItem as AuthItemContract;
use Centeron\Permissions\Contracts\AuthAssigment as AuthAssigmentContract;
use Centeron\Permissions\Exceptions\AuthItemNotFound;
use Centeron\Permissions\Models\AuthItem;
use Centeron\Permissions\Models\AuthAssigment;
use Exception;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Class ServiceProvider
 * @package Centeron\Permissions
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  Gate  $gate
     *
     * @return void
     */
    public function boot(Gate $gate)
    {
        $this->publishes([
            __DIR__.'/../config/permissions.php' => config_path('permissions.php'),
        ], 'config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/');

        $gate->before(static function (Authorizable $model, string $ability, $params) {
            try {
                if (method_exists($model, 'canAnyAuthItems')) {
                    return $model->canAnyAuthItems($ability, $params) ?: null;
                }
            } catch (AuthItemNotFound $e) {
            }
            return null;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAuthItem::class,
                RemoveAuthItems::class,
                InheritAuthItem::class,
                DisinheritAuthItem::class,
                AttachAuthItems::class,
                DetachAuthItems::class
            ]);
        }

        $this->registerBladeExtensions();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php', 'permissions'
        );

        $this->app->bind(AuthItemContract::class, AuthItem::class);
        $this->app->bind(AuthAssigmentContract::class, AuthAssigment::class);

        $this->registerBladeExtensions();
    }

    /**
     * Register blade extensions.
     *
     * @return void
     */
    protected function registerBladeExtensions()
    {
        $this->app->afterResolving('blade.compiler', static function (BladeCompiler $bladeCompiler) {
            $bladeCompiler->directive('authHasAny', static function ($expression) {
                return "<?php if(auth()->check() && auth()->user()->hasAnyAuthItems($expression)) { ?>";
            });
            $bladeCompiler->directive('authHasAll', static function ($expression) {
                return "<?php if(auth()->check() && auth()->user()->hasAllAuthItems($expression))) { ?>";
            });
            $bladeCompiler->directive('authCanAny', static function ($expression) {
                return "<?php if(auth()->check() && auth()->user()->canAnyAuthItems($expression)) { ?>";
            });
            $bladeCompiler->directive('authCanAll', static function ($expression) {
                return "<?php if(auth()->check() && auth()->user()->canAllAuthItems($expression)) { ?>";
            });
            $bladeCompiler->directive('authElse', static function () {
                return '<?php } else { ?>';
            });
            $bladeCompiler->directive('authEnd', static function () {
                return '<?php } ?>';
            });
        });
    }
}
