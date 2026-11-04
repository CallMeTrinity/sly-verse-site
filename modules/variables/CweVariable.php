<?php

namespace modules\variables;

use craft\elements\Entry;

/**
 * Helpers Twig propres à la CWE, accessibles via `craft.cwe`.
 */
class CweVariable
{
    private ?array $championIds = null;

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
     * IDs des superstars / tag teams détenant actuellement une ceinture.
     *
     * @return int[]
     */
    public function currentChampionIds(): array
    {
        if ($this->championIds === null) {
            $this->championIds = array_values(array_unique(array_map(
                fn(Entry $reign) => $reign->ownerId,
                Entry::find()->type('championships')->datePerte(':empty:')->all(),
            )));
        }

        return $this->championIds;
    }
}
