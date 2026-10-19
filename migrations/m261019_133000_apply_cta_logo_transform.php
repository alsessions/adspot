<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use RuntimeException;

/**
 * Applies the small inline transform to the existing CTA logo.
 */
class m261019_133000_apply_cta_logo_transform extends Migration
{
    public function safeUp(): bool
    {
        $asset = Asset::find()
            ->volume('photos')
            ->filename('ga-logo.jpeg')
            ->status(null)
            ->one();

        if (!$asset) {
            return true;
        }

        $pattern = sprintf('/\{asset:%d:url\|\|[^}]+\}/', $asset->id);
        $replacement = sprintf(
            '{asset:%d:transform:inlineSmall||%s}',
            $asset->id,
            $asset->getUrl('inlineSmall'),
        );

        $entries = Entry::find()
            ->type('fullWidthCta')
            ->status(null)
            ->drafts(false)
            ->revisions(false)
            ->all();

        foreach ($entries as $entry) {
            $value = $entry->getFieldValue('bodyText');
            $content = $value?->getRawContent() ?? '';

            if (!str_contains($content, 'style="width:25%;"') || !preg_match($pattern, $content)) {
                continue;
            }

            preg_match('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $content, $captionMatch);
            $caption = $captionMatch[1] ?? null;

            $entry->setFieldValue('bodyText', preg_replace($pattern, $replacement, $content));
            $entry->forceSave = true;

            if (!Craft::$app->getElements()->saveElement($entry)) {
                throw new RuntimeException("Unable to update the CTA image for {$entry->title}: " . implode(', ', $entry->getErrorSummary(true)));
            }

            $savedContent = $entry->getFieldValue('bodyText')->getRawContent();

            if ($caption && !str_contains($savedContent, "<figcaption>$caption</figcaption>")) {
                $savedContent = preg_replace_callback(
                    '/<figcaption\b[^>]*>.*?<\/figcaption>/is',
                    fn() => "<figcaption>$caption</figcaption>",
                    $savedContent,
                    1,
                );
                $entry->setFieldValue('bodyText', $savedContent);
                $entry->forceSave = true;

                if (!Craft::$app->getElements()->saveElement($entry)) {
                    throw new RuntimeException("Unable to preserve the CTA image caption for {$entry->title}: " . implode(', ', $entry->getErrorSummary(true)));
                }
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261019_133000_apply_cta_logo_transform cannot be reverted.\n";
        return false;
    }
}
