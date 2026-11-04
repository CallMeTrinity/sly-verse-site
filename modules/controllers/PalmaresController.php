<?php

namespace modules\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use modules\services\PalmaresSync;
use yii\base\InvalidArgumentException;
use yii\web\Response;

/**
 * Palmarès (CP) : bouton « Recalculer le palmarès » sur le dernier show terminé.
 */
class PalmaresController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('accessCp');

        $entries = Craft::$app->getEntries();
        foreach (['shows', 'ceintures'] as $handle) {
            $section = $entries->getSectionByHandle($handle);
            if ($section) {
                $this->requirePermission("saveEntries:{$section->uid}");
            }
        }

        return true;
    }

    public function actionSync(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $show = Entry::find()
            ->id((int)$this->request->getRequiredBodyParam('showId'))
            ->section('shows')
            ->status(null)
            ->one();

        if (!$show) {
            return $this->asFailure('Show introuvable.');
        }

        // Modifications en cours (brouillon provisoire) : on les enregistre d'abord,
        // pour recalculer d'après ce que l'utilisateur voit à l'écran
        $provisional = Entry::find()
            ->draftOf($show)
            ->provisionalDrafts()
            ->draftCreator(Craft::$app->getUser()->getIdentity())
            ->siteId($show->siteId)
            ->status(null)
            ->one();
        if ($provisional) {
            $show = Craft::$app->getDrafts()->applyDraft($provisional);
        }

        try {
            $report = (new PalmaresSync())->sync($show);
        } catch (InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        }

        $message = $report
            ? 'Palmarès recalculé. ' . implode(' ', $report)
            : 'Palmarès recalculé : aucun match pour le titre à appliquer.';
        Craft::$app->getSession()->setNotice($message);

        return $this->asSuccess($message, ['cpEditUrl' => $show->getCpEditUrl()]);
    }
}
