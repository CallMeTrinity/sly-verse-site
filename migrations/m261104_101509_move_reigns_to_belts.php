<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Palmarès : les règnes passent de chaque superstar (Matrix "championships")
 * à chaque ceinture (Matrix "reigns").
 *
 * Ancien modèle : un bloc par règne sur la superstar, avec ceinture + date de début + date de fin.
 * Nouveau modèle : la ceinture porte sa lignée, une ligne par changement de titre
 * (champion(s) + date facultative). L'ordre de la liste fait foi et la fin d'un règne
 * est le début du suivant.
 *
 * Ordre reconstitué par ceinture : d'abord les règnes sans date (ordre de création),
 * puis les règnes datés, triés par date de victoire. À vérifier dans le CP.
 *
 * Les anciennes données ne sont pas supprimées (le champ "championships" est seulement
 * retiré des layouts). Les ceintures qui ont déjà un historique sont ignorées.
 */
class m261104_101509_move_reigns_to_belts extends Migration
{
    public function safeUp(): bool
    {
        $oldReigns = Entry::find()
            ->type('championships')
            ->status(null)
            ->orderBy(['elements.id' => SORT_ASC])
            ->all();

        $byBelt = [];
        foreach ($oldReigns as $reign) {
            $belt = $reign->getFieldValue('ceinture')->status(null)->one();
            if (!$belt || !$reign->ownerId) {
                continue;
            }
            $byBelt[$belt->id][] = $reign;
        }

        foreach ($byBelt as $beltId => $reigns) {
            $belt = Entry::find()->id($beltId)->status(null)->one();
            if ($belt->getFieldValue('reigns')->status(null)->count() > 0) {
                echo "    > {$belt->title} : historique déjà présent, ignorée\n";
                continue;
            }

            usort($reigns, function(Entry $a, Entry $b) {
                $da = $a->getFieldValue('eventDateTime');
                $db = $b->getFieldValue('eventDateTime');
                if (!$da && !$db) {
                    return $a->id <=> $b->id;
                }
                if (!$da) {
                    return -1;
                }
                if (!$db) {
                    return 1;
                }
                return $da <=> $db ?: $a->id <=> $b->id;
            });

            $blocks = [];
            $sortOrder = [];
            foreach (array_values($reigns) as $i => $reign) {
                $key = 'new' . ($i + 1);
                $date = $reign->getFieldValue('eventDateTime');
                $blocks[$key] = [
                    'type' => 'reign',
                    'enabled' => true,
                    'fields' => [
                        'champions' => [$reign->ownerId],
                        'reignDate' => $date ? ['date' => $date->format('Y-m-d'), 'timezone' => $date->getTimezone()->getName()] : null,
                    ],
                ];
                $sortOrder[] = $key;
            }

            $belt->setFieldValue('reigns', ['entries' => $blocks, 'sortOrder' => $sortOrder]);
            if (!Craft::$app->getElements()->saveElement($belt, false)) {
                echo "    > échec de sauvegarde pour la ceinture #{$belt->id}\n";
                return false;
            }
            echo '    > ' . $belt->title . ' : ' . count($blocks) . " règne(s)\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261104_101509_move_reigns_to_belts cannot be reverted.\n";
        return false;
    }
}
