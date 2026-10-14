<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use RuntimeException;

/**
 * Moves legacy Page Image relations to Index Image.
 */
class m261013_121000_migrate_page_hero_images extends Migration
{
    public function safeUp(): bool
    {
        $pageImageField = Craft::$app->getFields()->getFieldByHandle('pageImage');
        $indexImageField = Craft::$app->getFields()->getFieldByHandle('indexImage');

        if (!$pageImageField || !$indexImageField) {
            return true;
        }

        $entries = Entry::find()
            ->section(['base', 'blog', 'contact'])
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->all();

        foreach ($entries as $entry) {
            $imageIds = (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
                ->where([
                    'sourceId' => $entry->id,
                    'fieldId' => $pageImageField->id,
                ])
                ->orderBy(['sortOrder' => SORT_ASC])
                ->column();

            if (!$imageIds) {
                continue;
            }

            $entry->setFieldValue($indexImageField->handle, $imageIds);
            $entry->forceSave = true;

            if (!Craft::$app->getElements()->saveElement($entry)) {
                throw new RuntimeException('Unable to migrate the hero image: ' . implode(', ', $entry->getErrorSummary(true)));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
