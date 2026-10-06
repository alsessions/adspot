<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use RuntimeException;

/**
 * Seeds fictional placeholder logos on the homepage.
 */
class m261006_140000_seed_home_logos extends Migration
{
    public function safeUp(): bool
    {
        $elements = Craft::$app->getElements();
        $assets = Craft::$app->getAssets();
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('photos');
        $home = Entry::find()
            ->section('home')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$volume || !$home) {
            throw new RuntimeException('The Photos volume or Home entry is missing.');
        }

        if ($home->getFieldValue('homeLogos')->exists()) {
            return true;
        }

        $folder = $assets->getRootFolderByVolumeId($volume->id);
        $logoIds = [];
        $logos = [
            'north-star-coffee.png' => 'North Star Coffee',
            'mohawk-market.png' => 'Mohawk Market',
            'empire-table.png' => 'Empire Table',
        ];

        foreach ($logos as $filename => $title) {
            $asset = Asset::find()
                ->volume('photos')
                ->filename($filename)
                ->status(null)
                ->one();

            if (!$asset) {
                $source = Craft::getAlias("@root/src/assets/images/home-logos/$filename");
                if (!is_file($source)) {
                    throw new RuntimeException("The placeholder logo $filename is missing.");
                }

                $tempPath = tempnam(sys_get_temp_dir(), 'adspot-logo-');
                if (!$tempPath || !copy($source, $tempPath)) {
                    throw new RuntimeException("Unable to prepare $filename for upload.");
                }

                $asset = new Asset([
                    'tempFilePath' => $tempPath,
                    'filename' => $filename,
                    'newFolderId' => $folder->id,
                    'volumeId' => $volume->id,
                    'avoidFilenameConflicts' => true,
                    'title' => $title,
                ]);
                $asset->setScenario(Asset::SCENARIO_CREATE);

                if (!$elements->saveElement($asset)) {
                    throw new RuntimeException("Unable to create $filename: " . implode(', ', $asset->getErrorSummary(true)));
                }
            }

            $logoIds[] = $asset->id;
        }

        $home->setFieldValue('homeLogos', [
            'new1' => [
                'type' => 'homeLogo',
                'fields' => ['logoImage' => [$logoIds[0]]],
            ],
            'new2' => [
                'type' => 'homeLogo',
                'fields' => ['logoImage' => [$logoIds[1]]],
            ],
            'new3' => [
                'type' => 'homeLogo',
                'fields' => ['logoImage' => [$logoIds[2]]],
            ],
        ]);

        if (!$elements->saveElement($home)) {
            throw new RuntimeException('Unable to save the placeholder home logos: ' . implode(', ', $home->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261006_140000_seed_home_logos cannot be reverted.\n";
        return false;
    }
}
