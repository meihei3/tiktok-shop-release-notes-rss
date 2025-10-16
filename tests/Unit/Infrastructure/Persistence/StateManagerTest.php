<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\SourceState;
use TikTokShopRss\Model\State;
use TikTokShopRss\Infrastructure\Persistence\StateManager;

class StateManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ttsrss_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } elseif (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    public function testFindItemByDocumentPath(): void
    {
        $manager = new StateManager();

        $items = [
            new DocumentItem('path1', 'Title 1', 'Desc 1', 'hash1', '2025-01-01', 'http://example.com/1'),
            new DocumentItem('path2', 'Title 2', 'Desc 2', 'hash2', '2025-01-02', 'http://example.com/2'),
        ];

        $found = $manager->findItemByDocumentPath($items, 'path1');
        $this->assertNotNull($found);
        $this->assertSame('path1', $found->documentPath);

        $notFound = $manager->findItemByDocumentPath($items, 'path3');
        $this->assertNull($notFound);
    }

    public function testMergeItems(): void
    {
        $manager = new StateManager();

        $existing = [
            new DocumentItem('path1', 'Title 1', 'Desc 1', 'hash1', '2025-01-01', 'http://example.com/1'),
            new DocumentItem('path2', 'Title 2', 'Desc 2', 'hash2', '2025-01-02', 'http://example.com/2'),
        ];

        $new = [
            new DocumentItem(
                'path1',
                'Updated Title 1',
                'Updated Desc 1',
                'hash1-new',
                '2025-01-03',
                'http://example.com/1'
            ),
            new DocumentItem('path3', 'Title 3', 'Desc 3', 'hash3', '2025-01-04', 'http://example.com/3'),
        ];

        $merged = $manager->mergeItems($existing, $new);

        $this->assertCount(3, $merged);

        $item1 = $manager->findItemByDocumentPath($merged, 'path1');
        $this->assertNotNull($item1);
        $this->assertSame('Updated Title 1', $item1->title);

        $item2 = $manager->findItemByDocumentPath($merged, 'path2');
        $this->assertNotNull($item2);
        $this->assertSame('Title 2', $item2->title);

        $item3 = $manager->findItemByDocumentPath($merged, 'path3');
        $this->assertNotNull($item3);
        $this->assertSame('Title 3', $item3->title);
    }

    public function testLoadNonExistentFileReturnsEmptyState(): void
    {
        $manager = new StateManager();
        $filePath = $this->tempDir . '/nonexistent.json';

        $state = $manager->load($filePath);

        $this->assertSame(2, $state->version);
        $this->assertEmpty($state->sources);
        $this->assertEmpty($state->items);
    }

    public function testSaveAndLoadState(): void
    {
        $manager = new StateManager();
        $filePath = $this->tempDir . '/state.json';

        $sources = [
            new SourceState(
                url: 'https://example.com/tree',
                etag: 'etag123',
                lastModified: 'Mon, 01 Jan 2024 00:00:00 GMT',
                contentHash: 'hash123',
                lastSeenAt: '2024-01-01T00:00:00+00:00'
            ),
        ];

        $items = [
            new DocumentItem(
                documentPath: '/docs/test',
                title: 'Test Article',
                description: 'Test description',
                contentHash: 'abc123',
                pubDate: '2024-01-01T00:00:00+00:00',
                link: 'https://example.com/test',
            ),
        ];

        $state = new State(
            version: 2,
            sources: $sources,
            items: $items
        );

        $manager->save($filePath, $state);

        $this->assertFileExists($filePath);

        $loadedState = $manager->load($filePath);

        $this->assertSame(2, $loadedState->version);
        $this->assertCount(1, $loadedState->sources);
        $this->assertCount(1, $loadedState->items);

        $this->assertSame('https://example.com/tree', $loadedState->sources[0]->url);
        $this->assertSame('etag123', $loadedState->sources[0]->etag);
        $this->assertSame('/docs/test', $loadedState->items[0]->documentPath);
        $this->assertSame('Test Article', $loadedState->items[0]->title);
    }

    public function testSaveCreatesDirectory(): void
    {
        $manager = new StateManager();
        $filePath = $this->tempDir . '/subdir/state.json';

        $state = new State(version: 2, sources: [], items: []);
        $manager->save($filePath, $state);

        $this->assertFileExists($filePath);
        $this->assertDirectoryExists($this->tempDir . '/subdir');
    }
}
