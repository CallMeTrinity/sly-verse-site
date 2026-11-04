<?php

namespace modules\services;

use Craft;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\Json;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Matchs pour le titre et mise à jour automatique du palmarès.
 *
 * - Un match (bloc matchCard) coché « Match pour le titre » met en jeu une ou plusieurs ceintures
 *   (champ titleBelts). S'il est vide, on prend la ceinture dont tous les champions actuels
 *   sont parmi les participants, à condition qu'il n'y en ait qu'une.
 * - Quand un show est « Terminé » et que c'est le dernier show en date, chaque match pour le titre
 *   avec un vainqueur est comparé au champion actuel. Si le vainqueur est différent, un règne est
 *   ajouté en fin de lignée de la ceinture, relié au show (champ reignShow).
 * - L'application est automatique une seule fois par show (table cwe_palmares_syncs). Ensuite,
 *   seul le recalcul manuel la relance : il supprime les règnes reliés au show puis les recrée.
 *
 * Le palmarès reste saisissable à la main sur chaque ceinture : ce service ne fait qu'ajouter des règnes.
 */
class PalmaresSync extends Component
{
    public const TABLE = '{{%cwe_palmares_syncs}}';
    public const STATUS_FINISHED = 'termine';

    /** @var array<int, array|null> Applications déjà lues, par ID de show */
    private array $records = [];

    // Ceinture en jeu
    // =========================================================================

    /**
     * Ceintures pouvant être en jeu : celles dont tous les champions actuels participent au match.
     *
     * @return Entry[]
     */
    public function candidateBelts(Entry $match): array
    {
        $fighterIds = $this->wrestlerIds($this->related($match->getFieldValue('fighters')));
        if (!$fighterIds) {
            return [];
        }

        $belts = [];
        foreach (Entry::find()->section('ceintures')->orderBy('title asc')->all() as $belt) {
            $championIds = $this->wrestlerIds($this->currentChampions($belt));
            if ($championIds && !array_diff($championIds, $fighterIds)) {
                $belts[] = $belt;
            }
        }

        return $belts;
    }

    /**
     * Ceintures choisies à la main sur le match.
     *
     * @return Entry[]
     */
    public function selectedBelts(Entry $match): array
    {
        return $this->related($match->getFieldValue('titleBelts'));
    }

    /**
     * Ceintures effectivement en jeu (choix manuel, sinon candidate unique).
     *
     * @return Entry[]
     */
    public function beltsInPlay(Entry $match): array
    {
        if (!$this->isTitleMatch($match)) {
            return [];
        }

        $selected = $this->selectedBelts($match);
        if ($selected) {
            return $selected;
        }

        $candidates = $this->candidateBelts($match);
        return count($candidates) === 1 ? $candidates : [];
    }

    public function isTitleMatch(Entry $match): bool
    {
        return $match->type->handle === 'matchCard' && (bool)$match->getFieldValue('titleMatch');
    }

    /**
     * Avant l'enregistrement d'un match pour le titre : mémorise la ceinture détectée.
     */
    public function fillBelts(Entry $match): void
    {
        if (!$this->isTitleMatch($match) || $this->selectedBelts($match)) {
            return;
        }

        $candidates = $this->candidateBelts($match);
        if (count($candidates) === 1) {
            $match->setFieldValue('titleBelts', [$candidates[0]->id]);
        }
    }

    /**
     * Message d'erreur si la ceinture en jeu ne peut pas être déduite, null sinon.
     */
    public function beltError(Entry $match): ?string
    {
        if (!$this->isTitleMatch($match) || $this->selectedBelts($match)) {
            return null;
        }

        $candidates = $this->candidateBelts($match);
        if (count($candidates) === 1) {
            return null;
        }
        if (!$candidates) {
            return 'Aucun participant ne détient de ceinture : choisissez la ceinture en jeu.';
        }

        $names = implode(', ', array_map(fn(Entry $b) => $b->title, $candidates));
        return "Plusieurs ceintures possibles ({$names}) : choisissez la ceinture en jeu.";
    }

    // Shows
    // =========================================================================

    public function isFinished(Entry $show): bool
    {
        return $show->getSection()?->handle === 'shows'
            && (string)$show->getFieldValue('showStatus')?->value === self::STATUS_FINISHED;
    }

