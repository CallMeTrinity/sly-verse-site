<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\PlainText;

/**
 * Renommage SWU (Sly Wrestling Universe) -> CWE (Chimera Wrestling Entertainment)
 * dans le contenu éditorial "vivant" : accueil, page participer, bios des superstars
 * et streams à venir.
 *
 * Les shows et articles passés ne sont pas touchés : ils font partie de l'historique
 * de la fédération sous son ancien nom.
 *
 * Idempotente : relancée, elle ne trouve plus rien à remplacer.
 */
class m261104_100802_rename_swu_to_cwe extends Migration
{
    private const REPLACEMENTS = [
        '/Sly Wrest?ling Univers(e)?/u' => 'Chimera Wrestling Entertainment',
        '/S\.W\.U\b/u' => 'C.W.E',
        '/\bSWU\b/u' => 'CWE',
    ];

    public function safeUp(): bool
    {
        $entries = array_merge(
            Entry::find()->section(['pageAccueil', 'howToParticipate', 'superstars'])->status(null)->all(),
            Entry::find()->type('upcomingStreams')->status(null)->all(),
        );

        $now = new \DateTime();

        foreach ($entries as $entry) {
            // Streams passés : historique, on n'y touche pas
            if ($entry->type->handle === 'upcomingStreams' && ($entry->eventDateTime ?? null) < $now) {
                continue;
            }

            $changed = false;

            $title = $this->replaceSwu((string)$entry->title);
            if ($title !== (string)$entry->title) {
                $entry->title = $title;
                $changed = true;
            }

            foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
                if (!$this->isTextField($field)) {
                    continue;
                }
                $value = $entry->getFieldValue($field->handle);
                if ($value === null) {
                    continue;
                }
                $original = (string)$value;
                $new = $this->replaceSwu($original);
                if ($new !== $original) {
                    $entry->setFieldValue($field->handle, $new);
                    $changed = true;
                }
            }

            if ($changed) {
                if (!Craft::$app->getElements()->saveElement($entry, false)) {
                    echo "    > échec de sauvegarde pour l'entrée #{$entry->id}\n";
                    return false;
                }
                echo "    > entrée #{$entry->id} « {$entry->title} » renommée\n";
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261104_100802_rename_swu_to_cwe cannot be reverted.\n";
        return false;
    }

    private function replaceSwu(string $value): string
    {
        return preg_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $value);
    }

    private function isTextField(FieldInterface $field): bool
    {
        return $field instanceof PlainText
            || (class_exists(\craft\ckeditor\Field::class) && $field instanceof \craft\ckeditor\Field);
    }
}
