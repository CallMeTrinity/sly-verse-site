<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Renseigne le champ "Origine" (cwe/wwe) sur les superstars et tag teams existantes.
 *
 * Toutes les entrées antérieures à ce champ sont des personnages créés pour l'univers,
 * donc "cwe". Les entrées qui ont déjà une valeur ne sont pas touchées.
 */
class m261104_100955_set_default_superstar_origin extends Migration
{
    public function safeUp(): bool
    {
        $entries = Entry::find()
            ->section('superstars')
            ->type(['superstar', 'tagTeam'])
            ->status(null)
            ->all();

        foreach ($entries as $entry) {
            if ($entry->getFieldValue('origin')?->value) {
                continue;
            }
            $entry->setFieldValue('origin', 'cwe');
            if (!Craft::$app->getElements()->saveElement($entry, false)) {
                echo "    > échec de sauvegarde pour l'entrée #{$entry->id}\n";
                return false;
            }
        }

        echo '    > ' . count($entries) . " entrées vérifiées\n";

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
