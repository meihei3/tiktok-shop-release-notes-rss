<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use TikTokShopRss\Infrastructure\Http\DocumentFetcher;
use TikTokShopRss\Model\DocumentDetail;
use TikTokShopRss\Model\DocumentPathInfo;
use TikTokShopRss\Model\Source;
use TikTokShopRss\Model\TreeResult;

class DocumentFetcherTest extends TestCase
{
    public function testFetchTreeSuccess(): void
    {
        $responseBody = json_encode([
            'data' => [
                'document_tree' => [
                    ['document_path' => '/docs/test1'],
                    ['document_path' => '/docs/test2'],
                ],
            ],
        ]);

        if ($responseBody === false) {
            $this->fail('Failed to encode JSON');
        }

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
            'response_headers' => [
                'etag' => 'etag123',
                'last-modified' => 'Mon, 01 Jan 2024 00:00:00 GMT',
            ],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/public/{document_path}'
        );

        $result = $fetcher->fetchTree($source);

        $this->assertInstanceOf(TreeResult::class, $result);
        $this->assertFalse($result->notModified);
        $this->assertIsArray($result->documentTree);
        $this->assertSame('etag123', $result->etag);
        $this->assertSame('Mon, 01 Jan 2024 00:00:00 GMT', $result->lastModified);
    }

    public function testFetchTreeNotModified(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 304,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/public/{document_path}'
        );

        $result = $fetcher->fetchTree($source, 'etag123', 'Mon, 01 Jan 2024 00:00:00 GMT');

        $this->assertInstanceOf(TreeResult::class, $result);
        $this->assertTrue($result->notModified);
        $this->assertEmpty($result->documentTree);
    }

    public function testFetchDetailSuccess(): void
    {
        $responseBody = json_encode([
            'data' => [
                'title' => 'Test Article',
                'description' => 'Test description',
                'content' => '<p>Test content</p>',
                'update_time' => 1234567890,
            ],
        ]);

        if ($responseBody === false) {
            $this->fail('Failed to encode JSON');
        }

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $source = new Source(
            treeUrl: 'https://example.com/tree',
            detailUrlTemplate: 'https://example.com/detail/{document_path}',
            publicUrlTemplate: 'https://example.com/public/{document_path}'
        );

        $result = $fetcher->fetchDetail($source, '/docs/test');

        $this->assertInstanceOf(DocumentDetail::class, $result);
        $this->assertSame('Test Article', $result->title);
        $this->assertSame('Test description', $result->description);
        $this->assertSame('<p>Test content</p>', $result->content);
        $this->assertSame(1234567890, $result->updateTime);
    }

    public function testExtractDocumentPaths(): void
    {
        $treeNodes = [
            [
                'document_path' => '/docs/test1',
            ],
            [
                'document_path' => '/docs/test2',
                'children' => [
                    ['document_path' => '/docs/test2-1'],
                    ['document_path' => '/docs/test2-2'],
                ],
            ],
            [
                'document_path' => '/docs/test3',
                'children' => [
                    [
                        'document_path' => '/docs/test3-1',
                        'children' => [
                            ['document_path' => '/docs/test3-1-1'],
                        ],
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse('', [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $paths = $fetcher->extractDocumentPaths($treeNodes);

        $this->assertCount(7, $paths);
        $this->assertContainsOnlyInstancesOf(DocumentPathInfo::class, $paths);

        $pathStrings = array_map(fn($info) => $info->path, $paths);
        $this->assertContains('/docs/test1', $pathStrings);
        $this->assertContains('/docs/test2', $pathStrings);
        $this->assertContains('/docs/test2-1', $pathStrings);
        $this->assertContains('/docs/test2-2', $pathStrings);
        $this->assertContains('/docs/test3', $pathStrings);
        $this->assertContains('/docs/test3-1', $pathStrings);
        $this->assertContains('/docs/test3-1-1', $pathStrings);
    }

    public function testExtractDocumentPathsWithEmptyArray(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $paths = $fetcher->extractDocumentPaths([]);

        $this->assertEmpty($paths);
    }

    public function testExtractDocumentPathsSkipsInvalidNodes(): void
    {
        $treeNodes = [
            ['document_path' => '/docs/test1'],
            ['title' => 'No document path'],
            ['document_path' => 123],
            ['document_path' => '/docs/test2'],
        ];

        $mockResponse = new MockResponse('', [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $fetcher = new DocumentFetcher($httpClient);

        $paths = $fetcher->extractDocumentPaths($treeNodes);

        $this->assertCount(2, $paths);
        $this->assertContainsOnlyInstancesOf(DocumentPathInfo::class, $paths);

        $pathStrings = array_map(fn($info) => $info->path, $paths);
        $this->assertContains('/docs/test1', $pathStrings);
        $this->assertContains('/docs/test2', $pathStrings);
    }
}
