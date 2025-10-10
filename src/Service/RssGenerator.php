<?php

declare(strict_types=1);

namespace TikTokShopRss\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TikTokShopRss\Model\DocumentItem;

class RssGenerator
{
    private Environment $twig;

    public function __construct(string $templateDir)
    {
        $loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($loader);
    }

    /**
     * @param array<string, mixed> $channel
     * @param array<int, DocumentItem> $items
     */
    public function generate(
        array $channel,
        array $items,
        bool $enableContentEncoded = true,
        int $limit = 50
    ): string {
        $limitedItems = array_slice($items, 0, $limit);

        $rssItems = array_map(function (DocumentItem $item) {
            return [
                'title' => $this->sanitizeXml($item->title),
                'link' => $item->link,
                'description' => $this->sanitizeHtml($item->description, 500),
                'guid' => $this->generateGuid($item->link, $item->contentHash),
                'pubDate' => $this->formatRfc822Date($item->pubDate),
                'contentHtml' => $item->contentHtml !== null ? $this->sanitizeHtml($item->contentHtml) : null,
            ];
        }, $limitedItems);

        return $this->twig->render('rss.xml.twig', [
            'channel' => $channel,
            'items' => $rssItems,
            'lastBuildDate' => $this->formatRfc822Date(date('c')),
            'enableContentEncoded' => $enableContentEncoded,
        ]);
    }

    private function sanitizeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sanitizeHtml(string $html, ?int $maxLength = null): string
    {
        $sanitized = strip_tags($html, '<p><br><a><strong><em><ul><ol><li><code><pre>');

        if ($maxLength !== null && mb_strlen($sanitized) > $maxLength) {
            $sanitized = mb_substr($sanitized, 0, $maxLength) . '...';
        }

        return $sanitized;
    }

    private function generateGuid(string $link, string $contentHash): string
    {
        return $link . '#' . substr($contentHash, 0, 8);
    }

    private function formatRfc822Date(string $isoDate): string
    {
        $timestamp = strtotime($isoDate);

        if ($timestamp === false) {
            $timestamp = time();
        }

        return date('r', $timestamp);
    }
}
