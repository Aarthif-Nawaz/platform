<?php

namespace Ushahidi\App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->configure('cdn');
        $this->app->configure('media');
        $this->app->configure('ratelimiter');
        $this->app->configure('multisite');
        $this->app->configure('ohanzee-db');

        $this->registerServicesFromAura();

        $this->registerFilesystem();
        $this->registerMailer();
        $this->registerDataSources();

        $this->configureAuraDI();

        // Hack, must construct it to register route :/
        $this->app->make('datasources');
    }

    public function registerServicesFromAura()
    {
        $this->app->singleton(\Ushahidi\Factory\UsecaseFactory::class, function ($app) {
            // Just return it from AuraDI
            return service('factory.usecase');
        });

        $this->app->singleton(\Ushahidi\Core\Entity\MessageRepository::class, function ($app) {
            // Just return it from AuraDI
            return service('repository.message');
        });
    }

    public function registerMailer()
    {
        // Add mailer
        $this->app->singleton('mailer', function ($app) {
            return $app->loadComponent(
                'mail',
                \Illuminate\Mail\MailServiceProvider::class,
                'mailer'
            );
        });
    }

    public function registerFilesystem()
    {
        // Add filesystem
        $this->app->singleton('filesystem', function ($app) {
            return $app->loadComponent(
                'filesystems',
                \Illuminate\Filesystem\FilesystemServiceProvider::class,
                'filesystem'
            );
        });
    }

    public function registerDataSources()
    {
        $this->app->singleton('datasources', function () {
            return $this->app->loadComponent(
                'datasources',
                \Ushahidi\App\DataSource\DataSourceServiceProvider::class,
                'datasources'
            );
        });
    }

    protected function configureAuraDI()
    {
        $di = service();

        $this->configureAuraServices($di);
        $this->injectAuraConfig($di);
    }

    protected function configureAuraServices(\Aura\Di\ContainerInterface $di)
    {
        // Configure mailer
        $di->set('tool.mailer', $di->lazyNew('Ushahidi\App\Tools\LumenMailer', [
            'mailer' => app('mailer'),
            'siteConfig' => $di->lazyGet('site.config'),
            'clientUrl' => $di->lazyGet('clienturl')
        ]));

        // @todo move to auth provider?
        $di->set('session', $di->lazyNew(\Ushahidi\App\Tools\LumenSession::class, [
            'userRepo' => $di->lazyGet('repository.user')
        ]));

        // Multisite db
        $di->set('kohana.db.multisite', function () use ($di) {
            $config = config('ohanzee-db');

            return \Ohanzee\Database::instance('multisite', $config['default']);
        });

        // Deployment db
        $di->set('kohana.db', function () use ($di) {
            return \Ohanzee\Database::instance('deployment', $this->getDbConfig($di));
        });
    }

    protected function injectAuraConfig(\Aura\Di\ContainerInterface $di)
    {
        // CDN Config settings
        $di->set('cdn.config', function () use ($di) {
            return config('cdn');
        });

        // Ratelimiter config settings
        $di->set('ratelimiter.config', function () use ($di) {
            return config('ratelimiter');
        });

        // Multisite db
        // Move multisite enabled check to class and move to src/App
        $di->set('site', function () use ($di) {
            $site = '';

            // Is this a multisite install?
            $multisite = config('multisite.enabled');
            if ($multisite) {
                $site = $di->get('multisite')->getSite();
            }

            return $site;
        });

        // Move multisite enabled check to class and move to src/App
        $di->set('tool.uploader.prefix', function () use ($di) {
            // Is this a multisite install?
            $multisite = config('multisite.enabled');
            if ($multisite) {
                return $di->get('multisite')->getCdnPrefix();
            }

            return '';
        });

        // Client Url
        $di->set('clienturl', function () use ($di) {
            return $this->getClientUrl($di->get('site.config'));
        });
    }

    protected function getDbConfig(\Aura\Di\ContainerInterface $di)
    {
        // Kohana injection
        // DB config
        $config = config('ohanzee-db');
        $config = $config['default'];

        // Is this a multisite install?
        $multisite = config('multisite.enabled');
        if ($multisite) {
            $config = $di->get('multisite')->getDbConfig();
        }

        return $config;
    }

    protected function getClientUrl($config)
    {
        $clientUrl = env('CLIENT_URL', false);

        if (env("MULTISITE_DOMAIN", false)) {
            try {
                $host = \League\Url\Url::createFromServer($_SERVER)->getHost()->toUnicode();
                $clientUrl = str_replace(env("MULTISITE_DOMAIN"), env("MULTISITE_CLIENT_DOMAIN"), $host);
            } catch (Exception $e) {
            }
        }

        // Or overwrite from config
        if (!$clientUrl && $config['client_url']) {
            $client_url = $config['client_url'];
        }

        return $clientUrl;
    }
}
