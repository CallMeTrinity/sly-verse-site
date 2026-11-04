<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\services\WweRosterImporter;

/**
 * Importe le roster WWE 2K26 (modules/data/wwe2k26-roster.json) en prod.
 *
 * Même code que `craft my-module/wwe-roster/import` : les superstars WWE déjà
 * présentes ne sont pas modifiées. Une image impossible à télécharger ne bloque
 * pas le déploiement, la superstar est créée sans image.
 */
class m261104_142442_import_wwe2k26_roster extends Migration
{
    public function safeUp(): bool
    {
        $importer = new WweRosterImporter();
        $importer->logger = function(string $message) {
            if (str_contains($message, 'échec') || str_contains($message, 'image non')) {
                echo "    > $message\n";
            }
        };

        $stats = $importer->import();

        echo sprintf(
            "    > %d créées, %d ignorées, %d échecs, %d images\n",
            $stats['created'],
            $stats['skipped'],
            $stats['failed'],
            $stats['images'],
        );

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
