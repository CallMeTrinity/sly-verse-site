<?php

namespace modules\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Html;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

/**
 * Accepte une proposition de superstar (section propositionsPersonnages) :
 * crée la superstar en brouillon non publié dans la section Superstars,
 * puis passe la proposition au statut « Acceptée ».
 *
 * La proposition est gardée telle quelle (on conserve la trace du membre qui l'a faite).
 */
class ProposalAcceptor extends Component
{
    public const PROPOSAL_SECTION = 'propositionsPersonnages';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    /** Champs copiés tels quels de la proposition vers la superstar */
    private const COPIED_FIELDS = [
        'ringName',
        'gender',
        'hometown',
        'homeCountry',
        'height',
        'weight',
        'alignment',
        'roster',
        'finisher',
        'shortDescription',
    ];

    public static function isProposal(?Entry $entry): bool
    {
        return $entry !== null && $entry->getSection()?->handle === self::PROPOSAL_SECTION;
    }

    public static function isAccepted(Entry $proposal): bool
    {
        return (string)$proposal->getFieldValue('proposalStatus')?->value === self::STATUS_ACCEPTED;
    }

    /**
     * Retrouve la proposition à partir de son ID canonique. Si l'utilisateur a des
     * modifications en cours (brouillon provisoire), elles sont d'abord enregistrées :
     * on accepte la proposition telle qu'il la voit à l'écran.
     */
    public function getProposal(int $proposalId, User $user): Entry
    {
        $proposal = Entry::find()
            ->id($proposalId)
            ->section(self::PROPOSAL_SECTION)
            ->status(null)
            ->one();

        if (!$proposal) {
            throw new InvalidArgumentException('Proposition introuvable.');
        }

        $provisional = Entry::find()
            ->draftOf($proposal)
            ->provisionalDrafts()
            ->draftCreator($user)
            ->siteId($proposal->siteId)
            ->status(null)
            ->one();

        if ($provisional) {
            $proposal = Craft::$app->getDrafts()->applyDraft($provisional);
        }

        return $proposal;
    }

    /**
     * @return Entry Le brouillon de superstar créé
     * @throws InvalidArgumentException si la proposition est déjà acceptée
     * @throws Exception si l'enregistrement échoue
     */
    public function accept(Entry $proposal, User $user): Entry
    {
        if (!self::isProposal($proposal)) {
            throw new InvalidArgumentException("« {$proposal->title} » n'est pas une proposition de superstar.");
        }
        if (self::isAccepted($proposal)) {
            throw new InvalidArgumentException("« {$proposal->title} » est déjà acceptée.");
        }

        $section = Craft::$app->getEntries()->getSectionByHandle('superstars');
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle('superstar');
        if (!$section || !$entryType) {
            throw new Exception('Section "superstars" ou entry type "superstar" introuvable.');
        }

        $superstar = new Entry();
        $superstar->sectionId = $section->id;
        $superstar->typeId = $entryType->id;
        $superstar->siteId = $proposal->siteId;
        $superstar->setAuthorIds([$user->id]);
        $superstar->title = (string)$proposal->getFieldValue('ringName');
        $superstar->setFieldValues($this->fieldValues($proposal));

        $pseudo = trim((string)$proposal->getFieldValue('proposerPseudo')) ?: 'un membre';
        $email = trim((string)$proposal->getFieldValue('proposerEmail'));
        $notes = "Proposée par $pseudo" . ($email ? " ($email)" : '') . ' via le formulaire « Participer ».';

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            if (!Craft::$app->getDrafts()->saveElementAsDraft($superstar, $user->id, "Proposition de $pseudo", $notes)) {
                throw new Exception('Création de la superstar impossible : ' . implode(' ; ', $superstar->getFirstErrors()));
            }

            $proposal->setFieldValue('proposalStatus', self::STATUS_ACCEPTED);
            if (!Craft::$app->getElements()->saveElement($proposal)) {
                throw new Exception('Mise à jour de la proposition impossible : ' . implode(' ; ', $proposal->getFirstErrors()));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $superstar;
    }

    private function fieldValues(Entry $proposal): array
    {
        // Une proposition vient toujours de la communauté : c'est un personnage CWE
        $values = ['origin' => 'cwe'];

        foreach (self::COPIED_FIELDS as $handle) {
            $value = $proposal->getFieldValue($handle);
            // Les Dropdown renvoient un SingleOptionFieldData : on recopie la valeur brute
            $value = is_object($value) && property_exists($value, 'value') ? $value->value : $value;
            if ($value !== null && $value !== '') {
                $values[$handle] = $value;
            }
        }

        $bio = $this->bioToHtml((string)$proposal->getFieldValue('proposalBio'));
        if ($bio !== '') {
            $values['richContent'] = $bio;
        }

        return $values;
    }

    /**
     * Bio en texte brut (textarea du formulaire) -> paragraphes HTML pour CKEditor.
     */
    private function bioToHtml(string $bio): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($bio), -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_map(
            fn(string $paragraph) => '<p>' . nl2br(Html::encode(trim($paragraph)), false) . '</p>',
            $paragraphs,
        ));
    }
}