    /**
     * Dernier show terminé, par date (seul show dont on peut appliquer les résultats
     * sans casser l'ordre des lignées).
     */
    public function latestFinishedShow(): ?Entry
    {
        return Entry::find()
            ->section('shows')
            ->status(null)
            ->showStatus(self::STATUS_FINISHED)
            ->eventDateTime(':notempty:')
            ->orderBy(['eventDateTime' => SORT_DESC, 'elements.id' => SORT_DESC])
            ->one();
    }

    public function isLatestFinishedShow(Entry $show): bool
    {
        return $this->isFinished($show) && $this->latestFinishedShow()?->id === $show->getCanonicalId();
    }

    public function isSynced(Entry $show): bool
    {
        return $this->syncRecord($show) !== null;
    }

    /**
     * Dernière application d'un show : compte rendu et résultat par match
     * (outcomes[matchId][beltId] = true si nouveau champion).
     *
     * @return array{report: string[], outcomes: array<int, array<int, bool>>}|null
     */
    public function syncRecord(Entry $show): ?array
    {
        $showId = $show->getCanonicalId();
        if (!array_key_exists($showId, $this->records)) {
            $json = (new Query())
                ->select('report')
                ->from(self::TABLE)
                ->where(['showId' => $showId])
                ->scalar();
            $this->records[$showId] = $json === false ? null : (Json::decodeIfJson((string)$json) ?: [])
                + ['report' => [], 'outcomes' => []];
        }

        return $this->records[$showId];
    }

    /**
     * Application automatique, à l'enregistrement d'un show : uniquement le dernier show terminé,
     * et uniquement s'il n'a jamais été appliqué.
     *
     * @return string[]|null Compte rendu, null si rien n'a été tenté
     */
    public function autoSync(Entry $show): ?array
    {
        if (!$this->isLatestFinishedShow($show) || $this->isSynced($show)) {
            return null;
        }

        $report = $this->sync($show);
        return $report ?: null;
    }

