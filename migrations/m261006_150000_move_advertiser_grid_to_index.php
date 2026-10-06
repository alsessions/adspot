<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;
use RuntimeException;

/**
 * Moves the advertiser grid from the global set back to the Advertisers index.
 */
class m261006_150000_move_advertiser_grid_to_index extends Migration
{
    public function safeUp(): bool
    {
        $entry = Entry::find()
            ->section('advertisersIndex')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();
        $global = Craft::$app->getGlobals()->getSetByHandle('grid');
        $field = Craft::$app->getFields()->getFieldByHandle('advertiserGrid');

        if (!$entry || !$global || !$field) {
            throw new RuntimeException('The Advertisers index, Grid global set, or advertiserGrid field is missing.');
        }

        $grid = $entry->getFieldValue('advertiserGrid');

        if ($grid->exists()) {
            return true;
        }

        $ids = (new Query())
            ->select('elements_owners.elementId')
            ->from('{{%elements_owners}} elements_owners')
            ->innerJoin('{{%entries}} entries', '[[entries.id]] = [[elements_owners.elementId]]')
            ->where([
                'elements_owners.ownerId' => $global->id,
                'entries.fieldId' => $field->id,
            ])
            ->orderBy(['elements_owners.sortOrder' => SORT_ASC])
            ->column();

        if (!$ids) {
            return true;
        }

        $items = Entry::find()
            ->id($ids)
            ->status(null)
            ->trashed(null)
            ->fixedOrder()
            ->all();

        foreach ($items as $item) {
            $item->forceSave = true;
        }

        $grid->setCachedResult($items);
        $entry->setFieldValue('advertiserGrid', $grid);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new RuntimeException('Unable to save the advertiser grid: ' . implode(', ', $entry->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
