<?php

declare(strict_types=1);

return [
    'remove_column' => [
        'message' => 'Supprimer la colonne `:column` utilisee par l\'application provoquera des erreurs jusqu\'au redemarrage des serveurs.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

Etape 1 - Ignorez la colonne dans le modele Eloquent :

    protected static array $ignoredColumns = [':column'];

Etape 2 - Deployez le code.

Etape 3 - Creez une migration qui supprime la colonne, encapsulee dans :

    StrongMigrations::safetyAssured(function () {
        Schema::table('your_table', function (Blueprint $table) {
            $table->dropColumn(':column');
        });
    });
ALT,
    ],

    'rename_column' => [
        'message' => 'Renommer la colonne `:from` en `:to` provoquera des erreurs immediates dans tout code referencant l\'ancien nom.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

1. Creer la nouvelle colonne
2. Ecrire dans les deux colonnes (ancienne et nouvelle)
3. Backfill les donnees de l'ancienne vers la nouvelle
4. Basculer les lectures vers la nouvelle colonne
5. Arreter d'ecrire dans l'ancienne colonne
6. Supprimer l'ancienne colonne
ALT,
    ],

    'rename_table' => [
        'message' => 'Renommer la table `:from` en `:to` cassera tout le code, les relations Eloquent, les foreign keys et les vues referencant l\'ancien nom.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

1. Creer la nouvelle table
2. Ecrire dans les deux tables
3. Backfill les donnees de l'ancienne vers la nouvelle
4. Basculer les lectures vers la nouvelle table
5. Arreter d'ecrire dans l'ancienne table
6. Supprimer l'ancienne table
ALT,
    ],

    'create_table_force' => [
        'message' => 'Utiliser `dropIfExists` sur la table `:table` detruira definitivement les donnees en production.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

Supprimez l'appel a `Schema::dropIfExists()`. Si vous devez recreer une table,
utilisez des migrations separees avec des procedures de sauvegarde appropriees.
ALT,
    ],

    'backfill_in_migration' => [
        'message' => 'Mixer des modifications de schema et des mises a jour de donnees dans la meme migration maintient les verrous pendant toute la duree du backfill.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

Separez en deux migrations :
1. Migration de schema (ALTER TABLE)
2. Migration de donnees (en batches, hors transaction DDL)
ALT,
    ],

    'add_column_with_default' => [
        'message' => 'Ajouter la colonne `:column` avec un default NOT NULL provoque une reecriture complete de la table sur MySQL < 8.0.12.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Etape 1 : Ajouter la colonne nullable
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->nullable();
});

// Etape 2 : Backfill les lignes existantes (migration separee, en batches)
DB::table('your_table')->whereNull(':column')->update([':column' => 'valeur_defaut']);

