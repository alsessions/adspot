<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\migrations\BaseEntryTypeMergeMigration;
use craft\records\EntryType as EntryTypeRecord;
use RuntimeException;

/**
 * Consolidates general content into the Page entry type.
 */
class m261013_120000_consolidate_page_entry_types extends BaseEntryTypeMergeMigration
{
    public string $persistingEntryTypeUid = '8f52b621-39cc-48d2-b870-890301665097';
    public string $outgoingEntryTypeUid = '';
    public array $layoutElementUidMap = [
        '9e747d9f-8c23-47e9-9a5f-b3a7b2acff5a' => '28d840cb-b9a1-484f-b330-e0bd0745dfd3',
        '6258982f-e926-4342-860d-7b4e4ccf8ab4' => 'b82edbf3-bc80-47b7-92ac-cfb1c542c5f4',
    ];

    public function safeUp(): bool
    {
        $entryIds = [];

        foreach ([
            'be0d1ca8-c22c-4404-9565-2356c6520f02',
            'eb47da8d-287d-4d74-97d8-59ff69eaa2e6',
        ] as $entryTypeUid) {
            $entryType = EntryTypeRecord::findWithTrashed()
                ->where(['uid' => $entryTypeUid])
                ->one();

            if (!$entryType) {
                continue;
            }

            $entryIds = array_merge($entryIds, (new Query())
                ->select('entries.id')
                ->from(['entries' => Table::ENTRIES])
                ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[entries.id]]')
                ->where([
                    'entries.typeId' => $entryType->id,
                    'elements.canonicalId' => null,
                    'elements.revisionId' => null,
                ])
                ->column());

            $this->outgoingEntryTypeUid = $entryTypeUid;

            if (!parent::safeUp()) {
                return false;
            }
        }

        $pageImageField = Craft::$app->getFields()->getFieldByHandle('pageImage');

        foreach (array_unique($entryIds) as $entryId) {
            $entry = Entry::find()
                ->id($entryId)
                ->status(null)
                ->drafts(false)
                ->revisions(false)
                ->trashed(null)
                ->one();

            if (!$entry) {
                continue;
            }

            $imageIds = $pageImageField ? (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
                ->where([
                    'sourceId' => $entry->id,
                    'fieldId' => $pageImageField->id,
                ])
                ->orderBy(['sortOrder' => SORT_ASC])
                ->column() : [];

            if (!$imageIds) {
                continue;
            }

            $entry->setFieldValue('indexImage', $imageIds);
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
