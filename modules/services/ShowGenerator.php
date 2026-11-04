<?php

namespace modules\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use modules\variables\CweVariable;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Générateur de show : données pour l'interface CP et création du show.
 */
class ShowGenerator extends Component
{
    /**
     * Données sérialisées pour le moteur JS (src/js/show-generator/engine.js).
     */
    public function getData(): array
    {
        $superstars = Entry::find()
            ->section('superstars')
            ->status(null)
            ->orderBy('title asc')
            ->with(['members'])
            ->all();

        $wrestlers = [];
        $teams = [];
        foreach ($superstars as $entry) {
            $common = [
                'id' => $entry->id,
                'name' => (string)$entry->title,
                'roster' => $entry->getFieldValue('roster')?->value ?: null,
                'origin' => $entry->getFieldValue('origin')?->value ?: 'cwe',
                'alignment' => $entry->getFieldValue('alignment')?->value ?: null,
                'active' => $entry->enabled && (bool)($entry->getFieldValue('isActive') ?? true),
            ];
            if ($entry->type->handle === 'tagTeam') {
                $teams[] = $common + [
                    'memberIds' => array_map(fn(Entry $m) => $m->id, $entry->getFieldValue('members')->all()),
                ];
            } else {
                $rating = $entry->getFieldValue('general');
                $wrestlers[] = $common + [
                    'gender' => $entry->getFieldValue('gender')?->value ?: null,
                    'rating' => $rating !== null && $rating !== '' ? (float)$rating : null,
                ];
            }
        }

        $genderById = array_column($wrestlers, 'gender', 'id');
        $teamMembers = array_column($teams, 'memberIds', 'id');

        $titles = [];
        foreach ((new CweVariable())->titles() as $title) {
            $belt = $title['belt'];
            $champions = array_map(fn(Entry $c) => [
                'kind' => $c->type->handle === 'tagTeam' ? 't' : 'w',
                'id' => $c->id,
            ], $title['champions']);

            // Titre par équipe : tag team championne, plusieurs champions, ou nom explicite
            $isTag = (bool)preg_match('/tag/i', $belt->title)
                || count($champions) > 1
                || in_array('t', array_column($champions, 'kind'), true);

            // Genre : nom de la ceinture, sinon genre du champion actuel
            $gender = null;
            if (preg_match('/women|féminin|feminin|divas/i', $belt->title)) {
                $gender = 'female';
            } else {
                $genders = [];
                foreach ($champions as $c) {
                    $ids = $c['kind'] === 't' ? ($teamMembers[$c['id']] ?? []) : [$c['id']];
                    foreach ($ids as $id) {
                        $genders[$genderById[$id] ?? 'unknown'] = true;
                    }
                }
                if (count($genders) === 1 && !isset($genders['unknown'])) {
                    $gender = array_key_first($genders);
                }
            }

            $titles[] = [
                'id' => $belt->id,
                'name' => (string)$belt->title,
                'champions' => $champions,
                'isTag' => $isTag,
                'gender' => $gender,
            ];
        }

        return [
            'wrestlers' => $wrestlers,
            'teams' => $teams,
            'titles' => $titles,
            'rosters' => array_map(
                fn($o) => ['value' => $o['value'], 'label' => $o['label']],
                Craft::$app->getFields()->getFieldByHandle('roster')->options,
            ),
            'showTypes' => array_map(
                fn($o) => ['value' => $o['value'], 'label' => $o['label']],
                Craft::$app->getFields()->getFieldByHandle('showType')->options,
            ),
        ];
    }

    /**
     * Crée le show (désactivé, statut "À venir") et ses cartes de match.
     *
     * @param array $payload { title, date, time, showType, matches: [{ label, fighterIds, stake, stipulation }] }
     */
    public function createShow(array $payload): Entry
    {
        $section = Craft::$app->getEntries()->getSectionByHandle('shows');
        $type = Craft::$app->getEntries()->getEntryTypeByHandle('shows');
        if (!$section || !$type) {
            throw new InvalidArgumentException('Section « shows » introuvable.');
        }

        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Le nom du show est obligatoire.');
        }

        $show = new Entry([
            'sectionId' => $section->id,
            'typeId' => $type->id,
            'title' => $title,
            'enabled' => false,
            'authorId' => Craft::$app->getUser()->getId(),
        ]);

        $date = trim((string)($payload['date'] ?? ''));
        $time = trim((string)($payload['time'] ?? '')) ?: '20:00';
        if ($date !== '') {
            $show->setFieldValue('eventDateTime', DateTimeHelper::toDateTime([
                'date' => $date,
                'time' => $time,
                'timezone' => Craft::$app->getTimeZone(),
            ]));
        }
        $show->setFieldValue('showStatus', 'aVenir');
        if (!empty($payload['showType'])) {
            $show->setFieldValue('showType', $payload['showType']);
        }

        $blocks = [];
        $sortOrder = [];
        foreach (array_values($payload['matches'] ?? []) as $i => $match) {
            $key = 'new' . ($i + 1);
            $blocks[$key] = [
                'type' => 'matchCard',
                'enabled' => true,
                'fields' => [
                    'matchTitle' => mb_substr((string)($match['label'] ?? ''), 0, 255),
                    'fighters' => array_values(array_map('intval', $match['fighterIds'] ?? [])),
                    'matchType' => (string)($match['stake'] ?? ''),
                    'stipulation' => (string)($match['stipulation'] ?? ''),
                ],
            ];
            $sortOrder[] = $key;
        }
        $show->setFieldValue('matchCard', ['entries' => $blocks, 'sortOrder' => $sortOrder]);

        if (!Craft::$app->getElements()->saveElement($show)) {
            throw new InvalidArgumentException(implode(' ', $show->getFirstErrors()) ?: 'Impossible d\'enregistrer le show.');
        }

        return $show;
    }
}
