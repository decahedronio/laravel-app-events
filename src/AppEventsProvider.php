<?php

namespace Decahedron\AppEvents;

use Decahedron\AppEvents\Commands\AppEventsListener;
use Illuminate\Support\ServiceProvider;

class AppEventsProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app['queue']->addConnector('app-events-pubsub', function () {
            return new PubSubConnector;
        });

        $this->app['config']->set('queue.connections.app-events', [
            'driver'     => 'app-events-pubsub',
            'queue'      => $this->app['config']->get('app-events.topic'),
            'project_id' => $this->app['config']->get('app-events.project_id'),
        ]);

        $this->publishes([
            __DIR__.'/../config/app-events.php' => config_path('app-events.php'),
        ]);
    }

    public function register()
    {
        $this->commands([
            AppEventsListener::class,
        ]);
    }
}
