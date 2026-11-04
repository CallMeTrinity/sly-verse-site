<?php

namespace modules\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\FileHelper;
use craft\models\VolumeFolder;
use yii\base\Component;
use yii\base\Exception;

/**
 * Importe les superstars WWE 2K26 depuis modules/data/wwe2k26-roster.json.
 *
 * Le JSON est produit par scripts/wwe2k26/scrape.py. Une superstar WWE est
 * reconnue par son titre + origin=wwe : relancer l'import ne crée pas de doublon.
 * Par défaut, les entrées existantes ne sont pas touchées (on ne veut pas écraser
 * une correction faite à la main dans le CP) ; $update force la mise à jour.
 */
class WweRosterImporter extends Component
{
    public const DATA_FILE = '@modules/data/wwe2k26-roster.json';
    public const IMAGE_FOLDER = 'wwe2k26';

    /** @var callable|null fonction(string $message) pour suivre l'avancement */
    public $logger = null;

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, images: int}
     */
    public function import(bool $update = false, bool $withImages = true, ?int $limit = null, bool $dryRun = false): array
    {
        $rows = $this->loadRows();
        if ($limit) {
            $rows = array_slice($rows, 0, $limit);
        }

        $section = Craft::$app->getEntries()->getSectionByHandle('superstars');
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle('superstar');
        if (!$section || !$entryType) {
            throw new Exception('Section "superstars" ou entry type "superstar" introuvable.');
        }

        $existing = [];
        foreach (Entry::find()->section('superstars')->type('superstar')->origin('wwe')->status(null)->all() as $entry) {
            $existing[mb_strtolower($entry->title)] = $entry;
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'images' => 0];
        $total = count($rows);

        foreach ($rows as $i => $row) {
            $prefix = sprintf('[%d/%d] %s', $i + 1, $total, $row['name']);
            $entry = $existing[mb_strtolower($row['name'])] ?? null;
            $isNew = $entry === null;

            if (!$isNew && !$update) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->log("$prefix : " . ($isNew ? 'à créer' : 'à mettre à jour'));
                $stats[$isNew ? 'created' : 'updated']++;
                continue;
            }

            if ($isNew) {
                $entry = new Entry();
                $entry->sectionId = $section->id;
                $entry->typeId = $entryType->id;
                $entry->title = $row['name'];
            }

            $entry->setFieldValues($this->fieldValues($row));

            if ($withImages && !empty($row['image']) && ($isNew || empty($entry->getFieldValue('profileImage')->ids()))) {
                $assetId = $this->importImage($row['image'], $row['slug']);
                if ($assetId) {
                    $entry->setFieldValue('profileImage', [$assetId]);
                    $stats['images']++;
                } else {
                    $this->log("$prefix : image non récupérée ({$row['image']})");
                }
            }

            if (!Craft::$app->getElements()->saveElement($entry)) {
                $stats['failed']++;
                $this->log("$prefix : échec, " . implode(' ; ', $entry->getFirstErrors()));
                continue;
            }

            $stats[$isNew ? 'created' : 'updated']++;
            $this->log("$prefix : " . ($isNew ? 'créée' : 'mise à jour'));
        }

        return $stats;
    }

    private function loadRows(): array
    {
        $path = Craft::getAlias(self::DATA_FILE);
        if (!is_file($path)) {
            throw new Exception("Fichier introuvable : $path (lancer scripts/wwe2k26/scrape.py)");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Correspondance JSON -> champs Craft. Les valeurs vides ne sont pas envoyées,
     * pour garder les valeurs par défaut des champs (taille, poids...).
     */
    private function fieldValues(array $row): array
    {
        $values = [
            'origin' => 'wwe',
            'ringName' => $row['name'],
            'gender' => $row['gender'],
            'roster' => $row['roster'],
            'alignment' => $row['alignment'] ?? null,
            'hometown' => $row['hometown'] ?? null,
            'homeCountry' => $row['country'] ?? null,
            'height' => $row['height'] ?? null,
            'weight' => $row['weight'] ?? null,
            'finisher' => $row['finisher'] ?? null,
            'general' => $row['overall'] ?? null,
            // Les légendes sont jouables mais ne font plus partie d'un roster actif
            'isActive' => $row['roster'] !== 'hallOfFamer',
        ];

        return array_filter($values, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Télécharge l'image dans Images/wwe2k26/. Réutilise l'asset s'il existe déjà.
     */
    private function importImage(string $url, string $slug): ?int
    {
        $folder = $this->imageFolder();
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'png';
        $filename = "$slug.$extension";

        $existing = Asset::find()->folderId($folder->id)->filename($filename)->one();
        if ($existing) {
            return $existing->id;
        }

        $tempPath = FileHelper::normalizePath(Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('wwe-') . ".$extension");
        try {
            $response = Craft::createGuzzleClient(['timeout' => 30])->get($url, ['sink' => $tempPath]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        return Craft::$app->getElements()->saveElement($asset) ? $asset->id : null;
    }

    private ?VolumeFolder $folder = null;

    private function imageFolder(): VolumeFolder
    {
        if ($this->folder) {
            return $this->folder;
        }

        $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
        if (!$volume) {
            throw new Exception('Volume "images" introuvable.');
        }

        return $this->folder = Craft::$app->getAssets()->ensureFolderByFullPathAndVolume(self::IMAGE_FOLDER, $volume);
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
