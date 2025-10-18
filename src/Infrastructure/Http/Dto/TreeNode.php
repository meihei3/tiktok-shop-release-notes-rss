<?php

declare(strict_types=1);

namespace TikTokShopRss\Infrastructure\Http\Dto;

final readonly class TreeNode
{
    /**
     * @param list<TreeNode> $children
     */
    public function __construct(
        public ?string $documentPath,
        public ?int $updateTime,
        public ?int $docType,
        public array $children = [],
    ) {
    }
}
