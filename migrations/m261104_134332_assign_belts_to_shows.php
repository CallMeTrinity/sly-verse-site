<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Range chaque ceinture existante dans son show (champ Dropdown "brand").
 *
 * Le rattachement est déduit du titre :
 * - préfixe NXT / BCW / AEW : le show du même nom ;
 * - sinon, répartition Raw / SmackDown calquée sur la WWE.
 *
 * Les ceintures qui ont déjà un show sont ignorées. Celles qui ne correspondent
 * à aucune règle restent vides et sont listées : à ranger à la main dans le CP.
 */
class m261104_134332_assign_belts_to_shows extends Migration
{
    private const PREFIXES = ['NXT' => 'nxt', 'BCW' => 'bcw', 'AEW' => 'aew'];

    private const TITLES = [
        'WORLD HEAVYWEIGHT CHAMPIONSHIP' => 'raw',
        'WWE INTERCONTINENTAL CHAMPIONSHIP' => 'raw',
        'WORLD TAG TEAM CHAMPIONSHIP' => 'raw',
        "WWE WOMEN'S WORLD CHAMPIONSHIP" => 'raw',
        "WWE WOMEN'S WORLD TAG TEAM CHAMPIONSHIP" => 'raw',
        "WWE WOMEN'S INTERCONTINENTAL CHAMPIONSHIP" => 'raw',
        'UNDISPUTED WWE CHAMPIONSHIP' => 'smackdown',
        'UNDIISPUTED WWE CHAMPIONSHIP' => 'smackdown',
        'WWE UNITED STATES CHAMPIONSHIP' => 'smackdown',
        'WWE TAG TEAM CHAMPIONSHIP' => 'smackdown',
        "WWE WOMEN'S CHAMPIONSHIP" => 'smackdown',
        "WWE WOMEN'S UNITED STATES CHAMPIONSHIP" => 'smackdown',
        "WWE WOMEN'S TAG TEAM CHAMPIONSHIP" => 'smackdown',
    ];

    public function safeUp(): bool
    {
        $belts = Entry::find()->section('ceintures')->status(null)->all();

        foreach ($belts as $belt) {
            if ($belt->getFieldValue('brand')->value) {
                continue;
            }

            $brand = $this->guessBrand($belt->title);
            if (!$brand) {
                echo "    > {$belt->title} : aucun show reconnu, à ranger dans le CP\n";
                continue;
            }

            $belt->setFieldValue('brand', $brand);
            if (!Craft::$app->getElements()->saveElement($belt, false)) {
                echo "    > échec de sauvegarde pour la ceinture #{$belt->id}\n";
                return false;
            }
            echo "    > {$belt->title} : $brand\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261104_134332_assign_belts_to_shows cannot be reverted.\n";
        return false;
    }

    private function guessBrand(string $title): ?string
    {
        $title = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $title)));

        foreach (self::PREFIXES as $prefix => $brand) {
            if (str_starts_with($title, $prefix . ' ')) {
                return $brand;
            }
        }

        return self::TITLES[$title] ?? null;
    }
}
