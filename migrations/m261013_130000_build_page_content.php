<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;
use RuntimeException;

/**
 * Moves Page text and grids into the ordered Page Content field.
 */
class m261013_130000_build_page_content extends Migration
{
    private const LEGACY_BODY_LAYOUT_UID = 'b82edbf3-bc80-47b7-92ac-cfb1c542c5f4';

    public function safeUp(): bool
    {
        $pageContent = Craft::$app->getFields()->getFieldByHandle('pageContent');
        $simpleGrid = Craft::$app->getFields()->getFieldByHandle('simpleGrid');

        if (!$pageContent || !$simpleGrid) {
            throw new RuntimeException('The Page Content or Simple Grid field is missing.');
        }

        $pages = Entry::find()
            ->type('page')
            ->siteId(Craft::$app->getSites()->getPrimarySite()->id)
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->all();

        foreach ($pages as $page) {
            if ($page->getFieldValue($pageContent->handle)->exists()) {
                continue;
            }

            $content = (new Query())
                ->select('content')
                ->from('{{%elements_sites}}')
                ->where(['elementId' => $page->id, 'siteId' => $page->siteId])
                ->scalar();
            $content = is_string($content) ? json_decode($content, true) : $content;
            $bodyText = $content[self::LEGACY_BODY_LAYOUT_UID] ?? null;

            $gridIds = (new Query())
                ->select('elements_owners.elementId')
                ->from('{{%elements_owners}} elements_owners')
                ->innerJoin('{{%entries}} entries', '[[entries.id]] = [[elements_owners.elementId]]')
                ->where([
                    'elements_owners.ownerId' => $page->id,
                    'entries.fieldId' => $simpleGrid->id,
                ])
                ->orderBy(['elements_owners.sortOrder' => SORT_ASC])
                ->column();

            $blocks = [];

            if (is_string($bodyText) && trim($bodyText) !== '') {
                $blocks['new1'] = [
                    'type' => 'bodyTextBlock',
                    'fields' => ['bodyText' => $bodyText],
                ];
            }

            if ($gridIds) {
                $gridItems = Entry::find()
                    ->id($gridIds)
                    ->siteId($page->siteId)
                    ->status(null)
                    ->trashed(null)
                    ->fixedOrder()
                    ->all();
                $serializedItems = [];

                foreach ($gridItems as $index => $item) {
                    $serializedItems['new' . ($index + 1)] = [
                        'type' => 'featureCard',
                        'fields' => $item->getSerializedFieldValues(),
                    ];
                }

                if ($serializedItems) {
                    $blocks['new' . (count($blocks) + 1)] = [
                        'type' => 'simpleGridBlock',
                        'fields' => ['simpleGrid' => $serializedItems],
                    ];
                }
            }

            if (!$blocks) {
                continue;
            }

            $page->setFieldValue($pageContent->handle, $blocks);
            $page->forceSave = true;

            if (!Craft::$app->getElements()->saveElement($page)) {
                throw new RuntimeException("Unable to migrate Page content for {$page->title}: " . implode(', ', $page->getErrorSummary(true)));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261013_130000_build_page_content cannot be reverted.\n";
        return false;
    }
}
