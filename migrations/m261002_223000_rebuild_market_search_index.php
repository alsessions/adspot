<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Adds market address values to Craft's search index.
 */
class m261002_223000_rebuild_market_search_index extends Migration
{
    public function safeUp(): bool
    {
        $markets = Entry::find()
            ->section('markets')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->all();

        foreach ($markets as $market) {
            Craft::$app->getSearch()->indexElementAttributes($market);
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
