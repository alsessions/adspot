<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use RuntimeException;

/**
 * Moves the Venues benefit rows from floated CKEditor images into Image + Text blocks.
 */
class m261022_120000_migrate_venue_benefits_to_image_text extends Migration
{
    public function safeUp(): bool
    {
        $pageContent = Craft::$app->getFields()->getFieldByHandle('pageContent');

        if (!$pageContent) {
            throw new RuntimeException('The Page Content field is missing.');
        }

        $page = Entry::find()
            ->type('page')
            ->uri('venues')
            ->siteId(Craft::$app->getSites()->getPrimarySite()->id)
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$page) {
            return true;
        }

        $blocks = [];
        $newBlock = 0;
        $changed = false;

        foreach ($page->getFieldValue($pageContent->handle)->all() as $block) {
            if ($block->type->handle !== 'bodyTextBlock') {
                $blocks[(string)$block->id] = $this->serializeBlock($block);
                continue;
            }

            $content = (string)$block->bodyText;
            $pairs = [];
            $content = preg_replace_callback(
                '~(<p\b[^>]*>(?:(?!</p>).)*</p>)\s*(<figure\b[^>]*class="[^"]*\bimage-style-align-(?:left|right)\b[^"]*"[^>]*>(?:(?!</figure>).)*</figure>)\s*(<p\b[^>]*>(?:(?!</p>).)*</p>)~is',
                function(array $matches) use (&$pairs, $page): string {
                    if ($this->isEmptyHtml($matches[1]) || $this->isEmptyHtml($matches[3])) {
                        return $matches[0];
                    }

                    $assetId = $this->assetId($matches[2], $page->siteId);

                    if (!$assetId) {
                        return $matches[0];
                    }

                    $pairs[] = [
                        'assetId' => $assetId,
                        'bodyText' => $matches[1] . $matches[3],
                    ];

                    return '<!--IMAGE_TEXT_BLOCK:' . (count($pairs) - 1) . '-->';
                },
                $content,
            );

            if (!$pairs) {
                $blocks[(string)$block->id] = $this->serializeBlock($block);
                continue;
            }

            $changed = true;
            $content = preg_replace('~<p\b[^>]*>(?:\s|&nbsp;|\x{00a0}|<br\s*/?>)*</p>~iu', '', $content);
            $parts = preg_split('~<!--IMAGE_TEXT_BLOCK:(\d+)-->~', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($parts as $index => $part) {
                if ($index % 2 === 0) {
                    if (!$this->isEmptyHtml($part)) {
                        $blocks['new' . ++$newBlock] = [
                            'type' => 'bodyTextBlock',
                            'fields' => ['bodyText' => $part],
                        ];
                    }
                    continue;
                }

                $pair = $pairs[(int)$part];
                $blocks['new' . ++$newBlock] = [
                    'type' => 'imageTextBlock',
                    'fields' => [
                        'pageImages' => [$pair['assetId']],
                        'bodyText' => $pair['bodyText'],
                    ],
                ];
            }
        }

        if (!$changed) {
            return true;
        }

        $page->setFieldValue($pageContent->handle, $blocks);
        $page->forceSave = true;

        if (!Craft::$app->getElements()->saveElement($page)) {
            throw new RuntimeException('Unable to migrate the Venues benefit rows: ' . implode(', ', $page->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261022_120000_migrate_venue_benefits_to_image_text cannot be reverted.\n";
        return false;
    }

    private function serializeBlock(Entry $block): array
    {
        return [
            'type' => $block->type->handle,
            'fields' => $block->getSerializedFieldValues(),
        ];
    }

    private function isEmptyHtml(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        return trim(str_replace("\u{00a0}", '', $text)) === '';
    }

    private function assetId(string $figure, int $siteId): ?int
    {
        if (preg_match('~\{asset:(\d+):~', $figure, $matches)) {
            return (int)$matches[1];
        }

        if (!preg_match('~\bsrc="([^"]+)"~i', $figure, $matches)) {
            return null;
        }

        $filename = basename(parse_url(html_entity_decode($matches[1]), PHP_URL_PATH));
        $asset = Asset::find()->filename(urldecode($filename))->siteId($siteId)->one();

        return $asset?->id;
    }
}
