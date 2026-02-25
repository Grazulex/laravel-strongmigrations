# 🛡️ laravel-strongmigrations — Spécification complète du package

> **Détecte les migrations dangereuses, empêche leur exécution, et guide le développeur vers des alternatives sûres.**

---

## Table des matières

1. [Vision et objectif](#1-vision-et-objectif)
2. [Inspiration et différenciation](#2-inspiration-et-différenciation)
3. [Prérequis techniques](#3-prérequis-techniques)
4. [Installation et configuration](#4-installation-et-configuration)
5. [Architecture du package](#5-architecture-du-package)
6. [Catalogue complet des règles de détection](#6-catalogue-complet-des-règles-de-détection)
7. [Mécanisme de bypass : `safetyAssured`](#7-mécanisme-de-bypass--safetyassured)
8. [Règles personnalisées](#8-règles-personnalisées)
9. [Commandes Artisan](#9-commandes-artisan)
10. [Intégration CI/CD](#10-intégration-cicd)
11. [Structure des fichiers du package](#11-structure-des-fichiers-du-package)
12. [Plan de développement (Roadmap)](#12-plan-de-développement-roadmap)
13. [Stratégie de tests](#13-stratégie-de-tests)
14. [Documentation et marketing](#14-documentation-et-marketing)
15. [Annexes techniques](#15-annexes-techniques)

---

## 1. Vision et objectif

### Le problème

Le système de migrations de Laravel n'a **aucune vérification de sécurité**. Toute migration s'exécute sans validation, qu'elle soit inoffensive ou qu'elle verrouille une table de 10 millions de lignes pendant 30 minutes.

Les développeurs découvrent les problèmes :
- **Trop tard** : en production, quand la migration bloque les requêtes
- **Par hasard** : via un collègue expérimenté qui repère le danger en code review
- **Jamais** : sur des bases encore petites, le problème reste invisible jusqu'à la croissance

### La solution

`laravel-strongmigrations` intercepte les migrations **avant** leur exécution, analyse les opérations qu'elles contiennent, et :

1. **Détecte** les opérations potentiellement dangereuses
2. **Empêche** leur exécution par défaut
3. **Explique** pourquoi c'est dangereux
4. **Guide** le développeur vers une alternative sûre avec du code Laravel prêt à copier-coller

### Exemple d'output attendu

```
$ php artisan migrate

   ⛔ Opération dangereuse détectée — laravel-strongmigrations

   Migration : 2026_02_25_create_add_status_to_users.php

   Ajouter une colonne avec une valeur par défaut NOT NULL provoque une
   réécriture complète de la table sur MySQL < 8.0.12. Sur une table
   volumineuse, cela verrouille les lectures ET écritures pendant toute
   la durée de l'opération.

   ✅ Alternative sûre :

   // Étape 1 : Ajouter la colonne sans valeur par défaut
   Schema::table('users', function (Blueprint $table) {
       $table->string('status')->nullable();
   });

   // Étape 2 : Backfill les données existantes (dans une migration séparée)
   DB::table('users')->whereNull('status')->update(['status' => 'active']);

   // Étape 3 : Appliquer la valeur par défaut et la contrainte NOT NULL
   Schema::table('users', function (Blueprint $table) {
       $table->string('status')->default('active')->nullable(false)->change();
   });

   Si cette opération est volontaire et sûre, encapsulez-la dans :
   StrongMigrations::safetyAssured(function () { ... });
```

---

## 2. Inspiration et différenciation

### Le gem Rails `strong_migrations`

Le gem Ruby `strong_migrations` d'Andrew Kane (2 800+ étoiles GitHub, utilisé en production chez Instacart) est l'inspiration directe. Il détecte les opérations dangereuses dans les migrations Active Record et fournit des alternatives sûres.

**Ce qu'on reprend :**
- Le concept de détection + blocage + guidance
- Le mécanisme `safety_assured` pour bypasser volontairement
- Le support de règles spécifiques par moteur de base de données (MySQL, PostgreSQL, SQLite)
- Les messages d'erreur éducatifs avec code prêt à l'emploi

**Ce qu'on adapte à l'écosystème Laravel :**
- Intégration native avec le Schema Builder de Laravel et les Blueprint
- Utilisation des événements de migration Laravel (`MigrationStarted`, `MigrationsStarted`, `MigrationEnded`, `MigrationsEnded`)
- Commandes Artisan dédiées (`php artisan migrate:check`, `php artisan migrate:analyze`)
- Support de la configuration via `config/strong-migrations.php`
- Intégration avec les packages existants de l'écosystème (Pest, PHPStan)
- Messages en anglais par défaut avec support de la traduction Laravel

### Pourquoi ça n'existe pas pour Laravel

Aucun package composer ne remplit ce rôle. Les approches actuelles sont :
- **Revue de code manuelle** : fragile, dépend de l'expertise individuelle
- **Règles CI ad hoc** : scripts bash/grep sur les fichiers de migration, non maintenables
- **Rien** : la majorité des projets Laravel n'ont aucun filet de sécurité sur les migrations

---

## 3. Prérequis techniques

### Versions supportées

| Composant | Version minimale |
|-----------|-----------------|
| PHP | 8.2+ |
| Laravel | 11.0+ |
| MySQL | 5.7+ (avec distinctions 5.7 / 8.0 / 8.0.12+) |
| PostgreSQL | 12+ |
| MariaDB | 10.3+ |
| SQLite | 3.26+ (vérifications limitées) |

### Dépendances

- `illuminate/database` (fourni par Laravel)
- `illuminate/console` (fourni par Laravel)
- `illuminate/support` (fourni par Laravel)
- `nikic/php-parser` ^5.0 (pour l'analyse statique des fichiers de migration)

Aucune dépendance externe lourde. Le package doit rester léger.

---

## 4. Installation et configuration

### Installation

```bash
composer require grazulex/laravel-strongmigrations --dev

php artisan vendor:publish --tag=strong-migrations-config
```

### Fichier de configuration `config/strong-migrations.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activer/Désactiver le package
    |--------------------------------------------------------------------------
    |
    | Permet de désactiver complètement les vérifications.
    | Utile pour les environnements de test ou CI spécifiques.
    |
    */
    'enabled' => env('STRONG_MIGRATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mode (block ou warn)
    |--------------------------------------------------------------------------
    |
    | 'block' : Empêche l'exécution de la migration (défaut)
    | 'warn'  : Affiche un avertissement mais laisse passer
    |
    */
    'mode' => env('STRONG_MIGRATIONS_MODE', 'block'),

    /*
    |--------------------------------------------------------------------------
    | Moteur de base de données cible
    |--------------------------------------------------------------------------
    |
    | Auto-détecté par défaut depuis la connexion Laravel active.
    | Peut être forcé si nécessaire.
    |
    */
    'database_driver' => null, // null = auto-detect

    /*
    |--------------------------------------------------------------------------
    | Version de la base de données cible
    |--------------------------------------------------------------------------
    |
    | Utilisé pour adapter les règles (ex: MySQL 8.0.12+ supporte
    | ALGORITHM=INSTANT pour certaines opérations).
    | null = auto-detect via requête à la connexion
    |
    */
    'database_version' => null,

    /*
    |--------------------------------------------------------------------------
    | Ignorer les migrations créées avant cette date
    |--------------------------------------------------------------------------
    |
    | Utile lors de l'installation sur un projet existant pour ne pas
    | bloquer les anciennes migrations déjà en production.
    | Format : timestamp du nom de migration (ex: '2026_01_01_000000')
    |
    */
    'start_after' => null,

    /*
    |--------------------------------------------------------------------------
    | Vérifications désactivées
    |--------------------------------------------------------------------------
    |
    | Liste des identifiants de règles à désactiver globalement.
    |
    */
    'disabled_checks' => [
        // 'add_column_with_default',
        // 'remove_column',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seuil de taille de table (en lignes)
    |--------------------------------------------------------------------------
    |
    | Certaines vérifications ne s'appliquent qu'aux tables "volumineuses".
    | Ce seuil définit à partir de combien de lignes une table est
    | considérée comme volumineuse. 0 = toujours vérifier.
    |
    */
    'table_size_threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Timeouts recommandés
    |--------------------------------------------------------------------------
    |
    | Timeout pour les statements et les locks pendant les migrations.
    |
    */
    'timeouts' => [
        'statement_timeout' => '1h',   // PostgreSQL
        'lock_timeout' => '10s',        // PostgreSQL / MySQL
        'lock_wait_timeout' => 10,      // MySQL (secondes)
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe by default
    |--------------------------------------------------------------------------
    |
    | Quand activé, certaines opérations sont automatiquement transformées
    | en leur version sûre (ex: ajout d'index concurrent sur PostgreSQL).
    |
    */
    'safe_by_default' => false,

    /*
    |--------------------------------------------------------------------------
    | Langue des messages
    |--------------------------------------------------------------------------
    |
    | null = anglais par défaut. Supporte les fichiers de lang Laravel.
    |
    */
    'locale' => null,
];
```

---

## 5. Architecture du package

### Diagramme d'architecture

```
┌─────────────────────────────────────────────────────┐
│                  php artisan migrate                  │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│              StrongMigrationsServiceProvider          │
│  ┌────────────────────────────────────────────────┐  │
│  │  Écoute MigrationStarted event                 │  │
│  │  Intercepte la migration AVANT exécution       │  │
│  └─────────────────────┬──────────────────────────┘  │
└────────────────────────┼─────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────┐
│                  MigrationAnalyzer                    │
│  ┌────────────────────────────────────────────────┐  │
│  │  1. Lit le fichier PHP de la migration         │  │
│  │  2. Parse avec PHP-Parser (AST)                │  │
│  │  3. Extrait les appels Schema::xxx             │  │
│  │  4. Identifie les opérations Blueprint         │  │
│  └─────────────────────┬──────────────────────────┘  │
└────────────────────────┼─────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────┐
│                    RuleEngine                         │
│  ┌────────────────────────────────────────────────┐  │
│  │  Pour chaque opération détectée :              │  │
│  │  1. Vérifie les règles applicables             │  │
│  │  2. Filtre par driver de DB                    │  │
│  │  3. Filtre par version de DB                   │  │
│  │  4. Vérifie si dans safetyAssured              │  │
│  │  5. Vérifie si dans disabled_checks            │  │
│  │  6. Génère un Violation si applicable          │  │
│  └─────────────────────┬──────────────────────────┘  │
└────────────────────────┼─────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────┐
│                  ViolationReporter                    │
│  ┌────────────────────────────────────────────────┐  │
│  │  - Formate le message d'erreur                 │  │
│  │  - Inclut l'alternative sûre (code Laravel)    │  │
│  │  - Mode 'block' : lance DangerousOperation     │  │
│  │  - Mode 'warn'  : affiche avertissement        │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

### Composants principaux

#### 1. `StrongMigrationsServiceProvider`
Le point d'entrée du package. Enregistre les listeners sur les événements de migration Laravel, charge la configuration, et initialise les composants.

#### 2. `MigrationAnalyzer`
Responsable de l'analyse statique des fichiers de migration PHP. Utilise `nikic/php-parser` pour construire un AST (Abstract Syntax Tree) et extraire les appels à `Schema::create`, `Schema::table`, `Schema::drop`, `Schema::rename`, et les méthodes du `Blueprint`.

#### 3. `RuleEngine`
Le moteur de règles. Itère sur les opérations détectées et les confronte à un catalogue de règles. Chaque règle est une classe qui implémente `RuleInterface`.

#### 4. `RuleInterface`
```php
interface RuleInterface
{
    public function id(): string;
    public function check(Operation $operation, DatabaseContext $context): ?Violation;
    public function appliesTo(): array; // ['mysql', 'pgsql', 'mariadb', 'sqlite']
}
```

#### 5. `Operation` (Value Object)
Représente une opération de migration détectée (ex: `AddColumn`, `RemoveColumn`, `AddIndex`, `ChangeColumn`, etc.).

#### 6. `DatabaseContext`
Encapsule les informations sur la base de données cible : driver, version, et éventuellement des métadonnées de table (nombre de lignes si `table_size_threshold` est configuré).

#### 7. `Violation`
Représente une violation détectée, contient : la règle violée, le message d'erreur, le code de l'alternative sûre, et le niveau de sévérité.

#### 8. `ViolationReporter`
Formate et affiche les violations. Supporte deux modes : `block` (lance une exception `DangerousOperationException`) et `warn` (affiche dans la console).

---

## 6. Catalogue complet des règles de détection

### Vue d'ensemble

| # | Règle | MySQL | PostgreSQL | MariaDB | SQLite | Sévérité |
|---|-------|:-----:|:----------:|:-------:|:------:|----------|
| 01 | Supprimer une colonne | ✅ | ✅ | ✅ | ✅ | Haute |
| 02 | Ajouter colonne avec default NOT NULL | ✅* | ⚠️ | ✅* | ❌ | Haute |
| 03 | Changer le type d'une colonne | ✅ | ✅ | ✅ | ❌ | Haute |
| 04 | Renommer une colonne | ✅ | ✅ | ✅ | ✅ | Haute |
| 05 | Renommer une table | ✅ | ✅ | ✅ | ✅ | Haute |
| 06 | Ajouter un index (non concurrent) | ❌ | ✅ | ❌ | ❌ | Moyenne |
| 07 | Ajouter une foreign key | ❌ | ✅ | ❌ | ❌ | Moyenne |
| 08 | Définir NOT NULL sur colonne existante | ✅ | ✅ | ✅ | ❌ | Haute |
| 09 | Ajouter une contrainte unique | ❌ | ✅ | ❌ | ❌ | Moyenne |
| 10 | Créer une table avec `force: true` | ✅ | ✅ | ✅ | ✅ | Haute |
| 11 | Ajouter une colonne JSON (vs JSONB) | ❌ | ✅ | ❌ | ❌ | Basse |
| 12 | Ajouter colonne auto-increment | ✅ | ✅ | ✅ | ❌ | Moyenne |
| 13 | Backfill dans la même migration | ✅ | ✅ | ✅ | ✅ | Haute |
| 14 | Utiliser `execute()` brut | ✅ | ✅ | ✅ | ✅ | Avertissement |
| 15 | Index non-unique > 3 colonnes | ✅ | ✅ | ✅ | ✅ | Basse |
| 16 | Ajouter check constraint | ❌ | ✅ | ❌ | ❌ | Moyenne |

\* Dépend de la version MySQL/MariaDB (MySQL >= 8.0.12 supporte ALGORITHM=INSTANT)

---

### Détail des règles

#### Règle 01 — Supprimer une colonne (`remove_column`)

**Danger** : Eloquent cache les attributs du modèle au runtime. Si une colonne est supprimée pendant que l'application tourne, toutes les requêtes `SELECT *` échouent jusqu'au redémarrage de l'application.

**Détection** : Appels à `$table->dropColumn(...)` dans un Blueprint.

**Message** :
```
Supprimer une colonne qui est utilisée par l'application provoquera des
erreurs jusqu'au redémarrage des serveurs d'application.

✅ Alternative sûre :

Étape 1 — Ignorez la colonne dans le modèle Eloquent :

  class User extends Model
  {
      protected $guarded = ['legacy_field'];

      // Ajoutez cette ligne pour qu'Eloquent ignore la colonne
      public function getIgnoredColumns(): array
      {
          return ['legacy_field'];
      }
  }

  // Ou avec la propriété (Laravel 11+) :
  protected static array $ignoredColumns = ['legacy_field'];

Étape 2 — Déployez le code.

Étape 3 — Créez une migration qui supprime la colonne, encapsulée dans :

  StrongMigrations::safetyAssured(function () {
      Schema::table('users', function (Blueprint $table) {
          $table->dropColumn('legacy_field');
      });
  });
```

---

#### Règle 02 — Ajouter une colonne avec default NOT NULL (`add_column_with_default`)

**Danger** :
- **MySQL < 8.0.12** : Provoque une réécriture complète de la table (ALGORITHM=COPY). Verrouille la table en lecture ET écriture.
- **MySQL >= 8.0.12** : Supporte ALGORITHM=INSTANT pour la plupart des cas, donc **pas de danger**. La règle ne s'applique pas.
- **PostgreSQL** : Depuis PostgreSQL 11, ajouter une colonne avec un default non-volatile est safe. La règle ne s'applique que si version < 11.
- **MariaDB** : Supporte ALGORITHM=INSTANT depuis 10.3.2 pour certains cas.

**Détection** : Appels à `$table->xxx('column')->default(value)` sans `->nullable()` dans un Blueprint.

**Message** :
```
Ajouter une colonne avec une valeur par défaut NOT NULL provoque une
réécriture complète de la table sur MySQL < 8.0.12.

✅ Alternative sûre :

// Étape 1 : Ajouter la colonne nullable
Schema::table('users', function (Blueprint $table) {
    $table->string('status')->nullable();
});

// Étape 2 : Backfill (migration séparée, en batches)
User::query()
    ->whereNull('status')
    ->chunkById(1000, function ($users) {
        User::whereIn('id', $users->pluck('id'))
            ->update(['status' => 'active']);
    });

// Étape 3 : Appliquer default et NOT NULL
Schema::table('users', function (Blueprint $table) {
    $table->string('status')->default('active')->nullable(false)->change();
});
```

---

#### Règle 03 — Changer le type d'une colonne (`change_column_type`)

**Danger** : Changer le type de données d'une colonne nécessite ALGORITHM=COPY sur MySQL, ce qui verrouille la table. Sur PostgreSQL, certains changements de type nécessitent une réécriture complète.

**Détection** : Appels à `$table->xxx('existing_column')->change()` où le type change.

**Message** :
```
Changer le type d'une colonne nécessite une réécriture de table qui
bloque lectures et écritures.

✅ Alternative sûre :

// Étape 1 : Créer une nouvelle colonne avec le bon type
Schema::table('users', function (Blueprint $table) {
    $table->text('bio_new')->nullable();
});

// Étape 2 : Copier les données (migration séparée, en batches)
// Étape 3 : Déployer le code pour lire/écrire la nouvelle colonne
// Étape 4 : Supprimer l'ancienne colonne
```

---

#### Règle 04 — Renommer une colonne (`rename_column`)

**Danger** : Renommer une colonne casse instantanément tout le code qui référence l'ancien nom. Aucun rollback possible sans re-renommer.

**Détection** : Appels à `$table->renameColumn('old', 'new')`.

**Message** :
```
Renommer une colonne utilisée par l'application provoquera des erreurs
immédiates.

✅ Alternative sûre :

1. Créer la nouvelle colonne
2. Écrire dans les deux colonnes (ancienne et nouvelle)
3. Backfill les données de l'ancienne vers la nouvelle
4. Basculer les lectures vers la nouvelle colonne
5. Arrêter d'écrire dans l'ancienne colonne
6. Supprimer l'ancienne colonne
```

---

#### Règle 05 — Renommer une table (`rename_table`)

**Danger** : Même problème que le renommage de colonne, mais à l'échelle de la table entière. Tout le code, les relations Eloquent, les foreign keys, les vues, etc. qui référencent l'ancien nom cassent immédiatement.

**Détection** : Appels à `Schema::rename('old', 'new')`.

---

#### Règle 06 — Ajouter un index non concurrent (`add_index_non_concurrent`)

**PostgreSQL uniquement**

**Danger** : Sur PostgreSQL, `CREATE INDEX` bloque les écritures sur la table. Sur des tables volumineuses, ça peut durer des minutes.

**Détection** : Appels à `$table->index(...)`, `$table->unique(...)` dans un Blueprint sans option `algorithm: 'concurrently'`.

**Message** :
```
Ajouter un index bloque les écritures sur PostgreSQL.

✅ Alternative sûre :

// Utilisez une migration sans transaction DDL
// et ajoutez l'index de manière concurrente :

public function up(): void
{
    // Pas de Schema::table ici — utilisation directe de DB
    DB::statement(
        'CREATE INDEX CONCURRENTLY idx_users_email ON users (email)'
    );
}
```

---

#### Règle 07 — Ajouter une foreign key (`add_foreign_key`)

**PostgreSQL uniquement**

**Danger** : Sur PostgreSQL, ajouter une foreign key bloque les écritures sur les **deux** tables impliquées pendant la validation.

**Message** :
```
Ajouter une foreign key bloque les écritures sur les deux tables
concernées sur PostgreSQL.

✅ Alternative sûre :

// Étape 1 : Ajouter la contrainte comme NOT VALID
DB::statement('
    ALTER TABLE orders
    ADD CONSTRAINT orders_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    NOT VALID
');

// Étape 2 : Valider dans une migration séparée
DB::statement('
    ALTER TABLE orders
    VALIDATE CONSTRAINT orders_user_id_foreign
');
```

---

#### Règle 08 — Définir NOT NULL sur colonne existante (`set_not_null`)

**Danger** : Sur PostgreSQL, `SET NOT NULL` bloque lectures et écritures pendant que chaque ligne est vérifiée. Sur MySQL, cela nécessite une copie de table.

**Message PostgreSQL** :
```
✅ Alternative sûre :

// Étape 1 : Ajouter une check constraint
DB::statement('
    ALTER TABLE users
    ADD CONSTRAINT users_email_not_null
    CHECK (email IS NOT NULL)
    NOT VALID
');

// Étape 2 : Valider (migration séparée)
DB::statement('
    ALTER TABLE users
    VALIDATE CONSTRAINT users_email_not_null
');

// Étape 3 : Appliquer NOT NULL et supprimer la constraint
Schema::table('users', function (Blueprint $table) {
    $table->string('email')->nullable(false)->change();
});
DB::statement('
    ALTER TABLE users
    DROP CONSTRAINT users_email_not_null
');
```

---

#### Règle 09 — Ajouter une contrainte unique (`add_unique_constraint`)

**PostgreSQL uniquement**

**Danger** : Créer une contrainte unique crée un index unique, ce qui bloque les lectures et écritures.

---

#### Règle 10 — Créer une table avec `force: true` (`create_table_force`)

**Danger** : `force: true` fait un `DROP TABLE IF EXISTS` avant le `CREATE TABLE`. En production, ça détruit irréversiblement les données.

**Détection** : Second argument de `Schema::create()` ou présence de `Schema::dropIfExists()` juste avant un `Schema::create()` sur la même table.

---

#### Règle 11 — Ajouter une colonne JSON au lieu de JSONB (`json_column`)

**PostgreSQL uniquement**

**Danger** : Le type `json` n'a pas d'opérateur d'égalité sur PostgreSQL, ce qui casse les requêtes `SELECT DISTINCT`.

**Message** :
```
✅ Utilisez jsonb au lieu de json :

$table->jsonb('metadata');  // au lieu de $table->json('metadata');
```

---

#### Règle 12 — Ajouter colonne auto-increment (`add_auto_increment`)

**Danger** : Ajouter une colonne auto-increment nécessite une copie de table sur MySQL.

---

#### Règle 13 — Backfill dans la même migration que le DDL (`backfill_in_migration`)

**Danger** : Laravel enveloppe chaque migration dans une transaction. Si vous faites du DDL (ALTER TABLE) ET du DML (UPDATE) dans la même migration, la table reste verrouillée pendant toute la durée du backfill.

**Détection** : Présence simultanée d'appels `Schema::table/create` et de `DB::table()->update()`, `Model::update()`, `DB::statement('UPDATE...')` dans la même migration.

**Message** :
```
Mixer des modifications de schéma et des mises à jour de données dans
la même migration maintient les verrous pour toute la durée du backfill.

✅ Alternative sûre :

Séparez en deux migrations :
1. Migration de schéma (ALTER TABLE)
2. Migration de données (en batches, hors transaction DDL)
```

---

#### Règle 14 — Utilisation de `execute()` ou `statement()` brut (`raw_sql`)

**Avertissement uniquement** (pas de blocage)

**Danger** : Les requêtes SQL brutes ne peuvent pas être inspectées par le package. Un avertissement rappelle au développeur de vérifier manuellement.

---

#### Règle 15 — Index non-unique avec plus de 3 colonnes (`wide_index`)

**Bonnes pratiques** (pas de blocage)

**Message** :
```
Les index non-uniques de plus de 3 colonnes sont rarement utiles et
ralentissent les écritures. Vérifiez que cet index est vraiment nécessaire.
```

---

#### Règle 16 — Ajouter une check constraint (`add_check_constraint`)

**PostgreSQL uniquement**

**Danger** : Ajouter une check constraint bloque lectures et écritures pendant la validation.

---

## 7. Mécanisme de bypass : `safetyAssured`

### Utilisation dans une migration

```php
use Grazulex\StrongMigrations\StrongMigrations;

class RemoveLegacyColumn extends Migration
{
    public function up(): void
    {
        StrongMigrations::safetyAssured(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('legacy_field');
            });
        });
    }
}
```

### Implémentation technique

```php
class StrongMigrations
{
    protected static bool $safetyAssured = false;

    public static function safetyAssured(Closure $callback): mixed
    {
        static::$safetyAssured = true;

        try {
            return $callback();
        } finally {
            static::$safetyAssured = false;
        }
    }

    public static function isSafetyAssured(): bool
    {
        return static::$safetyAssured;
    }
}
```

Le `MigrationAnalyzer` vérifie ce flag et skip les opérations encapsulées dans `safetyAssured`.

---

## 8. Règles personnalisées

### Ajouter une règle custom

```php
// Dans un ServiceProvider de l'application
use Grazulex\StrongMigrations\Facades\StrongMigrations;

StrongMigrations::addCheck(function (string $method, array $args) {
    if ($method === 'addIndex' && ($args[0] ?? '') === 'users') {
        StrongMigrations::stop("Plus d'index sur la table users !");
    }
});
```

### Personnaliser les messages

```php
StrongMigrations::setMessage('remove_column', "Message personnalisé...");
```

### Rendre certaines opérations safe par défaut

```php
// Dans config/strong-migrations.php
'safe_by_default' => true,
```

Quand activé, les opérations suivantes sont automatiquement exécutées de manière sûre :
- Ajout et suppression d'index → concurrent sur PostgreSQL
- Ajout de foreign key → NOT VALID + VALIDATE séparé
- Ajout de check constraint → NOT VALID + VALIDATE séparé
- SET NOT NULL → via check constraint

---

## 9. Commandes Artisan

### `php artisan migrate:check`

Analyse toutes les migrations en attente **sans les exécuter** et rapporte les violations.

```bash
$ php artisan migrate:check

  ┌──────────────────────────────────────────────────────────────┐
  │ laravel-strongmigrations — Analyse des migrations en attente │
  └──────────────────────────────────────────────────────────────┘

  ⛔ 2026_02_25_add_status_to_users.php
     → add_column_with_default : Colonne avec default NOT NULL

  ⚠️  2026_02_26_add_index_to_orders.php
     → wide_index : Index sur 4 colonnes

  ✅ 2026_02_27_create_invoices_table.php
     → Aucune violation

  Résultat : 1 erreur, 1 avertissement, 1 OK
```

**Retourne exit code 1** si des violations sont trouvées (utile pour CI).

### `php artisan migrate:analyze {migration}`

Analyse une migration spécifique en détail.

```bash
$ php artisan migrate:analyze 2026_02_25_add_status_to_users

  Analyse : 2026_02_25_add_status_to_users.php
  Driver  : MySQL 8.0.35
  Table   : users (estimé: 1,234,567 lignes)

  Opérations détectées :
    1. addColumn('status', 'string', default: 'active', nullable: false)
       → ⛔ add_column_with_default (sévérité: haute)

  Alternative sûre : [affiche le code complet]
```

### `php artisan strong-migrations:install`

Publie la configuration et crée un fichier d'initialisation optionnel.

---

## 10. Intégration CI/CD

### GitHub Actions

```yaml
# .github/workflows/migrations.yml
name: Migration Safety Check

on:
  pull_request:
    paths:
      - 'database/migrations/**'

jobs:
  check-migrations:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Check migration safety
        run: php artisan migrate:check --ansi
        env:
          STRONG_MIGRATIONS_MODE: block
```

### GitLab CI

```yaml
migration-check:
  stage: test
  script:
    - php artisan migrate:check
  rules:
    - changes:
        - database/migrations/*
```

### Formats de sortie

La commande `migrate:check` supporte plusieurs formats :

```bash
php artisan migrate:check --format=json    # Pour CI/CD
php artisan migrate:check --format=github  # Annotations GitHub Actions
php artisan migrate:check --format=text    # Défaut (terminal)
```

---

## 11. Structure des fichiers du package

```
laravel-strongmigrations/
├── src/
│   ├── StrongMigrationsServiceProvider.php
│   ├── StrongMigrations.php                     # Façade principale
│   ├── Analyzer/
│   │   ├── MigrationAnalyzer.php                # Parse les fichiers migration
│   │   ├── BlueprintInterceptor.php             # Intercepte les appels Blueprint
│   │   └── OperationExtractor.php               # Extrait les opérations de l'AST
│   ├── Rules/
│   │   ├── RuleInterface.php
│   │   ├── RuleEngine.php                       # Moteur de règles
│   │   ├── Concerns/
│   │   │   └── ChecksDatabaseVersion.php        # Trait pour vérifs version DB
│   │   ├── MySQL/
│   │   │   ├── AddColumnWithDefaultRule.php
│   │   │   ├── ChangeColumnTypeRule.php
│   │   │   └── SetNotNullRule.php
│   │   ├── PostgreSQL/
│   │   │   ├── AddIndexNonConcurrentRule.php
│   │   │   ├── AddForeignKeyRule.php
│   │   │   ├── AddCheckConstraintRule.php
│   │   │   ├── AddUniqueConstraintRule.php
│   │   │   ├── JsonColumnRule.php
│   │   │   └── SetNotNullRule.php
│   │   └── Common/
│   │       ├── RemoveColumnRule.php
│   │       ├── RenameColumnRule.php
│   │       ├── RenameTableRule.php
│   │       ├── CreateTableForceRule.php
│   │       ├── AddAutoIncrementRule.php
│   │       ├── BackfillInMigrationRule.php
│   │       ├── RawSqlRule.php
│   │       └── WideIndexRule.php
│   ├── Data/
│   │   ├── Operation.php                        # Value Object opération
│   │   ├── Violation.php                        # Value Object violation
│   │   ├── DatabaseContext.php                  # Contexte DB (driver, version)
│   │   └── Severity.php                         # Enum (high, medium, low, info)
│   ├── Console/
│   │   ├── MigrateCheckCommand.php              # artisan migrate:check
│   │   ├── MigrateAnalyzeCommand.php            # artisan migrate:analyze
│   │   └── InstallCommand.php                   # artisan strong-migrations:install
│   ├── Reporters/
│   │   ├── ReporterInterface.php
│   │   ├── ConsoleReporter.php                  # Sortie terminal
│   │   ├── JsonReporter.php                     # Sortie JSON
│   │   └── GithubActionsReporter.php            # Annotations GitHub
│   ├── Exceptions/
│   │   └── DangerousOperationException.php
│   └── Facades/
│       └── StrongMigrations.php
├── config/
│   └── strong-migrations.php
├── lang/
│   ├── en/
│   │   └── messages.php
│   └── fr/
│       └── messages.php
├── tests/
│   ├── Unit/
│   │   ├── Analyzer/
│   │   │   └── MigrationAnalyzerTest.php
│   │   ├── Rules/
│   │   │   ├── RemoveColumnRuleTest.php
│   │   │   ├── AddColumnWithDefaultRuleTest.php
│   │   │   ├── ChangeColumnTypeRuleTest.php
│   │   │   ├── RenameColumnRuleTest.php
│   │   │   ├── RenameTableRuleTest.php
│   │   │   ├── AddIndexNonConcurrentRuleTest.php
│   │   │   └── ...
│   │   └── Data/
│   │       └── OperationTest.php
│   ├── Feature/
│   │   ├── MigrateCheckCommandTest.php
│   │   ├── MigrateAnalyzeCommandTest.php
│   │   ├── SafetyAssuredTest.php
│   │   └── IntegrationTest.php
│   └── Fixtures/
│       └── Migrations/
│           ├── DangerousRemoveColumn.php
│           ├── DangerousAddColumnDefault.php
│           ├── SafeMigration.php
│           └── ...
├── .github/
│   └── workflows/
│       └── tests.yml
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── pint.json
├── README.md
├── CHANGELOG.md
└── LICENSE
```

---

## 12. Plan de développement (Roadmap)

### Phase 1 — MVP (Semaines 1-2)

**Objectif** : Le package fonctionne et détecte les 5 règles les plus critiques.

- [ ] Scaffolding du package (composer.json, ServiceProvider, config)
- [ ] `MigrationAnalyzer` avec PHP-Parser : extraction des opérations Blueprint
- [ ] `RuleEngine` avec architecture de règles pluggables
- [ ] 5 règles communes :
  - `RemoveColumnRule`
  - `RenameColumnRule`
  - `RenameTableRule`
  - `CreateTableForceRule`
  - `BackfillInMigrationRule`
- [ ] Mécanisme `safetyAssured`
- [ ] `DangerousOperationException` avec messages formatés
- [ ] Configuration de base (`enabled`, `mode`, `disabled_checks`)
- [ ] Tests unitaires pour chaque règle
- [ ] README basique

### Phase 2 — Règles spécifiques aux bases de données (Semaines 3-4)

**Objectif** : Couverture complète MySQL et PostgreSQL.

- [ ] Auto-détection du driver et de la version de DB
- [ ] `DatabaseContext` avec requête de version
- [ ] Règles MySQL :
  - `AddColumnWithDefaultRule` (avec distinction version < 8.0.12)
  - `ChangeColumnTypeRule`
  - `SetNotNullRule`
- [ ] Règles PostgreSQL :
  - `AddIndexNonConcurrentRule`
  - `AddForeignKeyRule`
  - `AddCheckConstraintRule`
  - `AddUniqueConstraintRule`
  - `JsonColumnRule`
  - `SetNotNullRule`
- [ ] Règles additionnelles communes :
  - `AddAutoIncrementRule`
  - `RawSqlRule`
  - `WideIndexRule`
- [ ] Support MariaDB
- [ ] Tests d'intégration avec SQLite en mémoire

### Phase 3 — Commandes Artisan et CI (Semaine 5)

**Objectif** : Commandes prêtes pour le développement et la CI.

- [ ] Commande `migrate:check`
- [ ] Commande `migrate:analyze`
- [ ] Commande `strong-migrations:install`
- [ ] Reporters : Console, JSON, GitHub Actions
- [ ] Configuration `start_after` pour projets existants
- [ ] Support `table_size_threshold`
- [ ] Exemples CI (GitHub Actions, GitLab CI)

### Phase 4 — Polish et release (Semaine 6)

**Objectif** : Package production-ready.

- [ ] Règles custom (`addCheck`, `stop!`)
- [ ] Mode `safe_by_default`
- [ ] Messages personnalisables
- [ ] Traductions (EN, FR)
- [ ] Documentation README complète avec tous les exemples
- [ ] CHANGELOG.md
- [ ] Tests de couverture > 90%
- [ ] Analyse PHPStan level 8
- [ ] Laravel Pint (formatting)
- [ ] GitHub Actions pour CI du package lui-même
- [ ] Publication sur Packagist
- [ ] Article de blog / LinkedIn pour le lancement

### Phase 5 — Post-launch (Semaines 7+)

- [ ] Support SQLite avancé
- [ ] Support SQL Server
- [ ] Intégration PHPStan (règle custom pour détecter les migrations dangereuses en analyse statique)
- [ ] Intégration Pest (plugin pour scanner les migrations dans les tests)
- [ ] Dashboard web optionnel (historique des violations par migration)
- [ ] Retries automatiques sur lock timeout (PostgreSQL)
- [ ] Analyse de l'impact des migrations sur les modèles Eloquent (cross-référencement avec le code applicatif)

---

## 13. Stratégie de tests

### Types de tests

#### Tests unitaires (par règle)

Chaque règle a son propre fichier de test avec des cas positifs (violation détectée) et négatifs (pas de violation).

```php
// tests/Unit/Rules/RemoveColumnRuleTest.php
it('detects dropColumn as dangerous', function () {
    $operation = new Operation(
        type: OperationType::DROP_COLUMN,
        table: 'users',
        column: 'email',
    );
    $context = new DatabaseContext(driver: 'mysql', version: '8.0.35');

    $rule = new RemoveColumnRule();
    $violation = $rule->check($operation, $context);

    expect($violation)->not->toBeNull();
    expect($violation->severity)->toBe(Severity::HIGH);
});

it('allows dropColumn inside safetyAssured', function () {
    StrongMigrations::safetyAssured(function () {
        $operation = new Operation(
            type: OperationType::DROP_COLUMN,
            table: 'users',
            column: 'email',
        );

        expect(StrongMigrations::isSafetyAssured())->toBeTrue();
    });
});
```

#### Tests d'intégration (migration réelle)

Utilisent des fichiers de migration fixtures pour tester le pipeline complet.

```php
// tests/Feature/IntegrationTest.php
it('blocks a dangerous migration', function () {
    // Copie la migration fixture dans le dossier de migrations
    // Exécute php artisan migrate
    // Vérifie que DangerousOperationException est lancée
});
```

#### Tests de la commande `migrate:check`

```php
it('returns exit code 1 when violations found', function () {
    $this->artisan('migrate:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('Opération dangereuse');
});
```

### Matrice de couverture

| Composant | Objectif couverture |
|-----------|:------------------:|
| Règles (Rules/) | 100% |
| Analyzer | 95%+ |
| Commandes Artisan | 90%+ |
| Reporters | 85%+ |
| Configuration | 90%+ |
| **Total** | **> 90%** |

---

## 14. Documentation et marketing

### README.md — Structure

1. **Bannière** avec logo et badges (CI, Packagist, downloads, license)
2. **Tagline** : "Détectez les migrations dangereuses avant qu'elles ne cassent votre production."
3. **Exemple visuel** : Screenshot du terminal avec une violation
4. **Installation** : 2 lignes (composer require + vendor:publish)
5. **Quick start** : Rien à faire, ça marche dès l'installation
6. **Liste des opérations détectées** : Tableau avec liens vers le détail
7. **Configuration** : Les options principales
8. **CI/CD** : Exemples GitHub Actions et GitLab CI
9. **Règles custom** : Comment en ajouter
10. **FAQ** : Questions fréquentes
11. **Inspiration** : Crédit à `strong_migrations` (Rails)

### Articles de lancement

1. **LinkedIn** : "Pourquoi vos migrations Laravel sont une bombe à retardement" — article narratif sur les risques, avec le package comme solution
2. **Laravel News** : Soumission pour publication
3. **Reddit r/laravel** : Post de présentation
4. **X/Twitter** : Thread avec GIF animé de la commande en action

### SEO Packagist

- **Keywords** : laravel, migrations, safety, database, production, zero-downtime, schema
- **Description** : "Catch unsafe migrations in development. Prevents dangerous operations, provides safer alternatives. Inspired by Rails strong_migrations."

---

## 15. Annexes techniques

### A. Événements de migration Laravel

Laravel dispatch les événements suivants (tous étendent `Illuminate\Database\Events\MigrationEvent`) :

| Événement | Quand |
|-----------|-------|
| `MigrationsStarted` | Avant l'exécution du batch complet |
| `MigrationStarted` | Avant chaque migration individuelle |
| `MigrationEnded` | Après chaque migration individuelle |
| `MigrationsEnded` | Après l'exécution du batch complet |

Le package écoute **`MigrationStarted`** pour intercepter chaque migration avant son exécution.

### B. Opérations DDL dangereuses par moteur

#### MySQL

| Opération | < 5.6 | 5.6 - 8.0.11 | >= 8.0.12 |
|-----------|:-----:|:------------:|:---------:|
| ADD COLUMN (sans default) | COPY | INPLACE | INSTANT |
| ADD COLUMN (avec default NOT NULL) | COPY | COPY | INSTANT |
| CHANGE COLUMN TYPE | COPY | COPY | COPY |
| ADD INDEX | COPY | INPLACE | INPLACE |
| DROP COLUMN | COPY | INPLACE | INPLACE |
| RENAME COLUMN | — | — | INSTANT (8.0+) |

#### PostgreSQL

| Opération | Danger | Alternative sûre |
|-----------|--------|-----------------|
| CREATE INDEX | Bloque écritures | CREATE INDEX CONCURRENTLY |
| ADD FOREIGN KEY | Bloque écritures (2 tables) | NOT VALID + VALIDATE |
| SET NOT NULL | Bloque lectures/écritures | CHECK CONSTRAINT + VALIDATE |
| ADD CHECK CONSTRAINT | Bloque si validé | NOT VALID + VALIDATE |
| ADD COLUMN + DEFAULT (< PG 11) | Réécriture table | Ajouter nullable + backfill |
| ADD COLUMN + DEFAULT (>= PG 11) | ✅ Sûr | — |

### C. Alternatives et outils connexes dans d'autres écosystèmes

| Écosystème | Outil | Stars | Description |
|------------|-------|:-----:|-------------|
| Rails | `strong_migrations` | 2,800+ | L'original, inspiration directe |
| Rails | `database_consistency` | 1,100+ | Vérifie cohérence modèle/DB |
| Django | `django-migration-linter` | 500+ | Lint des migrations Django |
| Elixir | `excellent_migrations` | 200+ | Safety checks pour Ecto |
| **Laravel** | **Rien** | — | **C'est le gap que nous comblons** |

### D. `composer.json` prévu

```json
{
    "name": "grazulex/laravel-strongmigrations",
    "description": "Catch unsafe migrations in development. Prevents dangerous database operations and provides safer alternatives.",
    "keywords": [
        "laravel",
        "migrations",
        "safety",
        "database",
        "production",
        "zero-downtime",
        "schema",
        "devtools"
    ],
    "homepage": "https://github.com/grazulex/laravel-strongmigrations",
    "license": "MIT",
    "type": "library",
    "authors": [
        {
            "name": "Jean-Marc Opis",
            "email": "...",
            "homepage": "https://github.com/grazulex"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/database": "^11.0|^12.0",
        "illuminate/console": "^11.0|^12.0",
        "illuminate/support": "^11.0|^12.0",
        "nikic/php-parser": "^5.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0|^10.0",
        "pestphp/pest": "^3.0",
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Grazulex\\StrongMigrations\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Grazulex\\StrongMigrations\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Grazulex\\StrongMigrations\\StrongMigrationsServiceProvider"
            ],
            "aliases": {
                "StrongMigrations": "Grazulex\\StrongMigrations\\Facades\\StrongMigrations"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

---

## Résumé

`laravel-strongmigrations` comble le gap le plus visible de l'écosystème Laravel en matière de sécurité des bases de données. Avec 16 règles de détection couvrant MySQL, PostgreSQL, MariaDB et SQLite, un mécanisme de bypass élégant, des commandes Artisan pour la CI, et une architecture extensible, ce package a le potentiel de devenir un standard dans la boîte à outils de tout développeur Laravel sérieux.

**Temps estimé** : 6 semaines pour une v1 production-ready.

**Premier commit** : Scaffolding + 5 règles communes + `safetyAssured` + `migrate:check`.
