<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use RuntimeException;

/**
 * Updates the homepage hero copy.
 */
class m261002_174500_update_home_hero_copy extends Migration
{
    public function safeUp(): bool
    {
        return $this->updateHero([
            'heading' => 'Where',
            'headingAccent' => 'AMERICA is!',
            'bodyCopy' => "The best venues pull you in — they don’t wait for you to swipe right. They are present, you should be too!\n\nAd Spot is New York's newest advertising company, connecting businesses with high-traffic venues through a growing network of advertising opportunities",
            'primaryCtaLabel' => 'Explore Our Network',
            'primaryCtaUrl' => '/markets',
        ]);
    }

    public function safeDown(): bool
    {
        return $this->updateHero([
            'heading' => 'Put your brand',
            'headingAccent' => 'in the right spot.',
            'bodyCopy' => 'AdSpot helps businesses reach more people with high-impact billboard placements across New York.',
            'primaryCtaLabel' => 'Start your campaign',
            'primaryCtaUrl' => '#contact',
        ]);
    }

    private function updateHero(array $values): bool
    {
        $home = Entry::find()
            ->section('home')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->one();

        if (!$home) {
            throw new RuntimeException('The Home entry is missing.');
        }

        foreach ($values as $handle => $value) {
            $home->setFieldValue($handle, $value);
        }

        if (!Craft::$app->getElements()->saveElement($home)) {
            throw new RuntimeException('Unable to update the homepage hero: ' . implode(', ', $home->getErrorSummary(true)));
        }

        return true;
    }
}
