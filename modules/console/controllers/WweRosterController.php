<?php

namespace modules\console\controllers;

use craft\console\Controller;
use modules\services\WweRosterImporter;
use yii\console\ExitCode;

/**
 * Import des superstars WWE 2K26.
 */
class WweRosterController extends Controller
{
    /** @var bool Met aussi à jour les superstars WWE déjà présentes */
    public bool $update = false;

    /** @var bool Ne télécharge pas les images */
    public bool $noImages = false;

    /** @var bool Affiche ce qui serait fait, sans rien enregistrer */
    public bool $dryRun = false;

    /** @var int|null Limite le nombre de lignes traitées (pour tester) */
    public ?int $limit = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['update', 'noImages', 'dryRun', 'limit']);
    }

    /**
     * Importe modules/data/wwe2k26-roster.json dans la section Superstars (origin=wwe).
     *
     * Usage : craft my-module/wwe-roster/import [--update] [--no-images] [--dry-run] [--limit=10]
     */
    public function actionImport(): int
    {
        $importer = new WweRosterImporter();
        $importer->logger = fn(string $message) => $this->stdout($message . "\n");

        $stats = $importer->import($this->update, !$this->noImages, $this->limit, $this->dryRun);

        $this->stdout(sprintf(
            "\n%s%d créées, %d mises à jour, %d ignorées (déjà présentes), %d échecs, %d images\n",
            $this->dryRun ? '[dry-run] ' : '',
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['failed'],
            $stats['images'],
        ));

        return $stats['failed'] ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
