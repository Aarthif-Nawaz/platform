<?php

namespace Ushahidi\App\DataSource;

use Illuminate\Support\ServiceProvider;

class DataSourceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerStorage();
        $this->registerManager();
        $this->registerRoutes();
    }

    /**
     * Register the data provider manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('datasources', function ($app) {
            $manager = new DataSourceManager($app->router);

            $configRepo = service('repository.config');
            $dataProviderConfig = $configRepo->get('data-provider')->asArray();

            $manager->setEnabledSources($dataProviderConfig['providers']);
            $manager->setAvailableSources(service('features.data-providers'));

            $manager->setStorage($app->make(DataSourceStorage::class));

            $this->registerDataSources($manager);
            $manager->registerRoutes();

            return $manager;
        });

        $this->app->singleton(DataSourceManager::class, function ($app) {
            return $app->make('datasources');
        });
    }

    protected function registerDataSources($manager)
    {
        $configRepo = service('repository.config');
        $dataProviderConfig = $configRepo->get('data-provider')->asArray();

        $manager->addSource('email', new Email\Email($dataProviderConfig['email']));
        $manager->addSource('frontlinesms', new FrontlineSMS\FrontlineSMS($dataProviderConfig['frontlinesms']));
        $manager->addSource('nexmo', new Nexmo\Nexmo($dataProviderConfig['nexmo']));
        $manager->addSource('smssync', new SMSSync\SMSSync($dataProviderConfig['smssync']));
        $manager->addSource('twilio', new Twilio\Twilio($dataProviderConfig['twilio']));
        $manager->addSource('twitter', new Twitter\Twitter($dataProviderConfig['twitter']));

        return $manager;
    }

    protected function registerStorage()
    {
        $this->app->singleton(DataSourceStorage::class, function ($app) {
            return $this->makeStorage();
        });
    }

    protected function makeStorage()
    {
        $receiveUsecase = service('factory.usecase')->get('messages', 'receive');
        $messageRepo = service('repository.message');
        return new DataSourceStorage($receiveUsecase, $messageRepo);
    }

    public function registerRoutes()
    {
        // $this->app->router->post('/sms/{source}[/]', 'Ushahidi\App\DataSource\DataSourceController@handleRequest');
        // $this->app->router->get('/sms/{source}[/]', 'Ushahidi\App\DataSource\DataSourceController@handleRequest');
    }
}
