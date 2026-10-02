<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use RuntimeException;

/**
 * m261002_170400_create_home_page_content migration.
 */
class m261002_170400_create_home_page_content extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        $fieldConfigs = [
            'eyebrow' => ['name' => 'Eyebrow'],
            'heading' => ['name' => 'Heading'],
            'headingAccent' => ['name' => 'Heading Accent'],
            'bodyCopy' => ['name' => 'Body Copy', 'multiline' => true, 'initialRows' => 4],
            'primaryCtaLabel' => ['name' => 'Primary CTA Label'],
            'primaryCtaUrl' => ['name' => 'Primary CTA URL'],
            'secondaryEyebrow' => ['name' => 'Secondary Eyebrow'],
            'secondaryHeading' => ['name' => 'Secondary Heading'],
            'secondaryBody' => ['name' => 'Secondary Body', 'multiline' => true, 'initialRows' => 4],
        ];

        $homeFields = [];
        foreach ($fieldConfigs as $handle => $config) {
            $homeFields[$handle] = $fields->getFieldByHandle($handle) ?? new PlainText([
                'name' => $config['name'],
                'handle' => $handle,
                'multiline' => $config['multiline'] ?? false,
                'initialRows' => $config['initialRows'] ?? 1,
            ]);

            if (!$homeFields[$handle]->id && !$fields->saveField($homeFields[$handle])) {
                throw new RuntimeException("Unable to save the $handle field.");
            }
        }

        foreach (['secondaryCta' => 'Secondary CTA'] as $handle => $name) {
            $homeFields[$handle] = $fields->getFieldByHandle($handle) ?? new Link([
                'name' => $name,
                'handle' => $handle,
                'showLabelField' => true,
                'types' => ['url', 'email'],
            ]);

            if (!$homeFields[$handle]->id && !$fields->saveField($homeFields[$handle])) {
                throw new RuntimeException("Unable to save the $handle field.");
            }
        }

        $layout = static function(array $layoutFields): FieldLayout {
            return FieldLayout::createFromConfig([
                'type' => Entry::class,
                'tabs' => [[
                    'name' => 'Content',
                    'elements' => array_map(
                        fn($field) => [
                            'type' => CustomField::class,
                            'fieldUid' => $field->uid,
                            'required' => true,
                        ],
                        $layoutFields,
                    ),
                ]],
            ]);
        };

        $mainContent = $entries->getEntryTypeByHandle('mainContent') ?? new EntryType([
            'name' => 'Main Content',
            'handle' => 'mainContent',
            'titleFormat' => '{heading} {headingAccent}',
            'showSlugField' => false,
            'showStatusField' => false,
            'uiLabelFormat' => '{heading} {headingAccent}',
        ]);
        if (!$mainContent->id) {
            $mainContent->setFieldLayout($layout([
                $homeFields['eyebrow'],
                $homeFields['heading'],
                $homeFields['headingAccent'],
                $homeFields['bodyCopy'],
                $homeFields['primaryCtaLabel'],
                $homeFields['primaryCtaUrl'],
                $homeFields['secondaryEyebrow'],
                $homeFields['secondaryHeading'],
                $homeFields['secondaryBody'],
                $homeFields['secondaryCta'],
            ]));
            if (!$entries->saveEntryType($mainContent)) {
                throw new RuntimeException('Unable to save the Main Content entry type.');
            }
        }

        $contentSection = $entries->getEntryTypeByHandle('contentSection') ?? new EntryType([
            'name' => 'Content Section',
            'handle' => 'contentSection',
            'titleFormat' => '{heading}',
            'showSlugField' => false,
            'showStatusField' => false,
            'uiLabelFormat' => '{heading}',
        ]);
        if (!$contentSection->id) {
            $contentSection->setFieldLayout($layout([
                $homeFields['eyebrow'],
                $homeFields['heading'],
                $homeFields['bodyCopy'],
            ]));
            if (!$entries->saveEntryType($contentSection)) {
                throw new RuntimeException('Unable to save the Content Section entry type.');
            }
        }

        $homeContent = $fields->getFieldByHandle('homeContent') ?? new Matrix([
            'name' => 'Home Content',
            'handle' => 'homeContent',
            'viewMode' => Matrix::VIEW_MODE_BLOCKS,
            'minEntries' => 1,
            'enableVersioning' => true,
            'createButtonLabel' => 'Add content',
            'entryTypes' => [$mainContent, $contentSection],
        ]);
        if (!$homeContent->id && !$fields->saveField($homeContent)) {
            throw new RuntimeException('Unable to save the Home Content field.');
        }

        $homePage = $entries->getEntryTypeByHandle('homePage') ?? new EntryType([
            'name' => 'Home Page',
            'handle' => 'homePage',
            'titleFormat' => 'Home',
            'showSlugField' => false,
        ]);
        if (!$homePage->id) {
            $homePage->setFieldLayout($layout([$homeContent]));
            if (!$entries->saveEntryType($homePage)) {
                throw new RuntimeException('Unable to save the Home Page entry type.');
            }
        }

        $section = $entries->getSectionByHandle('home') ?? new Section([
            'name' => 'Home',
            'handle' => 'home',
            'type' => Section::TYPE_SINGLE,
            'enableVersioning' => true,
            'previewTargets' => [[
                'label' => 'Primary entry page',
                'urlFormat' => '{url}',
            ]],
        ]);
        if (!$section->id) {
            $site = Craft::$app->getSites()->getPrimarySite();
            $section->setEntryTypes([$homePage]);
            $section->setSiteSettings([
                new Section_SiteSettings([
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'uriFormat' => '__home__',
                    'template' => 'index.twig',
                ]),
            ]);
            if (!$entries->saveSection($section)) {
                throw new RuntimeException('Unable to save the Home section.');
            }
        }

        $home = Entry::find()->section('home')->status(null)->one();
        if (!$home) {
            $home = new Entry([
                'sectionId' => $section->id,
                'typeId' => $homePage->id,
                'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
                'enabled' => true,
            ]);
        }

        if (!$home->getFieldValue('homeContent')->exists()) {
            $home->setFieldValue('homeContent', [
                'new1' => [
                    'type' => 'mainContent',
                    'fields' => [
                        'eyebrow' => 'Billboard advertising · New York State',
                        'heading' => 'Put your brand',
                        'headingAccent' => 'in the right spot.',
                        'bodyCopy' => 'AdSpot helps businesses reach more people with high-impact billboard placements across New York.',
                        'primaryCtaLabel' => 'Start your campaign',
                        'primaryCtaUrl' => '#contact',
                        'secondaryEyebrow' => 'Your next campaign',
                        'secondaryHeading' => 'Ready to get noticed?',
                        'secondaryBody' => 'Tell us who you want to reach. We’ll help you find the right location.',
                        'secondaryCta' => ['type' => 'email', 'value' => 'hello@adspot.com', 'label' => 'hello@adspot.com'],
                    ],
                ],
                'new2' => [
                    'type' => 'contentSection',
                    'fields' => [
                        'eyebrow' => 'Content section 01',
                        'heading' => 'Section heading',
                        'bodyCopy' => 'Use this space for supporting homepage content, featured services, or a focused call to action.',
                    ],
                ],
                'new3' => [
                    'type' => 'contentSection',
                    'fields' => [
                        'eyebrow' => 'Content section 02',
                        'heading' => 'Section heading',
                        'bodyCopy' => 'Use this space for another key message, customer story, featured location, or conversion point.',
                    ],
                ],
            ]);

            if (!Craft::$app->getElements()->saveElement($home)) {
                throw new RuntimeException('Unable to seed the Home entry: ' . implode(', ', $home->getErrorSummary(true)));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261002_170400_create_home_page_content cannot be reverted.\n";
        return false;
    }
}
