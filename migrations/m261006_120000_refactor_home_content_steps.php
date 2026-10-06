<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use RuntimeException;

/**
 * Refactors the homepage Matrix field into three How It Works steps.
 */
class m261006_120000_refactor_home_content_steps extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $homeContent = $fields->getFieldByHandle('homeContent');
        $stepType = $entries->getEntryTypeByHandle('contentSection')
            ?? $entries->getEntryTypeByHandle('howItWorksStep');
        $homePage = $entries->getEntryTypeByHandle('homePage');
        $eyebrow = $fields->getFieldByHandle('eyebrow');
        $heading = $fields->getFieldByHandle('heading');
        $bodyCopy = $fields->getFieldByHandle('bodyCopy');

        if (!$homeContent || !$stepType || !$homePage || !$eyebrow || !$heading || !$bodyCopy) {
            throw new RuntimeException('The homepage content schema is incomplete.');
        }

        $stepType->name = 'How It Works Step';
        $stepType->handle = 'howItWorksStep';
        $stepType->titleFormat = '{eyebrow}: {heading}';
        $stepType->uiLabelFormat = '{eyebrow}: {heading}';
        $stepType->setFieldLayout(FieldLayout::createFromConfig([
            'type' => Entry::class,
            'tabs' => [[
                'name' => 'Step',
                'elements' => [
                    [
                        'type' => CustomField::class,
                        'fieldUid' => $eyebrow->uid,
                        'label' => 'Step Label',
                        'instructions' => 'For example, Step 1.',
                        'required' => true,
                    ],
                    [
                        'type' => CustomField::class,
                        'fieldUid' => $heading->uid,
                        'label' => 'Step Heading',
                        'required' => true,
                    ],
                    [
                        'type' => CustomField::class,
                        'fieldUid' => $bodyCopy->uid,
                        'label' => 'Step Copy',
                        'required' => true,
                    ],
                ],
            ]],
        ]));

        if (!$entries->saveEntryType($stepType)) {
            throw new RuntimeException('Unable to update the How It Works entry type.');
        }

        $homeContent->name = 'How It Works';
        $homeContent->instructions = 'Add the three steps shown in the How It Works section.';
        $homeContent->createButtonLabel = 'Add step';
        $homeContent->minEntries = 3;
        $homeContent->maxEntries = 3;
        $homeContent->setEntryTypes([$stepType]);

        if (!$fields->saveField($homeContent)) {
            throw new RuntimeException('Unable to update the How It Works field.');
        }

        $homeLayout = $homePage->getFieldLayout();
        foreach ($homeLayout->getTabs() as $tab) {
            if ($tab->name === 'Sections') {
                $tab->name = 'How It Works';
            }
        }
        $homePage->setFieldLayout($homeLayout);

        if (!$entries->saveEntryType($homePage)) {
            throw new RuntimeException('Unable to update the Home Page field layout.');
        }

        $home = Entry::find()
            ->section('home')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$home) {
            throw new RuntimeException('The Home entry is missing.');
        }

        $home->setFieldValue('homeContent', [
            'new1' => [
                'type' => 'howItWorksStep',
                'fields' => [
                    'eyebrow' => 'Step 1',
                    'heading' => 'Select Markets',
                    'bodyCopy' => 'Choose between cities, venue types, and demographics. Plenty of ZIP codes from Upstate to NYC.',
                ],
            ],
            'new2' => [
                'type' => 'howItWorksStep',
                'fields' => [
                    'eyebrow' => 'Step 2',
                    'heading' => 'Created & Placed',
                    'bodyCopy' => 'Send your artwork or let our team design it for an additional fee. Your campaign launches in selected venues.',
                ],
            ],
            'new3' => [
                'type' => 'howItWorksStep',
                'fields' => [
                    'eyebrow' => 'Step 3',
                    'heading' => 'Verified Results',
                    'bodyCopy' => 'Reporting that rises above the noise. Geopath-backed impression reports, with real people, real places, and no inflated numbers. Excelsior.',
                ],
            ],
        ]);

        if (!Craft::$app->getElements()->saveElement($home)) {
            throw new RuntimeException('Unable to save the How It Works steps: ' . implode(', ', $home->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261006_120000_refactor_home_content_steps cannot be reverted.\n";
        return false;
    }
}
