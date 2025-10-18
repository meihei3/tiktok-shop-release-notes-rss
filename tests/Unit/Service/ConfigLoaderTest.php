<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Application\Dto\LimitsConfig;
use TikTokShopRss\Service\ConfigLoader;

class ConfigLoaderTest extends TestCase
{
    public function testLoadThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');

        $loader = new ConfigLoader();
        $loader->load('/nonexistent/path.yaml');
    }

    public function testLoadValidConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.yaml';

        file_put_contents($tempFile, <<<YAML
state_file: var/state/test.json
sources:
  - tree_url: "https://example.com/tree"
    detail_url_template: "https://example.com/detail?id={document_path}"
    public_url_template: "https://example.com/page/{document_path}"
channel:
  title: Test Channel
  link: https://example.com
  description: Test Description
limits:
  pages: 100
  items: 20
concurrency: 5
YAML
        );

        try {
            $loader = new ConfigLoader();
            $config = $loader->load($tempFile);

            $this->assertSame('var/state/test.json', $config->stateFile);
            $this->assertCount(1, $config->sources);
            $this->assertSame('https://example.com/tree', $config->sources[0]->treeUrl);
            $this->assertInstanceOf(ChannelConfig::class, $config->channel);
            $this->assertSame('Test Channel', $config->channel->title);
            $this->assertSame('https://example.com', $config->channel->link);
            $this->assertSame('Test Description', $config->channel->description);
            $this->assertInstanceOf(LimitsConfig::class, $config->limits);
            $this->assertSame(100, $config->limits->pages);
            $this->assertSame(5, $config->concurrency);
        } finally {
            unlink($tempFile);
        }
    }
}
