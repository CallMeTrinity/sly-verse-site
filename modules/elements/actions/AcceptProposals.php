<?php

namespace modules\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use modules\services\ProposalAcceptor;

/**
 * Action d'index « Accepter la proposition » : crée une superstar en brouillon
 * pour chaque proposition sélectionnée (les propositions déjà acceptées sont ignorées).
 */
class AcceptProposals extends ElementAction
{
    public static function displayName(): string
    {
        return 'Accepter la proposition';
    }

    public function getConfirmationMessage(): ?string
    {
        return 'Créer une superstar en brouillon pour chaque proposition sélectionnée ?';
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        $acceptor = new ProposalAcceptor();
        $created = 0;
        $skipped = 0;

        /** @var Entry $proposal */
        foreach ($query->all() as $proposal) {
            if (!ProposalAcceptor::isProposal($proposal) || ProposalAcceptor::isAccepted($proposal)) {
                $skipped++;
                continue;
            }

            try {
                $acceptor->accept($proposal, $user);
                $created++;
            } catch (\Throwable $e) {
                Craft::error("Proposition {$proposal->id} : {$e->getMessage()}", __METHOD__);
                $this->setMessage("Échec sur « {$proposal->title} » : {$e->getMessage()}");
                return false;
            }
        }

        $message = $created
            ? "$created superstar(s) créée(s) en brouillon (Superstars, statut « Brouillons »)."
            : 'Aucune superstar créée.';
        if ($skipped) {
            $message .= " $skipped proposition(s) déjà acceptée(s), ignorée(s).";
        }
        $this->setMessage($message);

        return true;
    }
}
