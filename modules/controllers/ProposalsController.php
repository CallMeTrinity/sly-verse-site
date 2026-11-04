<?php

namespace modules\controllers;

use Craft;
use craft\web\Controller;
use modules\services\ProposalAcceptor;
use yii\base\InvalidArgumentException;
use yii\web\Response;

/**
 * Propositions de superstars (CP) : bouton « Accepter la proposition ».
 */
class ProposalsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('accessCp');

        $entries = Craft::$app->getEntries();
        foreach (['superstars', ProposalAcceptor::PROPOSAL_SECTION] as $handle) {
            $section = $entries->getSectionByHandle($handle);
            if ($section) {
                $this->requirePermission("saveEntries:{$section->uid}");
            }
        }

        return true;
    }

    public function actionAccept(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $user = Craft::$app->getUser()->getIdentity();
        $acceptor = new ProposalAcceptor();

        try {
            $proposal = $acceptor->getProposal((int)$this->request->getRequiredBodyParam('proposalId'), $user);
            $superstar = $acceptor->accept($proposal, $user);
        } catch (InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        }

        Craft::$app->getSession()->setSuccess("Superstar « {$superstar->title} » créée en brouillon.");

        return $this->asSuccess('Superstar créée en brouillon.', [
            'id' => $superstar->id,
            'cpEditUrl' => $superstar->getCpEditUrl(),
        ]);
    }
}
