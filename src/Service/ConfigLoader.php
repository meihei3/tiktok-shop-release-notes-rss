<?php

declare(strict_types=1);

namespace TikTokShopRss\Service;

use Symfony\Component\Yaml\Yaml;
use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Application\Dto\LimitsConfig;
use TikTokShopRss\Application\Dto\RetryConfig;
use TikTokShopRss\Application\Dto\RssConfig;
use TikTokShopRss\Application\Dto\SaveRawConfig;
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

        $rssData = $data['rss'] ?? [];
        $rss = new RssConfig(
            enableContentEncoded: (bool) ($rssData['enable_content_encoded'] ?? true),
        );

        $limitsData = $data['limits'] ?? [];
        $limits = new LimitsConfig(
            pages: (int) ($limitsData['pages'] ?? 300),
            items: (int) ($limitsData['items'] ?? 50),
        );

        $retryData = $data['retry'] ?? [];
        $retry = new RetryConfig(
            maxAttempts: (int) ($retryData['attempts'] ?? 3),
            delayMs: (int) ($retryData['backoff_initial_ms'] ?? 1000),
        );

        $saveRawData = $data['save_raw'] ?? [];
        $saveRaw = new SaveRawConfig(
            enabled: (bool) ($saveRawData['tree'] ?? false),
        );

        return new Config(
            stateFile: $data['state_file'] ?? 'var/state/tiktok-shop.json',
            sources: $sources,
            channel: $channel,
            rss: $rss,
            limits: $limits,
            concurrency: $data['concurrency'] ?? 10,
            retry: $retry,
            sleepBetweenRequestsMs: $data['sleep_between_requests_ms'] ?? 100,
            saveRaw: $saveRaw,
        );
    }
}