// Etape 3 : Appliquer le default et la contrainte NOT NULL
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->default('valeur_defaut')->nullable(false)->change();
});
ALT,
    ],

    'change_column_type' => [
        'message' => 'Changer le type de la colonne `:column` necessite une reecriture de table qui bloque lectures et ecritures.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Etape 1 : Creer une nouvelle colonne avec le bon type
Schema::table('your_table', function (Blueprint $table) {
    $table->text(':column_new')->nullable();
});

// Etape 2 : Copier les donnees (migration separee, en batches)
// Etape 3 : Deployer le code pour lire/ecrire la nouvelle colonne
// Etape 4 : Supprimer l'ancienne colonne
ALT,
    ],

    'set_not_null_mysql' => [
        'message' => 'Definir NOT NULL sur la colonne `:column` necessite une copie de table sur MySQL, bloquant lectures et ecritures.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

1. Assurez-vous que toutes les lignes ont une valeur non-null
2. Ajoutez la contrainte NOT NULL dans une migration separee encapsulee dans safetyAssured
ALT,
    ],

    'set_not_null_pgsql' => [
        'message' => 'Definir NOT NULL sur la colonne `:column` bloque lectures et ecritures sur PostgreSQL pendant la verification de chaque ligne.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Etape 1 : Ajouter une check constraint NOT VALID
DB::statement('
    ALTER TABLE your_table
    ADD CONSTRAINT your_table_:column_not_null
    CHECK (:column IS NOT NULL)
    NOT VALID
');

// Etape 2 : Valider (migration separee)
DB::statement('
    ALTER TABLE your_table
    VALIDATE CONSTRAINT your_table_:column_not_null
');

// Etape 3 : Appliquer NOT NULL et supprimer la constraint
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->nullable(false)->change();
});
DB::statement('ALTER TABLE your_table DROP CONSTRAINT your_table_:column_not_null');
ALT,
    ],

    'add_index_non_concurrent' => [
        'message' => 'Ajouter un index bloque les ecritures sur PostgreSQL. Sur des tables volumineuses, cela peut durer des minutes.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Utilisez une migration sans transaction DDL et ajoutez l'index de maniere concurrente :
public function up(): void
{
    DB::statement('CREATE INDEX CONCURRENTLY idx_table_column ON your_table (column)');
}
ALT,
    ],

    'add_foreign_key' => [
        'message' => 'Ajouter une foreign key sur la colonne `:column` bloque les ecritures sur les deux tables sur PostgreSQL.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Etape 1 : Ajouter la contrainte comme NOT VALID
DB::statement('
    ALTER TABLE your_table
    ADD CONSTRAINT your_table_:column_foreign
    FOREIGN KEY (:column) REFERENCES other_table(id)
    NOT VALID
');

// Etape 2 : Valider dans une migration separee
DB::statement('
    ALTER TABLE your_table
    VALIDATE CONSTRAINT your_table_:column_foreign
');
ALT,
    ],

    'add_check_constraint' => [
        'message' => 'Ajouter une check constraint bloque lectures et ecritures sur PostgreSQL pendant la validation.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Etape 1 : Ajouter la constraint comme NOT VALID
DB::statement('ALTER TABLE your_table ADD CONSTRAINT ... CHECK (...) NOT VALID');

// Etape 2 : Valider dans une migration separee
DB::statement('ALTER TABLE your_table VALIDATE CONSTRAINT ...');
ALT,
    ],

    'add_unique_constraint' => [
        'message' => 'Ajouter une contrainte unique sur la colonne `:column` cree un index unique qui bloque lectures et ecritures sur PostgreSQL.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

// Creez l'index unique de maniere concurrente d'abord :
DB::statement('CREATE UNIQUE INDEX CONCURRENTLY idx_table_:column ON your_table (:column)');
ALT,
    ],

    'json_column' => [
        'message' => 'Utiliser le type `json` pour la colonne `:column` sur PostgreSQL n\'a pas d\'operateur d\'egalite, cassant les requetes SELECT DISTINCT.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

Utilisez jsonb au lieu de json :

$table->jsonb(':column');
ALT,
    ],

    'add_auto_increment' => [
        'message' => 'Ajouter une colonne auto-increment `:column` necessite une copie de table sur MySQL.',
        'safe_alternative' => <<<'ALT'
Alternative sure :

Considerez ajouter la colonne sans auto-increment et utilisez une sequence
ou une generation d'ID au niveau applicatif.
ALT,
    ],

    'raw_sql' => [
        'message' => 'Les statements SQL bruts ne peuvent pas etre inspectes par strong-migrations. Verifiez manuellement que c\'est sur.',
        'safe_alternative' => <<<'ALT'
Recommandation :

Verifiez manuellement que le statement SQL ne cause pas de verrous de table,
de perte de donnees, ou d'autres operations dangereuses en production.
ALT,
    ],

    'wide_index' => [
        'message' => 'Index non-unique avec :count colonnes (> 3) est rarement utile et ralentit les ecritures.',
        'safe_alternative' => <<<'ALT'
Recommandation :

Verifiez que cet index est vraiment necessaire. Les index de plus de 3 colonnes
sont rarement utilises efficacement par l'optimiseur de requetes.
ALT,
    ],
];
