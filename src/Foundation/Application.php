<?php

namespace Igniter\Flame\Foundation;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Igniter\Flame\Foundation\Providers\LogServiceProvider;
use Illuminate\Foundation\Application as BaseApplication;
use Illuminate\Routing\RoutingServiceProvider;

/**
 * Igniter Application Class
 *
 * @package        System\Classes\BaseController.php
 */
class Application extends BaseApplication
{
    /**
     * The TastyIgniter version.
     *
     * @var string
     */
    const VERSION = '3.0.0-dev';

    /**
     * The base path for extensions.
     *
     * @var string
     */
    protected $extensionsPath;

    /**
     * The base path for themes.
     *
     * @var string
     */
    protected $themesPath;

    /**
     * The base path for views.
     *
     * @var string
     */
    protected $viewPath;

    /**
     * The base path for assets.
     *
     * @var string
     */
    protected $assetsPath;

    /**
     * The request execution context (main, admin)
     *
     * @var string
     */
    protected $appContext;

    /**
     * Get the path to the public directory.
     *
     * @return string
     */
    public function publicPath()
    {
        return $this->basePath;
    }

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath()
    {
        return $this->path('system/language');
    }

    /**
     * Get the path to the extensions directory.
     *
     * @return string
     */
    public function extensionsPath()
    {
        return $this->extensionsPath ?: $this->basePath.'/extensions';
    }

    /**
     * Get the path to the themes directory.
     *
     * @return string
     */
    public function themesPath()
    {
        return $this->themesPath ?: $this->basePath.'/themes';
    }

    /**
     * Get the path to the themes directory.
     *
     * @return string
     */
    public function assetsPath()
    {
        return $this->assetsPath ?: $this->basePath.'/assets';
    }

    /**
     * Get the path to the app context views directory.
     *
     * @return string
     */
    public function viewPaths()
    {
        return $this->viewPath ?: $this->basePath.'/app/'.$this->appContext.'/views';
    }

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));

        $this->register(new LogServiceProvider($this));

        $this->register(new RoutingServiceProvider($this));
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        parent::bindPathsInContainer();

        foreach (['extensions', 'themes', 'assets', 'temp'] as $path) {
            $this->instance('path.'.$path, $this->{$path.'Path'}());
        }
    }

    /**
     * Set the extensions path for the application.
     *
     * @param  string $path
     *
     * @return $this
     */
    public function useExtensionsPath($path)
    {
        $this->extensionsPath = $path;
        $this->instance('path.extensions', $path);

        return $this;
    }

    /**
     * Set the themes path for the application.
     *
     * @param  string $path
     *
     * @return $this
     */
    public function useThemesPath($path)
    {
        $this->themesPath = $path;
        $this->instance('path.themes', $path);

        return $this;
    }

    /**
     * Set the assets path for the application.
     *
     * @param  string $path
     *
     * @return $this
     */
    public function useAssetsPath($path)
    {
        $this->assetsPath = $path;
        $this->instance('path.assets', $path);

        return $this;
    }

    /**
     * Get the path to the storage temp directory.
     *
     * @return string
     */
    public function tempPath()
    {
        return $this->basePath.'/storage/temp';
    }

    /**
     * Determine if we are running in the admin area.
     *
     * @return bool
     */
    public function runningInAdmin()
    {
        return $this->appContext == 'admin';
    }

    /**
     * Gets the execution context
     *
     * @return string
     */
    public function appContext()
    {
        return $this->appContext;
    }

    /**
     * Sets the execution context
     *
     * @param  string $context
     *
     * @return void
     */
    public function setAppContext($context)
    {
        $this->appContext = $context;
    }

    /**
     * Returns true if a database connection is present.
     * @return boolean
     */
    public function hasDatabase()
    {
        try {
            $this['db.connection']->table('settings')->get();
//            $this['db.connection']->getPdo();
        } catch (Exception $ex) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    public function registerCoreContainerAliases()
    {
        $aliases = [
            'app'              => [\Igniter\Flame\Foundation\Application::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class],
//            'auth'                 => [\Illuminate\Auth\AuthManager::class, \Illuminate\Contracts\Auth\Factory::class],
//            'auth.driver'          => [\Illuminate\Contracts\Auth\Guard::class],
            'blade.compiler'   => [\Illuminate\View\Compilers\BladeCompiler::class],
            'cache'            => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
            'cache.store'      => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class],
            'config'           => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'cookie'           => [\Illuminate\Cookie\CookieJar::class, \Illuminate\Contracts\Cookie\Factory::class, \Illuminate\Contracts\Cookie\QueueingFactory::class],
            'encrypter'        => [\Illuminate\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\Encrypter::class],
            'db'               => [\Illuminate\Database\DatabaseManager::class],
//            'db'                   => [\Igniter\Flame\Database\DatabaseManager::class],
            'db.connection'    => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
            'events'           => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
            'files'            => [\Illuminate\Filesystem\Filesystem::class],
            'filesystem'       => [\Illuminate\Filesystem\FilesystemManager::class, \Illuminate\Contracts\Filesystem\Factory::class],
            'filesystem.disk'  => [\Illuminate\Contracts\Filesystem\Filesystem::class],
            'filesystem.cloud' => [\Illuminate\Contracts\Filesystem\Cloud::class],
            'hash'             => [\Illuminate\Contracts\Hashing\Hasher::class],
            'translator'       => [\Illuminate\Translation\Translator::class, \Illuminate\Contracts\Translation\Translator::class],
            'log'              => [\Illuminate\Log\Writer::class, \Illuminate\Contracts\Logging\Log::class, \Psr\Log\LoggerInterface::class],
            'mailer'           => [\Illuminate\Mail\Mailer::class, \Illuminate\Contracts\Mail\Mailer::class, \Illuminate\Contracts\Mail\MailQueue::class],
//            'auth.password'        => [\Illuminate\Auth\Passwords\PasswordBrokerManager::class, \Illuminate\Contracts\Auth\PasswordBrokerFactory::class],
//            'auth.password.broker' => [\Illuminate\Auth\Passwords\PasswordBroker::class, \Illuminate\Contracts\Auth\PasswordBroker::class],
            'queue'            => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
            'queue.failer'     => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
            'redirect'         => [\Illuminate\Routing\Redirector::class],
            'redis'            => [\Illuminate\Redis\RedisManager::class, \Illuminate\Contracts\Redis\Factory::class],
            'request'          => [\Illuminate\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class],
            'router'           => [\Illuminate\Routing\Router::class, \Illuminate\Contracts\Routing\Registrar::class, \Illuminate\Contracts\Routing\BindingRegistrar::class],
            'session'          => [\Illuminate\Session\SessionManager::class],
            'session.store'    => [\Illuminate\Session\Store::class, \Illuminate\Contracts\Session\Session::class],
            'url'              => [\Illuminate\Routing\UrlGenerator::class, \Illuminate\Contracts\Routing\UrlGenerator::class],
            'validator'        => [\Illuminate\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class],
            'view'             => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class],
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
    //
    // Caching
    //

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        return $this['path.storage'].'/framework/config.php';
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath()
    {
        return $this['path.storage'].'/framework/routes.php';
    }

    /**
     * Get the path to the cached services.json file.
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        return $this->storagePath().'/framework/services.php';
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedPackagesPath()
    {
        return $this->storagePath().'/framework/packages.php';
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedClassesPath()
    {
        return $this->storagePath().'/framework/classes.php';
    }
}