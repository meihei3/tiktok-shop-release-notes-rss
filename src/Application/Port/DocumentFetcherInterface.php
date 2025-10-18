<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Model\DocumentDetail;
use TikTokShopRss\Model\DocumentPathInfo;
use TikTokShopRss\Model\Source;
use TikTokShopRss\Model\TreeResult;

interface DocumentFetcherInterface
{
    public function fetchTree(Source $source, ?string $etag = null, ?string $lastModified = null): TreeResult;

    public function fetchDetail(Source $source, string $documentPath): DocumentDetail;

    /**
     * @param array<int, array<string, mixed>> $treeNodes
     * @return list<DocumentPathInfo>
     */
    public function extractDocumentPaths(array $treeNodes): array;
}
