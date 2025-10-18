<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use TikTokShopRss\Application\Dto\BuildResult;
use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Application\Dto\DocumentPathInfo;
use TikTokShopRss\Application\Port\DocumentFetcherInterface;
use TikTokShopRss\Application\Port\RssGeneratorInterface;
use TikTokShopRss\Application\Port\StateManagerInterface;
use TikTokShopRss\Application\UseCase\BuildRssUseCase;
use TikTokShopRss\Infrastructure\Http\Dto\DocumentDetail;
use TikTokShopRss\Infrastructure\Http\Dto\TreeNode;
use TikTokShopRss\Infrastructure\Http\Dto\TreeResult;
use TikTokShopRss\Model\Config;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\Source;
use TikTokShopRss\Model\State;

class BuildRssUseCaseTest extends TestCase
{
    public function testBuildWithNewDocument(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcherInterface::class);
        $stateManager = $this->createMock(StateManagerInterface::class);
        $rssGenerator = $this->createMock(RssGeneratorInterface::class);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/doc/{document_path}'
        );

        $config = new Config(
            stateFile: 'state.json',
            sources: [$source],
            channel: new ChannelConfig('Test', 'https://example.com', 'Test Feed'),
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
            ->willReturn(new TreeResult(
                notModified: false,
                documentTree: [
                    new TreeNode(
                        documentPath: 'test-doc',
                        updateTime: 1234567890,
                        children: []
                    ),
                ],
                etag: 'etag123',
                lastModified: 'Mon, 01 Jan 2024 00:00:00 GMT',
            ));

        $documentFetcher
            ->expects($this->once())
            ->method('extractDocumentPaths')
            ->willReturn([
                new DocumentPathInfo(path: 'test-doc', updateTime: 1234567890),
            ]);

        $documentFetcher
            ->expects($this->once())
            ->method('fetchDetail')
            ->willReturn(new DocumentDetail(
                title: 'Test Document',
                content: 'Test content',
                description: 'Test description',
                updateTime: 1234567890,
            ));

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

        $this->assertInstanceOf(BuildResult::class, $result);
        $this->assertSame(1, $result->pagesChanged);
        $this->assertInstanceOf(State::class, $result->state);
        $this->assertCount(1, $result->state->items);
        $this->assertSame('test-doc', $result->state->items[0]->documentPath);
        $this->assertSame('Test Document', $result->state->items[0]->title);
    }

    public function testBuildWithNotModifiedTree(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcherInterface::class);
        $stateManager = $this->createMock(StateManagerInterface::class);
        $rssGenerator = $this->createMock(RssGeneratorInterface::class);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/doc/{document_path}'
        );

        $config = new Config(
            stateFile: 'state.json',
            sources: [$source],
            channel: new ChannelConfig('Test', 'https://example.com', 'Test Feed'),
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
            ->willReturn(new TreeResult(
                notModified: true,
                documentTree: [],
                etag: null,
                lastModified: null,
            ));

        $documentFetcher
            ->expects($this->never())
            ->method('fetchDetail');

        $useCase = new BuildRssUseCase($documentFetcher, $stateManager, $rssGenerator);
        $result = $useCase->build($config, $state);

        $this->assertInstanceOf(BuildResult::class, $result);
        $this->assertSame(0, $result->pagesChanged);
        $this->assertInstanceOf(State::class, $result->state);
    }

    public function testGenerateRss(): void
    {
        $documentFetcher = $this->createMock(DocumentFetcherInterface::class);
        $stateManager = $this->createMock(StateManagerInterface::class);
        $rssGenerator = $this->createMock(RssGeneratorInterface::class);

        $config = new Config(
            stateFile: 'state.json',
            sources: [],
            channel: new ChannelConfig('Test Feed', 'https://example.com', 'Test Feed'),
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
                $this->isInstanceOf(ChannelConfig::class),
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
