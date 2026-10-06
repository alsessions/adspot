<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use RuntimeException;

/**
 * Adds an editable homepage logo row.
 */
class m261006_130000_add_home_logos extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('photos');
        $homePage = $entries->getEntryTypeByHandle('homePage');

        if (!$volume || !$homePage) {
            throw new RuntimeException('The Photos volume or Home Page entry type is missing.');
        }

        $source = "volume:{$volume->uid}";
        $logoImage = $fields->getFieldByHandle('logoImage') ?? new Assets([
            'name' => 'Logo Image',
            'handle' => 'logoImage',
            'instructions' => 'Upload a clear logo with a transparent background when possible.',
            'allowedKinds' => ['image'],
            'maxRelations' => 1,
            'minRelations' => 1,
            'sources' => [$source],
            'defaultUploadLocationSource' => $source,
            'restrictLocation' => true,
            'restrictedLocationSource' => $source,
            'viewMode' => 'thumbs',
        ]);

        if (!$logoImage->id && !$fields->saveField($logoImage)) {
            throw new RuntimeException('Unable to save the Logo Image field.');
        }

        $homeLogo = $entries->getEntryTypeByHandle('homeLogo') ?? new EntryType([
            'name' => 'Home Logo',
            'handle' => 'homeLogo',
            'titleFormat' => 'Logo',
            'showSlugField' => false,
            'showStatusField' => false,
            'uiLabelFormat' => 'Logo',
        ]);

        if (!$homeLogo->id) {
            $homeLogo->setFieldLayout(FieldLayout::createFromConfig([
                'type' => Entry::class,
                'tabs' => [[
                    'name' => 'Logo',
                    'elements' => [[
                        'type' => CustomField::class,
                        'fieldUid' => $logoImage->uid,
                        'required' => true,
                    ]],
                ]],
                'thumbFieldKey' => "field:$logoImage->uid",
            ]));

            if (!$entries->saveEntryType($homeLogo)) {
                throw new RuntimeException('Unable to save the Home Logo entry type.');
            }
        }

        $homeLogos = $fields->getFieldByHandle('homeLogos') ?? new Matrix([
            'name' => 'Home Logos',
            'handle' => 'homeLogos',
            'instructions' => 'Add logos in the order they should appear on the homepage.',
            'createButtonLabel' => 'Add logo',
            'defaultIndexViewMode' => 'cards',
            'enableVersioning' => true,
            'entryTypes' => [$homeLogo],
            'viewMode' => Matrix::VIEW_MODE_BLOCKS,
        ]);

        if (!$homeLogos->id && !$fields->saveField($homeLogos)) {
            throw new RuntimeException('Unable to save the Home Logos field.');
        }

        $homeLayout = $homePage->getFieldLayout();
        $trustedByTab = new FieldLayoutTab(['name' => 'Trusted By']);
        $homeLayout->setTabs([...$homeLayout->getTabs(), $trustedByTab]);
        $trustedByTab->setElements([
            new CustomField($homeLogos, [
                'required' => false,
            ]),
        ]);
        $homePage->setFieldLayout($homeLayout);

        if (!$entries->saveEntryType($homePage)) {
            throw new RuntimeException('Unable to add Home Logos to the Home Page field layout.');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261006_130000_add_home_logos cannot be reverted.\n";
        return false;
    }
}
