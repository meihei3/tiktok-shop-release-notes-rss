<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TikTokShopRss\Application\Port\DocumentFetcherInterface;
use TikTokShopRss\Application\Port\RssGeneratorInterface;
use TikTokShopRss\Application\Port\StateManagerInterface;
use TikTokShopRss\Application\UseCase\BuildRssUseCase;
use TikTokShopRss\Infrastructure\Http\DocumentFetcher;
use TikTokShopRss\Infrastructure\Persistence\StateManager;
use TikTokShopRss\Service\ConfigLoader;
use TikTokShopRss\Service\RssGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    // Infrastructure Layer
    $services->set(DocumentFetcherInterface::class, DocumentFetcher::class)
        ->args([
            '$httpClient' => service(HttpClientInterface::class),
        ]);

    $services->set(StateManagerInterface::class, StateManager::class);

    // Service Layer (Domain Services)
    $services->set(ConfigLoader::class);

    $services->set(RssGeneratorInterface::class, RssGenerator::class)
        ->args([
            '$templateDir' => '%kernel.project_dir%/templates',
        ]);

    // Application Layer
    $services->set(BuildRssUseCase::class)
        ->args([
            '$documentFetcher' => service(DocumentFetcherInterface::class),
            '$stateManager' => service(StateManagerInterface::class),
            '$rssGenerator' => service(RssGeneratorInterface::class),
        ]);

    // Commands
    $services->load('TikTokShopRss\\Command\\', __DIR__ . '/../src/Command')
        ->tag('console.command');
};
