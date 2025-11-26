<?php

declare(strict_types=1);

namespace OpenAI\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OpenAI;
use OpenAI\Client;
use OpenAI\Contracts\ClientContract;
use OpenAI\Laravel\Commands\InstallCommand;
use OpenAI\Laravel\Exceptions\ApiKeyIsMissing;

/**
 * @internal
 */
final class ServiceProvider extends BaseServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $config = config('openai', []);

        if (!empty($config['connections']) && is_array($config['connections']))
        {
            // New multi-config array format
            foreach ($config['connections'] as $connection)
                $this->registerClient($connection, $config['default']);
        }
        else
        {
            // Original Single Client config format
            $this->registerClient($config);
        }
    }

    public function registerClient($config, $name, $default = 'openai')
    {
        $name = 'openai.' . $name;

        $this->app->singleton($name, static function (): Client {
            $apiKey = $config['api_key'];
            $organization = $config['organization'];
            $project = $config['project'];
            $baseUri = $config['base_uri'];

            if (! is_string($apiKey) || ($organization !== null && ! is_string($organization))) {
                throw ApiKeyIsMissing::create();
            }

            $client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withOrganization($organization)
                ->withHttpClient(new \GuzzleHttp\Client(['timeout' => config['request_timeout'] ?? 30]));

            if (is_string($project)) {
                $client->withProject($project);
            }

            if (is_string($baseUri)) {
                $client->withBaseUri($baseUri);
            }

            return $client->make();
        });

        if ($name == $default)
        {
            $this->app->alias($name, 'openai');
            $this->app->alias($name, Client::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/openai.php' => config_path('openai.php'),
            ]);

            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Client::class,
            ClientContract::class,
            'openai',
        ];
    }
}
