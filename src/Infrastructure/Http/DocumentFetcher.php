<?php

declare(strict_types=1);

namespace TikTokShopRss\Infrastructure\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use TikTokShopRss\Application\Dto\DocumentPathInfo;
use TikTokShopRss\Application\Port\DocumentFetcherInterface;
use TikTokShopRss\Infrastructure\Http\Dto\DocumentDetail;
use TikTokShopRss\Infrastructure\Http\Dto\TreeResult;
use TikTokShopRss\Model\Source;

use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function str_replace;

class DocumentFetcher implements DocumentFetcherInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetchTree(Source $source, ?string $etag = null, ?string $lastModified = null): TreeResult
    {
        $headers = [];

        if ($etag !== null) {
            $headers['If-None-Match'] = $etag;
        }

        if ($lastModified !== null) {
            $headers['If-Modified-Since'] = $lastModified;
        }

        $options = [];
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        $response = $this->httpClient->request('GET', $source->treeUrl, $options);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 304) {
            return new TreeResult(
                notModified: true,
                documentTree: [],
                etag: null,
                lastModified: null,
            );
        }

        if ($statusCode !== 200) {
            throw new \RuntimeException("Failed to fetch tree: HTTP {$statusCode}");
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response from tree API");
        }

        return new TreeResult(
            notModified: false,
            documentTree: $data['data']['document_tree'] ?? [],
            etag: $response->getHeaders()['etag'][0] ?? null,
            lastModified: $response->getHeaders()['last-modified'][0] ?? null,
        );
    }

    public function fetchDetail(Source $source, string $documentPath): DocumentDetail
    {
        $url = str_replace('{document_path}', $documentPath, $source->detailUrlTemplate);

        $response = $this->httpClient->request('GET', $url);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new \RuntimeException("Failed to fetch detail for {$documentPath}: HTTP {$statusCode}");
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response from detail API for {$documentPath}");
        }

        return new DocumentDetail(
            title: $data['data']['title'] ?? 'Untitled',
            content: $data['data']['content'] ?? '',
            description: $data['data']['description'] ?? '',
            updateTime: isset($data['data']['update_time']) ? (int) $data['data']['update_time'] : null,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $treeNodes
     * @return list<DocumentPathInfo>
     */
    public function extractDocumentPaths(array $treeNodes): array
    {
        $paths = [];

        foreach ($treeNodes as $node) {
            if (isset($node['document_path']) && is_string($node['document_path']) && $node['document_path'] !== '') {
                $paths[] = new DocumentPathInfo(
                    path: $node['document_path'],
                    updateTime: isset($node['update_time']) ? (int) $node['update_time'] : null,
                );
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $childPaths = $this->extractDocumentPaths($node['children']);
                $paths = array_merge($paths, $childPaths);
            }
        }

        return $paths;
    }
}
