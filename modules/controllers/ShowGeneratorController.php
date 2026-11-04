<?php

namespace modules\controllers;

use Craft;
use craft\web\Controller;
use modules\services\ShowGenerator;
use yii\base\InvalidArgumentException;
use yii\web\Response;

/**
 * Générateur de show (CP) : /admin/show-generator
 */
class ShowGeneratorController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Réservé aux utilisateurs connectés avec accès au CP et droit de créer des shows
        $this->requireCpRequest();
        $this->requirePermission('accessCp');
        $section = Craft::$app->getEntries()->getSectionByHandle('shows');
        if ($section) {
            $this->requirePermission("saveEntries:{$section->uid}");
        }

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('cwe/show-generator/index', [
            'generatorData' => (new ShowGenerator())->getData(),
        ]);
    }

    public function actionCreate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $payload = $this->request->getBodyParams();

        try {
            $show = (new ShowGenerator())->createShow($payload);
        } catch (InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess('Show créé en brouillon (désactivé).', [
            'id' => $show->id,
            'cpEditUrl' => $show->getCpEditUrl(),
        ]);
    }
}
