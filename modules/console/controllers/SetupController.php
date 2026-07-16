<?php
namespace modules\console\controllers;

use Craft;
use craft\console\Controller;
use craft\guestentries\Plugin as GuestEntries;
use yii\console\ExitCode;

/**
 * Commandes de setup one-shot du projet.
 */
class SetupController extends Controller
{
    /**
     * Configure guest-entries pour la section "Propositions de personnages".
     *
     * Usage : craft my-module/setup/guest-entries
     */
    public function actionGuestEntries(): int
    {
        $section = Craft::$app->getEntries()->getSectionByHandle('propositionsPersonnages');
        if (!$section) {
            $this->stderr("Section propositionsPersonnages introuvable.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $author = \craft\elements\User::find()->admin()->one();
        if (!$author) {
            $this->stderr("Aucun utilisateur admin trouvé pour servir d'auteur par défaut.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $plugin = GuestEntries::getInstance();
        $ok = Craft::$app->getPlugins()->savePluginSettings($plugin, [
            'enableCsrfProtection' => true,
            // "entry" écraserait la variable entry de la page single en cas
            // d'erreur de validation, d'où un nom dédié
            'entryVariable' => 'guestEntry',
            'sections' => [
                $section->uid => [
                    'sectionUid' => $section->uid,
                    'allowGuestSubmissions' => true,
                    'enabledByDefault' => false,
                    'runValidation' => true,
                    'authorUid' => $author->uid,
                ],
            ],
        ]);

        if (!$ok) {
            $this->stderr('Échec : ' . print_r($plugin->getSettings()->getErrors(), true) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("guest-entries configuré (section {$section->handle}, auteur {$author->email}).\n");
        return ExitCode::OK;
    }
}
