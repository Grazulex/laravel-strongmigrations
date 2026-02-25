<?php

declare(strict_types=1);

return [
    'remove_column' => [
        'message' => 'Removing column `:column` that is used by the application will cause errors until application servers are restarted.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

Step 1 - Ignore the column in your Eloquent model:

    protected static array $ignoredColumns = [':column'];

Step 2 - Deploy the code.

Step 3 - Create a migration that drops the column, wrapped in:

    StrongMigrations::safetyAssured(function () {
        Schema::table('your_table', function (Blueprint $table) {
            $table->dropColumn(':column');
        });
    });
ALT,
    ],

    'rename_column' => [
        'message' => 'Renaming column `:from` to `:to` will cause immediate errors in any code referencing the old name.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

1. Create the new column
2. Write to both columns (old and new)
3. Backfill data from old to new
4. Switch reads to the new column
5. Stop writing to the old column
6. Drop the old column
ALT,
    ],

    'rename_table' => [
        'message' => 'Renaming table `:from` to `:to` will break all code, Eloquent relations, foreign keys, and views referencing the old name.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

1. Create the new table
2. Write to both tables
3. Backfill data from old to new
4. Switch reads to the new table
5. Stop writing to the old table
6. Drop the old table
ALT,
    ],

    'create_table_force' => [
        'message' => 'Using `dropIfExists` on table `:table` will permanently destroy data in production.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

Remove the `Schema::dropIfExists()` call. If you need to recreate a table,
use separate migrations with proper data backup procedures.
ALT,
    ],

    'backfill_in_migration' => [
        'message' => 'Mixing schema changes and data updates in the same migration keeps locks for the entire duration of the backfill.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

Separate into two migrations:
1. Schema migration (ALTER TABLE)
2. Data migration (in batches, outside DDL transaction)
ALT,
    ],

    'add_column_with_default' => [
        'message' => 'Adding column `:column` with a NOT NULL default causes a full table rewrite on MySQL < 8.0.12, locking reads and writes.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Step 1: Add the column as nullable
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->nullable();
});

// Step 2: Backfill existing rows (separate migration, in batches)
DB::table('your_table')->whereNull(':column')->update([':column' => 'default_value']);

// Step 3: Apply default and NOT NULL constraint
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->default('default_value')->nullable(false)->change();
});
ALT,
    ],

    'change_column_type' => [
        'message' => 'Changing the type of column `:column` requires a table rewrite that locks reads and writes.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Step 1: Create a new column with the desired type
Schema::table('your_table', function (Blueprint $table) {
    $table->text(':column_new')->nullable();
});

// Step 2: Copy data (separate migration, in batches)
// Step 3: Deploy code to read/write the new column
// Step 4: Drop the old column
ALT,
    ],

    'set_not_null_mysql' => [
        'message' => 'Setting NOT NULL on column `:column` requires a table copy on MySQL, locking reads and writes.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

1. Ensure all rows have a non-null value
2. Add a NOT NULL constraint in a separate migration wrapped in safetyAssured
ALT,
    ],

    'set_not_null_pgsql' => [
        'message' => 'Setting NOT NULL on column `:column` blocks reads and writes on PostgreSQL while every row is checked.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Step 1: Add a check constraint as NOT VALID
DB::statement('
    ALTER TABLE your_table
    ADD CONSTRAINT your_table_:column_not_null
    CHECK (:column IS NOT NULL)
    NOT VALID
');

// Step 2: Validate in a separate migration
DB::statement('
    ALTER TABLE your_table
    VALIDATE CONSTRAINT your_table_:column_not_null
');

// Step 3: Apply NOT NULL and drop the constraint
Schema::table('your_table', function (Blueprint $table) {
    $table->string(':column')->nullable(false)->change();
});
DB::statement('ALTER TABLE your_table DROP CONSTRAINT your_table_:column_not_null');
ALT,
    ],

    'add_index_non_concurrent' => [
        'message' => 'Adding an index blocks writes on PostgreSQL. On large tables, this can take minutes.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Use a migration without DDL transaction and add the index concurrently:
public function up(): void
{
    DB::statement('CREATE INDEX CONCURRENTLY idx_table_column ON your_table (column)');
}
ALT,
    ],

    'add_foreign_key' => [
        'message' => 'Adding a foreign key on column `:column` blocks writes on both tables on PostgreSQL during validation.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Step 1: Add the constraint as NOT VALID
DB::statement('
    ALTER TABLE your_table
    ADD CONSTRAINT your_table_:column_foreign
    FOREIGN KEY (:column) REFERENCES other_table(id)
    NOT VALID
');

// Step 2: Validate in a separate migration
DB::statement('
    ALTER TABLE your_table
    VALIDATE CONSTRAINT your_table_:column_foreign
');
ALT,
    ],

    'add_check_constraint' => [
        'message' => 'Adding a check constraint blocks reads and writes on PostgreSQL during validation.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Step 1: Add constraint as NOT VALID
DB::statement('ALTER TABLE your_table ADD CONSTRAINT ... CHECK (...) NOT VALID');

// Step 2: Validate in a separate migration
DB::statement('ALTER TABLE your_table VALIDATE CONSTRAINT ...');
ALT,
    ],

    'add_unique_constraint' => [
        'message' => 'Adding a unique constraint on column `:column` creates a unique index that blocks reads and writes on PostgreSQL.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

// Create the unique index concurrently first, then add the constraint:
DB::statement('CREATE UNIQUE INDEX CONCURRENTLY idx_table_:column ON your_table (:column)');
ALT,
    ],

    'json_column' => [
        'message' => 'Using `json` type for column `:column` on PostgreSQL lacks equality operator, breaking SELECT DISTINCT queries.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

Use jsonb instead of json:

$table->jsonb(':column');
ALT,
    ],

    'add_auto_increment' => [
        'message' => 'Adding auto-increment column `:column` requires a table copy on MySQL.',
        'safe_alternative' => <<<'ALT'
Safe alternative:

Consider adding the column without auto-increment and using a sequence
or application-level ID generation instead.
ALT,
    ],

    'raw_sql' => [
        'message' => 'Raw SQL statements cannot be inspected by strong-migrations. Please verify manually that this is safe.',
        'safe_alternative' => <<<'ALT'
Recommendation:

Review the raw SQL statement manually to ensure it does not cause
table locks, data loss, or other dangerous operations in production.
ALT,
    ],

    'wide_index' => [
        'message' => 'Non-unique index with :count columns (> 3) is rarely useful and slows down writes.',
        'safe_alternative' => <<<'ALT'
Recommendation:

Verify this index is truly necessary. Indexes with more than 3 columns
are rarely used effectively by the query optimizer.
ALT,
    ],
];