    /**
     * (Re)calcule les règnes issus d'un show : supprime ceux qui y sont reliés, puis rejoue
     * ses matchs pour le titre dans l'ordre de la carte.
     *
     * @return string[] Compte rendu, une ligne par match pour le titre
     * @throws InvalidArgumentException si le show n'est pas le dernier show terminé
     */
    public function sync(Entry $show): array
    {
        if (!$this->isLatestFinishedShow($show)) {
            throw new InvalidArgumentException('Seul le dernier show terminé peut mettre à jour le palmarès.');
        }

        $show = $show->getIsCanonical() ? $show : $show->getCanonical();
        $date = $show->getFieldValue('eventDateTime');
        $elements = Craft::$app->getElements();
        $report = [];
        $outcomes = [];
        $applied = false;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($this->reignsFromShow($show) as $reign) {
                $elements->deleteElement($reign);
            }

            $matches = Entry::find()
                ->ownerId($show->id)
                ->field('matchCard')
                ->all();

            foreach ($matches as $i => $match) {
                if (!$this->isTitleMatch($match)) {
                    continue;
                }

                $label = trim((string)$match->getFieldValue('matchTitle')) ?: 'Match ' . ($i + 1);
                $winners = $this->related($match->getFieldValue('vainqueur'));
                if (!$winners) {
                    $report[] = "{$label} : pas de vainqueur, ignoré.";
                    continue;
                }

                $belts = $this->beltsInPlay($match);
                if (!$belts) {
                    $report[] = "{$label} : ceinture en jeu à choisir, ignoré.";
                    continue;
                }

                // Ceinture déduite pendant la synchro (ex. revanche après un changement de titre
                // dans le même show) : on la fige sur le match pour que l'affichage ne dépende plus
                // des champions à venir
                if (!$this->selectedBelts($match)) {
                    $match->setFieldValue('titleBelts', array_map(fn(Entry $b) => $b->id, $belts));
                    $elements->saveElement($match, false);
                }

                $applied = true;
                $winnerNames = $this->names($winners);
                foreach ($belts as $belt) {
                    if ($this->sameHolder($this->currentChampions($belt), $winners)) {
                        $outcomes[$match->id][$belt->id] = false;
                        $report[] = "{$belt->title} : conservé par {$winnerNames}.";
                        continue;
                    }

                    $this->addReign($belt, $winners, $show, $date);
                    $outcomes[$match->id][$belt->id] = true;
                    $report[] = "{$belt->title} : nouveau champion, {$winnerNames}.";
                }
            }

            $this->saveSyncRecord($show, $applied ? ['report' => $report, 'outcomes' => $outcomes] : null);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * Ceintures en jeu dans un match, avec le résultat une fois le show appliqué.
     *
     * @return array<int, array{belt: Entry, changed: ?bool}> changed : true nouveau champion,
     *         false titre conservé, null si le palmarès n'a pas été mis à jour depuis ce show
     */
    public function titleOutcome(Entry $match): array
    {
        $belts = $this->beltsInPlay($match);
        if (!$belts) {
            return [];
        }

        $show = $match->getOwner();
        $outcomes = $show ? ($this->syncRecord($show)['outcomes'] ?? []) : [];
        $matchOutcomes = $outcomes[$match->getCanonicalId()] ?? [];

        return array_map(fn(Entry $belt) => [
            'belt' => $belt,
            'changed' => isset($matchOutcomes[$belt->id]) ? (bool)$matchOutcomes[$belt->id] : null,
        ], $belts);
    }

    /**
     * Champion(s) actuel(s) d'une ceinture : dernier règne de la lignée (vide si vacante).
     *
     * @return Entry[]
     */
    public function currentChampions(Entry $belt): array
    {
        $last = Entry::find()
            ->ownerId($belt->id)
            ->field('reigns')
            ->inReverse()
            ->one();

        return $last ? $this->related($last->getFieldValue('champions')) : [];
    }

    // Private
    // =========================================================================

    /**
     * @return Entry[]
     */
    private function reignsFromShow(Entry $show): array
    {
        return Entry::find()
            ->type('reign')
            ->status(null)
            ->relatedTo(['targetElement' => $show->getCanonicalId(), 'field' => 'reignShow'])
            ->all();
    }

    /**
     * @param Entry[] $winners
     */
    private function addReign(Entry $belt, array $winners, Entry $show, ?\DateTime $date): void
    {
        $reign = new Entry([
            'fieldId' => Craft::$app->getFields()->getFieldByHandle('reigns')->id,
            'primaryOwnerId' => $belt->id,
            'ownerId' => $belt->id,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('reign')->id,
            'siteId' => $belt->siteId,
        ]);
        $reign->setFieldValue('champions', array_map(fn(Entry $w) => $w->id, $winners));
        $reign->setFieldValue('reignDate', $date);
        $reign->setFieldValue('reignShow', [$show->id]);

        if (!Craft::$app->getElements()->saveElement($reign)) {
            throw new InvalidArgumentException(
                "Règne non enregistré sur {$belt->title} : " . implode(' ', $reign->getFirstErrors())
            );
        }

        Craft::$app->getElements()->invalidateCachesForElement($belt);
    }

    /**
     * @param array|null $record null : aucun match appliqué, l'application automatique reste possible
     */
    private function saveSyncRecord(Entry $show, ?array $record): void
    {
        unset($this->records[$show->id]);
        if ($record === null) {
            Craft::$app->getDb()->createCommand()->delete(self::TABLE, ['showId' => $show->id])->execute();
            return;
        }

        Db::upsert(self::TABLE, ['showId' => $show->id, 'report' => Json::encode($record)]);
    }

    /**
     * Le vainqueur garde le titre si tous ses lutteurs font partie des champions actuels
     * (tag team ou ses membres, règle Freebird comprise).
     *
     * @param Entry[] $champions
     * @param Entry[] $winners
     */
    private function sameHolder(array $champions, array $winners): bool
    {
        $championIds = $this->wrestlerIds($champions);
        $winnerIds = $this->wrestlerIds($winners);

        return $championIds && $winnerIds && !array_diff($winnerIds, $championIds);
    }

    /**
     * IDs des lutteurs individuels : une tag team compte pour ses membres.
     *
     * @param Entry[] $entries
     * @return int[]
     */
    private function wrestlerIds(array $entries): array
    {
        $ids = [];
        foreach ($entries as $entry) {
            if ($entry->type->handle === 'tagTeam') {
                $memberIds = $this->related($entry->getFieldValue('members'));
                if ($memberIds) {
                    array_push($ids, ...array_map(fn(Entry $m) => $m->id, $memberIds));
                    continue;
                }
            }
            $ids[] = $entry->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Éléments d'un champ de relation, qu'il soit en requête ou déjà chargé.
     *
     * @return Entry[]
     */
    private function related(mixed $value): array
    {
        if ($value instanceof ElementQueryInterface) {
            return (clone $value)->status(null)->all();
        }
        if ($value instanceof Collection) {
            return $value->all();
        }

        return [];
    }

    /**
     * @param Entry[] $entries
     */
    private function names(array $entries): string
    {
        return implode(' & ', array_map(fn(Entry $e) => $e->title, $entries));
    }
}
