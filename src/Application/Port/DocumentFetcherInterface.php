<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Model\Source;

interface DocumentFetcherInterface
{
    /**
     * @return array<string, mixed>
     */
    public function fetchTree(Source $source, ?string $etag = null, ?string $lastModified = null): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchDetail(Source $source, string $documentPath): array;

    /**
     * @param array<int, array<string, mixed>> $treeNodes
     * @return array<int, array{path: string, update_time: int|null}>
     */
    public function extractDocumentPaths(array $treeNodes): array;
}
