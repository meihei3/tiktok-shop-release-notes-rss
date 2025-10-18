<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Application\Dto\DocumentPathInfo;
use TikTokShopRss\Infrastructure\Http\Dto\DocumentDetail;
use TikTokShopRss\Infrastructure\Http\Dto\TreeResult;
use TikTokShopRss\Model\Source;

interface DocumentFetcherInterface
{
    public function fetchTree(Source $source, ?string $etag = null, ?string $lastModified = null): TreeResult;

    public function fetchDetail(Source $source, string $documentPath): DocumentDetail;

    /**
     * @param list<\TikTokShopRss\Infrastructure\Http\Dto\TreeNode> $treeNodes
     * @return list<DocumentPathInfo>
     */
    public function extractDocumentPaths(array $treeNodes): array;
}
