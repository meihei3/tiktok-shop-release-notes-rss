<?php

declare(strict_types=1);

namespace TikTokShopRss\Infrastructure\Http\Dto;

readonly class TreeResult
{
    /**
     * @param list<TreeNode> $documentTree
     */
    public function __construct(
        public bool $notModified,
        public array $documentTree,
        public ?string $etag,
        public ?string $lastModified,
    ) {
    }
}
