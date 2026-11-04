<?php

namespace modules\variables;

use craft\elements\Entry;
use modules\services\PalmaresSync;

/**
 * Helpers Twig propres à la CWE, accessibles via `craft.cwe`.
 *
 * Palmarès : chaque ceinture porte sa lignée dans le Matrix "reigns" (entry type "reign" :
 * champions + reignDate facultative). L'ordre de la liste fait foi, la fin d'un règne
 * est le début du suivant et le dernier règne est le champion actuel.
 */
class CweVariable
{
    /** @var array<int, array>|null Lignées calculées, indexées par ID de ceinture */
    private ?array $histories = null;

    /**
     * Catégorie d'un lutteur pour la page Superstars : cwe | wwe | tagTeams.
     */
    public function wrestlerCategory(Entry $entry): string
    {
        if ($entry->type->handle === 'tagTeam') {
            return 'tagTeams';
        }

        return ($entry->getFieldValue('origin')?->value ?? null) === 'wwe' ? 'wwe' : 'cwe';
    }

    /**
     * Lignée d'une ceinture, du plus ancien au plus récent.
     *
     * @return array<int, array{reign: Entry, number: int, champions: Entry[], start: ?\DateTime, end: ?\DateTime, days: ?int, current: bool, vacant: bool}>
     */
    public function beltHistory(Entry $belt): array
    {
        return $this->histories()[$belt->id]['reigns'] ?? [];
    }

    /**
     * Champion(s) actuel(s) d'une ceinture (tableau vide si vacante ou sans historique).
     *
     * @return Entry[]
     */
    public function currentChampions(Entry $belt): array
    {
        $reigns = $this->beltHistory($belt);
        $last = end($reigns);

        return $last ? $last['champions'] : [];
    }

    /**
     * Toutes les ceintures avec leur champion actuel.
     *
     * @return array<int, array{belt: Entry, champions: Entry[]}>
     */
    public function titles(): array
    {
        return array_values(array_map(fn(array $h) => [
            'belt' => $h['belt'],
            'champions' => ($last = end($h['reigns'])) ? $last['champions'] : [],
        ], $this->histories()));
    }

    /**
     * IDs des superstars / tag teams détenant actuellement une ceinture.
     * Les membres d'une tag team championne sont inclus.
     *
     * @return int[]
     */
    public function currentChampionIds(): array
    {
        $ids = [];
        foreach ($this->titles() as $title) {
            foreach ($title['champions'] as $champion) {
                $ids[] = $champion->id;
                if ($champion->type->handle === 'tagTeam') {
                    foreach ($champion->getFieldValue('members')->ids() as $memberId) {
                        $ids[] = $memberId;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Palmarès d'une superstar ou d'une tag team, groupé par ceinture.
     * Une superstar récupère aussi les règnes de ses tag teams (marqués `via`).
     *
     * @return array<int, array{belt: Entry, count: int, current: bool, reigns: array}>
     */
    public function palmares(Entry $entry): array
    {
        $ids = [$entry->id];
        $teams = [];
        if ($entry->type->handle !== 'tagTeam') {
            $teams = Entry::find()
                ->section('superstars')
                ->type('tagTeam')
                ->relatedTo(['targetElement' => $entry, 'field' => 'members'])
                ->all();
            foreach ($teams as $team) {
                $ids[] = $team->id;
            }
        }

        $result = [];
        foreach ($this->histories() as $beltId => $history) {
            foreach ($history['reigns'] as $reign) {
                $holder = null;
                foreach ($reign['champions'] as $champion) {
                    if (in_array($champion->id, $ids, true)) {
                        $holder = $champion;
                        break;
                    }
                }
                if (!$holder) {
                    continue;
                }
                $result[$beltId] ??= ['belt' => $history['belt'], 'count' => 0, 'current' => false, 'reigns' => []];
                $result[$beltId]['count']++;
                $result[$beltId]['current'] = $result[$beltId]['current'] || $reign['current'];
                $result[$beltId]['reigns'][] = $reign + ['via' => $holder->id !== $entry->id ? $holder : null];
            }
        }

        // Titres détenus actuellement en premier, puis par nombre de règnes
        uasort($result, fn($a, $b) => [$b['current'], $b['count']] <=> [$a['current'], $a['count']]);

        return array_values($result);
    }

    /**
     * Match pour le titre : ceintures possibles d'après les participants (champions actuels présents).
     *
     * @return Entry[]
     */
    public function titleCandidates(Entry $match): array
    {
        return $this->palmaresSync()->candidateBelts($match);
    }

    /**
     * Match pour le titre : ceintures choisies à la main.
     *
     * @return Entry[]
     */
    public function selectedTitleBelts(Entry $match): array
    {
        return $this->palmaresSync()->selectedBelts($match);
    }

    /**
     * Match pour le titre : ceintures en jeu et résultat (changed : true nouveau champion,
     * false titre conservé, null si le palmarès n'a pas encore été mis à jour depuis ce show).
     *
     * @return array<int, array{belt: Entry, changed: ?bool}>
     */
    public function titleOutcome(Entry $match): array
    {
        return $this->palmaresSync()->titleOutcome($match);
    }

    private ?PalmaresSync $palmaresSync = null;

    private function palmaresSync(): PalmaresSync
    {
        return $this->palmaresSync ??= new PalmaresSync();
    }

    /**
     * Calcule une fois par requête la lignée de toutes les ceintures.
     */
    private function histories(): array
    {
        if ($this->histories !== null) {
            return $this->histories;
        }

        $this->histories = [];
        $belts = Entry::find()
            ->section('ceintures')
            ->orderBy('title asc')
            ->with(['reigns.champions'])
            ->all();

        foreach ($belts as $belt) {
            $reigns = array_values($belt->getFieldValue('reigns')->all());
            $count = count($reigns);
            $history = [];
            foreach ($reigns as $i => $reign) {
                $start = $reign->getFieldValue('reignDate');
                $next = $reigns[$i + 1] ?? null;
                $end = $next?->getFieldValue('reignDate');
                $champions = $reign->getFieldValue('champions')->all();
                $history[] = [
                    'reign' => $reign,
                    'number' => $i + 1,
                    'champions' => $champions,
                    'start' => $start,
                    'end' => $end,
                    'days' => ($start && $end && $end >= $start) ? (int)$start->diff($end)->days : null,
                    'current' => $i === $count - 1 && !empty($champions),
                    'vacant' => empty($champions),
                ];
            }
            $this->histories[$belt->id] = ['belt' => $belt, 'reigns' => $history];
        }

        return $this->histories;
    }
}
