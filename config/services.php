<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TikTokShopRss\Service\ConfigLoader;
use TikTokShopRss\Service\DocumentFetcher;
use TikTokShopRss\Service\RssGenerator;
use TikTokShopRss\Service\StateManager;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // HttpClient
    $services->set(HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([[
            'timeout' => 30,
            'max_redirects' => 5,
        ]]);

    // Services
    $services->set(ConfigLoader::class);

    $services->set(StateManager::class);

    $services->set(DocumentFetcher::class)
        ->args([
            '$httpClient' => service(HttpClientInterface::class),
        ]);

    $services->set(RssGenerator::class)
        ->args([
            '$templateDir' => '%kernel.project_dir%/templates',
        ]);

    // Commands
    $services->load('TikTokShopRss\\Command\\', __DIR__ . '/../src/Command')
        ->tag('console.command');
};
