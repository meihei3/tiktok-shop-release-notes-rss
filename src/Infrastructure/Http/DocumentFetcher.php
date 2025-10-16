<?php

declare(strict_types=1);

namespace TikTokShopRss\Infrastructure\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use TikTokShopRss\Model\Source;

use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function str_replace;

class DocumentFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTree(Source $source, ?string $etag = null, ?string $lastModified = null): array
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
            return [
                'not_modified' => true,
                'data' => null,
            ];
        }

        if ($statusCode !== 200) {
            throw new \RuntimeException("Failed to fetch tree: HTTP {$statusCode}");
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response from tree API");
        }

        return [
            'not_modified' => false,
            'data' => $data,
            'etag' => $response->getHeaders()['etag'][0] ?? null,
            'last_modified' => $response->getHeaders()['last-modified'][0] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchDetail(Source $source, string $documentPath): array
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

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $treeNodes
     * @return array<int, array{path: string, update_time: int|null}>
     */
    public function extractDocumentPaths(array $treeNodes): array
    {
        $paths = [];

        foreach ($treeNodes as $node) {
            if (isset($node['document_path']) && is_string($node['document_path']) && $node['document_path'] !== '') {
                $paths[] = [
                    'path' => $node['document_path'],
                    'update_time' => isset($node['update_time']) ? (int) $node['update_time'] : null,
                ];
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $childPaths = $this->extractDocumentPaths($node['children']);
                $paths = array_merge($paths, $childPaths);
            }
        }

        return $paths;
    }
}
