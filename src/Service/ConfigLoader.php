<?php

declare(strict_types=1);

namespace TikTokShopRss\Service;

use Symfony\Component\Yaml\Yaml;
use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Model\Config;
use TikTokShopRss\Model\Source;

use function file_exists;
use function is_array;

class ConfigLoader
{
    public function load(string $configPath): Config
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Config file not found: {$configPath}");
        }

        $data = Yaml::parseFile($configPath);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid config format");
        }

        $sources = [];
        foreach ($data['sources'] ?? [] as $sourceData) {
            $sources[] = new Source(
                treeUrl: $sourceData['tree_url'] ?? '',
                detailUrlTemplate: $sourceData['detail_url_template'] ?? '',
                publicUrlTemplate: $sourceData['public_url_template'] ?? '',
            );
        }

        $channelData = $data['channel'] ?? [];
        $channel = new ChannelConfig(
            title: $channelData['title'] ?? 'RSS Feed',
            link: $channelData['link'] ?? '',
            description: $channelData['description'] ?? '',
            language: $channelData['language'] ?? 'ja',
        );

        return new Config(
            stateFile: $data['state_file'] ?? 'var/state/tiktok-shop.json',
            sources: $sources,
            channel: $channel,
            rss: $data['rss'] ?? [],
            limits: $data['limits'] ?? ['pages' => 300, 'items' => 50],
            concurrency: $data['concurrency'] ?? 10,
            retry: $data['retry'] ?? ['attempts' => 3, 'backoff_initial_ms' => 100, 'backoff_max_ms' => 5000],
            sleepBetweenRequestsMs: $data['sleep_between_requests_ms'] ?? 100,
            saveRaw: $data['save_raw'] ?? ['tree' => false, 'detail' => false],
        );
    }
}
