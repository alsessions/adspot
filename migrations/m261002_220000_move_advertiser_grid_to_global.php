<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;
use RuntimeException;

/**
 * Moves the former Advertisers index grid into the global Grid set.
 */
class m261002_220000_move_advertiser_grid_to_global extends Migration
{
    public function safeUp(): bool
    {
        $global = Craft::$app->getGlobals()->getSetByHandle('grid');
        $field = Craft::$app->getFields()->getFieldByHandle('simpleGrid')
            ?? Craft::$app->getFields()->getFieldByHandle('advertiserGrid');
        $source = Entry::find()
            ->section('advertisersIndex')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$global || !$field || !$source) {
            throw new RuntimeException('The Grid global set, Grid field, or Advertisers index is missing.');
        }

        if (!$global->getFieldLayout()->isFieldIncluded($field->handle)) {
            return true;
        }

        $grid = $global->getFieldValue($field->handle);

        if ($grid->exists()) {
            return true;
        }

        $ids = (new Query())
            ->select('elements_owners.elementId')
            ->from('{{%elements_owners}} elements_owners')
            ->innerJoin('{{%entries}} entries', '[[entries.id]] = [[elements_owners.elementId]]')
            ->where([
                'elements_owners.ownerId' => $source->id,
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
        $global->setFieldValue($field->handle, $grid);

        if (!Craft::$app->getElements()->saveElement($global)) {
            throw new RuntimeException('Unable to save the global Grid: ' . implode(', ', $global->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
