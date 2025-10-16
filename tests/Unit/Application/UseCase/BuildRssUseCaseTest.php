<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use TikTokShopRss\Application\UseCase\BuildRssUseCase;
use TikTokShopRss\Infrastructure\Http\DocumentFetcher;
use TikTokShopRss\Infrastructure\Persistence\StateManager;
use TikTokShopRss\Model\Config;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\Source;
use TikTokShopRss\Model\State;
use TikTokShopRss\Service\RssGenerator;

class BuildRssUseCaseTest extends TestCase
{
    public function testBuildWithNewDocument(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcher::class);
        $stateManager = $this->createMock(StateManager::class);
        $rssGenerator = $this->createMock(RssGenerator::class);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/doc/{document_path}'
        );

        $config = new Config(
            stateFile: 'state.json',
            sources: [$source],
            channel: ['title' => 'Test'],
            rss: [],
            limits: ['pages' => 10, 'items' => 50],
            concurrency: 1,
            retry: [],
            sleepBetweenRequestsMs: 0,
            saveRaw: []
        );

        $state = new State(
            version: 2,
            sources: [],
            items: []
        );

        $documentFetcher
            ->expects($this->once())
            ->method('fetchTree')
            ->willReturn([
                'not_modified' => false,
                'data' => [
                    'data' => [
                        'document_tree' => [
                            ['document_path' => 'test-doc', 'update_time' => 1234567890],
                        ],
                    ],
                ],
                'etag' => 'etag123',
                'last_modified' => 'Mon, 01 Jan 2024 00:00:00 GMT',
            ]);

        $documentFetcher
            ->expects($this->once())
            ->method('extractDocumentPaths')
            ->willReturn([
                ['path' => 'test-doc', 'update_time' => 1234567890],
            ]);

        $documentFetcher
            ->expects($this->once())
            ->method('fetchDetail')
            ->willReturn([
                'data' => [
                    'title' => 'Test Document',
                    'content' => 'Test content',
                    'description' => 'Test description',
                    'update_time' => 1234567890,
                ],
            ]);

        $stateManager
            ->expects($this->once())
            ->method('findItemByDocumentPath')
            ->willReturn(null);

        $stateManager
            ->expects($this->once())
            ->method('mergeItems')
            ->willReturnCallback(function ($existing, $new) {
                return array_merge($existing, $new);
            });

        $useCase = new BuildRssUseCase($documentFetcher, $stateManager, $rssGenerator);
        $result = $useCase->build($config, $state);

        $this->assertSame(1, $result['pages_changed']);
        $this->assertInstanceOf(State::class, $result['state']);
        $this->assertCount(1, $result['state']->items);
        $this->assertSame('test-doc', $result['state']->items[0]->documentPath);
        $this->assertSame('Test Document', $result['state']->items[0]->title);
    }

    public function testBuildWithNotModifiedTree(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcher::class);
        $stateManager = $this->createMock(StateManager::class);
        $rssGenerator = $this->createMock(RssGenerator::class);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/doc/{document_path}'
        );

        $config = new Config(
            stateFile: 'state.json',
            sources: [$source],
            channel: ['title' => 'Test'],
            rss: [],
            limits: ['pages' => 10, 'items' => 50],
            concurrency: 1,
            retry: [],
            sleepBetweenRequestsMs: 0,
            saveRaw: []
        );

        $state = new State(
            version: 2,
            sources: [],
            items: []
        );

        $documentFetcher
            ->expects($this->once())
            ->method('fetchTree')
            ->willReturn([
                'not_modified' => true,
                'data' => null,
            ]);

        $documentFetcher
            ->expects($this->never())
            ->method('fetchDetail');

        $useCase = new BuildRssUseCase($documentFetcher, $stateManager, $rssGenerator);
        $result = $useCase->build($config, $state);

        $this->assertSame(0, $result['pages_changed']);
        $this->assertInstanceOf(State::class, $result['state']);
    }

    public function testGenerateRss(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcher::class);
        $stateManager = $this->createMock(StateManager::class);
        $rssGenerator = $this->createMock(RssGenerator::class);

        $config = new Config(
            stateFile: 'state.json',
            sources: [],
            channel: ['title' => 'Test Feed'],
            rss: ['enable_content_encoded' => true],
            limits: ['pages' => 10, 'items' => 50],
            concurrency: 1,
            retry: [],
            sleepBetweenRequestsMs: 0,
            saveRaw: []
        );

        $item = new DocumentItem(
            documentPath: 'test-doc',
            title: 'Test',
            description: 'Description',
            contentHash: 'hash123',
            pubDate: '2024-01-01T00:00:00+00:00',
            link: 'https://example.com/test'
        );

        $state = new State(
            version: 2,
            sources: [],
            items: [$item]
        );

        $rssGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                $this->equalTo(['title' => 'Test Feed']),
                $this->equalTo([$item]),
                $this->equalTo(true),
                $this->equalTo(50)
            )
            ->willReturn('<rss>test</rss>');

        $useCase = new BuildRssUseCase($documentFetcher, $stateManager, $rssGenerator);
        $result = $useCase->generateRss($config, $state);

        $this->assertSame('<rss>test</rss>', $result);
    }
}
