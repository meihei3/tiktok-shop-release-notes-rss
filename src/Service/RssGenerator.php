<?php

declare(strict_types=1);

namespace TikTokShopRss\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Application\Port\RssGeneratorInterface;
use TikTokShopRss\Model\DocumentItem;

use function array_map;
use function array_slice;
use function date;
use function htmlspecialchars;
use function mb_strlen;
use function mb_substr;
use function strip_tags;
use function strtotime;
use function substr;
use function time;
use function usort;

class RssGenerator implements RssGeneratorInterface
{
    private Environment $twig;

    public function __construct(string $templateDir)
    {
        $loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($loader);
    }

    /**
     * @param list<DocumentItem> $items
     */
    public function generate(
        ChannelConfig $channel,
        array $items,
        bool $enableContentEncoded = true,
        int $limit = 50
    ): string {
        // Sort by pubDate descending (newest first)
        $sortedItems = $items;
        usort($sortedItems, function (DocumentItem $a, DocumentItem $b): int {
            $timeA = strtotime($a->pubDate);
            $timeB = strtotime($b->pubDate);

            if ($timeA === false) {
                $timeA = 0;
            }
            if ($timeB === false) {
                $timeB = 0;
            }

            return $timeB <=> $timeA; // Descending order
        });

        $limitedItems = array_slice($sortedItems, 0, $limit);

        $rssItems = array_map(function (DocumentItem $item) {
            return [
                'title' => $this->sanitizeXml($item->title),
                'link' => $item->link,
                'description' => $this->sanitizeHtml($item->description, 500),
                'keywords' => $item->keywords,
                'guid' => $this->generateGuid($item->documentPath, $item->contentHash),
                'pubDate' => $this->formatRfc822Date($item->pubDate),
                'contentHtml' => $item->description !== '' ? $this->sanitizeHtml($item->description) : null,
            ];
        }, $limitedItems);

        return $this->twig->render('rss.xml.twig', [
            'channel' => [
                'title' => $channel->title,
                'link' => $channel->link,
                'description' => $channel->description,
                'language' => $channel->language,
            ],
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
        if ($maxLength !== null) {
            // For description field with length limit, use plain text only
            $plainText = strip_tags($html);
            if (mb_strlen($plainText) > $maxLength) {
                return mb_substr($plainText, 0, $maxLength) . '...';
            }
            return $plainText;
        }

        // For content:encoded, allow HTML tags
        return strip_tags($html, '<p><br><a><strong><em><ul><ol><li><code><pre>');
    }

    private function generateGuid(string $documentPath, string $contentHash): string
    {
        return $documentPath . '#' . substr($contentHash, 0, 8);
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
