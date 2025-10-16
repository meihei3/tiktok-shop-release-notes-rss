<?php

declare(strict_types=1);

namespace TikTokShopRss\Tests\Unit\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use TikTokShopRss\Infrastructure\Http\DocumentFetcher;
use TikTokShopRss\Model\Source;

class DocumentFetcherTest extends TestCase
{
    public function testFetchTreeSuccess(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['document_path' => '/docs/test1'],
                ['document_path' => '/docs/test2'],
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

        $this->assertFalse($result['not_modified']);
        $this->assertIsArray($result['data']);
        $this->assertSame('etag123', $result['etag']);
        $this->assertSame('Mon, 01 Jan 2024 00:00:00 GMT', $result['last_modified']);
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

        $this->assertTrue($result['not_modified']);
        $this->assertNull($result['data']);
    }

    public function testFetchDetailSuccess(): void
    {
        $responseBody = json_encode([
            'data' => [
                'title' => 'Test Article',
                'description' => 'Test description',
                'content_html' => '<p>Test content</p>',
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame('Test Article', $result['data']['title']);
        $this->assertSame('Test description', $result['data']['description']);
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
        $pathStrings = array_column($paths, 'path');
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
        $pathStrings = array_column($paths, 'path');
        $this->assertContains('/docs/test1', $pathStrings);
        $this->assertContains('/docs/test2', $pathStrings);
    }
}
