<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'Cette migration exige MySQL ou MariaDB.'
            );
        }

        /*
         * 1. Empêche de rattacher au catalogue d'une coopérative
         * un produit appartenant à une autre coopérative.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cp_validate_owner_bi
            BEFORE INSERT ON catalog_product
            FOR EACH ROW
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM catalogs AS c
                    INNER JOIN products AS p
                        ON p.id = NEW.product_id
                    WHERE c.id = NEW.catalog_id
                      AND c.cooperative_id = p.cooperative_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Le catalogue et le produit doivent appartenir a la meme cooperative.';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cp_validate_owner_bu
            BEFORE UPDATE ON catalog_product
            FOR EACH ROW
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM catalogs AS c
                    INNER JOIN products AS p
                        ON p.id = NEW.product_id
                    WHERE c.id = NEW.catalog_id
                      AND c.cooperative_id = p.cooperative_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Le catalogue et le produit doivent appartenir a la meme cooperative.';
                END IF;
            END
        SQL);

        /*
         * 2. Une catégorie globale est autorisée.
         * Une catégorie locale doit appartenir à la coopérative
         * du produit associé.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER category_product_validate_bi
            BEFORE INSERT ON category_product
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM categories AS c
                    INNER JOIN products AS p
                        ON p.id = NEW.product_id
                    WHERE c.id = NEW.category_id
                      AND c.cooperative_id IS NOT NULL
                      AND c.cooperative_id <> p.cooperative_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Une categorie locale ne peut etre associee qu aux produits de sa cooperative.';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER category_product_validate_bu
            BEFORE UPDATE ON category_product
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM categories AS c
                    INNER JOIN products AS p
                        ON p.id = NEW.product_id
                    WHERE c.id = NEW.category_id
                      AND c.cooperative_id IS NOT NULL
                      AND c.cooperative_id <> p.cooperative_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Une categorie locale ne peut etre associee qu aux produits de sa cooperative.';
                END IF;
            END
        SQL);

        /*
         * 3. Empêche de changer le propriétaire d'un produit
         * si cela rendrait ses associations existantes invalides.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_validate_owner_bu
            BEFORE UPDATE ON products
            FOR EACH ROW
            BEGIN
                IF NEW.cooperative_id <> OLD.cooperative_id
                   AND (
                       EXISTS (
                           SELECT 1
                           FROM catalog_product AS cp
                           INNER JOIN catalogs AS c
                               ON c.id = cp.catalog_id
                           WHERE cp.product_id = OLD.id
                             AND c.cooperative_id <> NEW.cooperative_id
                       )
                       OR EXISTS (
                           SELECT 1
                           FROM category_product AS cp
                           INNER JOIN categories AS c
                               ON c.id = cp.category_id
                           WHERE cp.product_id = OLD.id
                             AND c.cooperative_id IS NOT NULL
                             AND c.cooperative_id <> NEW.cooperative_id
                       )
                   )
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Le changement de cooperative invalide une association existante du produit.';
                END IF;
            END
        SQL);

        /*
         * 4. Empêche de changer le propriétaire d'un catalogue
         * si ses produits associés appartiennent à une autre
         * coopérative que le nouveau propriétaire.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER catalogs_validate_owner_bu
            BEFORE UPDATE ON catalogs
            FOR EACH ROW
            BEGIN
                IF NEW.cooperative_id <> OLD.cooperative_id
                   AND EXISTS (
                       SELECT 1
                       FROM catalog_product AS cp
                       INNER JOIN products AS p
                           ON p.id = cp.product_id
                       WHERE cp.catalog_id = OLD.id
                         AND p.cooperative_id <> NEW.cooperative_id
                   )
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'Le changement de cooperative invalide une association existante du catalogue.';
                END IF;
            END
        SQL);

        /*
         * 5. Empêche de rendre une catégorie locale à une
         * coopérative qui ne possède pas tous ses produits liés.
         *
         * Le passage d'une catégorie locale à une catégorie
         * globale reste compatible avec la règle des catégories.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER categories_validate_owner_bu
            BEFORE UPDATE ON categories
            FOR EACH ROW
            BEGIN
                IF NOT (NEW.cooperative_id <=> OLD.cooperative_id)
                   AND NEW.cooperative_id IS NOT NULL
                   AND EXISTS (
                       SELECT 1
                       FROM category_product AS cp
                       INNER JOIN products AS p
                           ON p.id = cp.product_id
                       WHERE cp.category_id = OLD.id
                         AND p.cooperative_id <> NEW.cooperative_id
                   )
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT =
                            'La categorie ne peut pas changer de cooperative car ses associations seraient invalides.';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(
            'DROP TRIGGER IF EXISTS categories_validate_owner_bu'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS catalogs_validate_owner_bu'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS products_validate_owner_bu'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS category_product_validate_bu'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS category_product_validate_bi'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS cp_validate_owner_bu'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS cp_validate_owner_bi'
        );
    }
};
