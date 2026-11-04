<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * Table de suivi des mises à jour automatiques du palmarès (modules/services/PalmaresSync.php).
 *
 * Une ligne par show dont les matchs pour le titre ont été appliqués aux ceintures.
 * Elle empêche de réappliquer un show à chaque enregistrement : seul le bouton
 * « Recalculer le palmarès » (dernier show terminé uniquement) relance le calcul.
 */
class m261104_160000_create_palmares_syncs extends Migration
{
    public const TABLE = '{{%cwe_palmares_syncs}}';

    public function safeUp(): bool
    {
        if ($this->db->tableExists(self::TABLE)) {
            return true;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'showId' => $this->integer()->notNull(),
            'report' => $this->text()->comment('JSON : compte rendu + résultat par match'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, self::TABLE, ['showId'], true);
        $this->addForeignKey(null, self::TABLE, ['showId'], Table::ELEMENTS, ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::TABLE);
        return true;
    }
}
