<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use RuntimeException;

/**
 * m261002_171736_separate_home_hero migration.
 */
class m261002_171736_separate_home_hero extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $homePage = $entries->getEntryTypeByHandle('homePage');
        $homeContent = $fields->getFieldByHandle('homeContent');
        $contentSection = $entries->getEntryTypeByHandle('contentSection');
        $mainContent = $entries->getEntryTypeByHandle('mainContent');

        if (!$homePage || !$homeContent || !$contentSection) {
            throw new RuntimeException('The homepage content schema is missing.');
        }

        $heroFieldHandles = [
            'eyebrow',
            'heading',
            'headingAccent',
            'bodyCopy',
            'primaryCtaLabel',
            'primaryCtaUrl',
            'secondaryEyebrow',
            'secondaryHeading',
            'secondaryBody',
            'secondaryCta',
        ];
        $heroFields = [];
        foreach ($heroFieldHandles as $handle) {
            $heroFields[$handle] = $fields->getFieldByHandle($handle);
            if (!$heroFields[$handle]) {
                throw new RuntimeException("The $handle field is missing.");
            }
        }

        $fieldElements = static fn(array $layoutFields): array => array_map(
            fn($field) => [
                'type' => CustomField::class,
                'fieldUid' => $field->uid,
                'required' => true,
            ],
            $layoutFields,
        );

        $homePage->setFieldLayout(FieldLayout::createFromConfig([
            'type' => Entry::class,
            'tabs' => [
                [
                    'name' => 'Hero',
                    'elements' => $fieldElements(array_values($heroFields)),
                ],
                [
                    'name' => 'Sections',
                    'elements' => $fieldElements([$homeContent]),
                ],
            ],
        ]));
        if (!$entries->saveEntryType($homePage)) {
            throw new RuntimeException('Unable to update the Home Page entry type.');
        }

        $home = Entry::find()->section('home')->status(null)->one();
        if (!$home) {
            throw new RuntimeException('The Home entry is missing.');
        }

        $blocks = $home->getFieldValue('homeContent')->status(null)->all();
        $hero = null;
        $sectionIds = [];
        foreach ($blocks as $block) {
            if ($block->type->handle === 'mainContent' && !$hero) {
                $hero = $block;
            } elseif ($block->type->handle === 'contentSection') {
                $sectionIds[] = $block->id;
            }
        }

        if ($hero) {
            foreach ($heroFieldHandles as $handle) {
                $home->setFieldValue($handle, $hero->getFieldValue($handle));
            }
            $home->setFieldValue('homeContent', ['sortOrder' => $sectionIds]);

            if (!Craft::$app->getElements()->saveElement($home)) {
                throw new RuntimeException('Unable to move the hero content: ' . implode(', ', $home->getErrorSummary(true)));
            }
        }

        $homeContent->name = 'Sections';
        $homeContent->setEntryTypes([$contentSection]);
        if (!$fields->saveField($homeContent)) {
            throw new RuntimeException('Unable to update the Sections field.');
        }

        if ($mainContent && !$entries->deleteEntryType($mainContent)) {
            throw new RuntimeException('Unable to remove the Main Content entry type.');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261002_171736_separate_home_hero cannot be reverted.\n";
        return false;
    }
}
