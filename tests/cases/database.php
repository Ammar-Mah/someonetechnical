<?php

/**
 * The schema the SQL engine runs, and the columns the application relies on.
 *
 * Every database/ pair is applied here the way the DEV migrator applies it -
 * one statement per ';' at the end of a line, each through the connection's
 * schema() - on an in-memory SQLite database, so a case never touches data/.
 * The checks run the same pairs up, down and up through the migrator itself,
 * on SQLite and on MySQL.
 */

/** A schema file's statements, split as the migrator splits them. */
function database_statements(string $file): array
{
    $statements = [];
    foreach (preg_split('/;[ \t]*(\r?\n|$)/', (string)file_get_contents($file)) as $chunk) {
        $chunk = trim((string)preg_replace('/^\s*(--[^\n]*|#[^\n]*)\s*$/m', '', $chunk));
        if ($chunk !== '') {
            $statements[] = $chunk;
        }
    }

    return $statements;
}

/** Every forward file in name order, or every .down.sql in reverse order. */
function database_apply(SqliteDatabase $db, bool $up): void
{
    $files = array_filter(glob(ROOT . '/database/*.sql') ?: [],
        fn(string $file): bool => str_ends_with($file, '.down.sql') !== $up);
    sort($files, SORT_STRING);

    foreach ($up ? $files : array_reverse($files) as $file) {
        foreach (database_statements($file) as $statement) {
            $db->getConnection()->exec($db->schema($statement));
        }
    }
}

/** The tables in $db, SQLite's own left out. */
function database_tables(SqliteDatabase $db): array
{
    return $db->getConnection()
        ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** name => "TYPE NULL", "TYPE NOT NULL" or "TYPE PRIMARY KEY", in column order. */
function database_columns(SqliteDatabase $db, string $table): array
{
    $columns = [];
    foreach ($db->getConnection()->query("PRAGMA table_info($table)")->fetchAll() as $column) {
        $columns[$column['name']] = $column['type']
            . ($column['pk'] ? ' PRIMARY KEY' : ($column['notnull'] ? ' NOT NULL' : ' NULL'));
    }

    return $columns;
}

group('database');

test('intake_requests holds one column per intake question, and the contact', function () {
    $db = new SqliteDatabase(':memory:');
    database_apply($db, true);

    // #41 names these; the intake (#42) stores into them. A schema file that
    // has run on DEV is never edited: a change is the next pair.
    same([
        'id'             => 'INTEGER PRIMARY KEY',
        'building'       => 'TEXT NULL',
        'ai_tool'        => 'VARCHAR(100) NULL',
        'stuck_on'       => 'TEXT NULL',
        'is_live'        => 'VARCHAR(20) NULL',
        'help_wanted'    => 'VARCHAR(20) NULL',
        'contact_name'   => 'VARCHAR(200) NOT NULL',
        'contact_email'  => 'VARCHAR(254) NOT NULL',
        'preferred_time' => 'VARCHAR(200) NULL',
        'created_at'     => 'DATETIME NOT NULL',
        'updated_at'     => 'DATETIME NOT NULL',
        'deleted_at'     => 'DATETIME NULL',
    ], database_columns($db, 'intake_requests'));

    $indexed = $db->getConnection()->query('PRAGMA index_info(intake_requests_created_at)')->fetchAll(PDO::FETCH_COLUMN, 2);
    same(['created_at'], $indexed, 'requests are read newest first');
});

test('every pair reverses, and applies again after its reverse', function () {
    $db = new SqliteDatabase(':memory:');
    database_apply($db, true);
    $tables = database_tables($db);
    $columns = database_columns($db, 'intake_requests');

    database_apply($db, false);
    same([], database_tables($db), 'tables the .down.sql files leave behind');

    database_apply($db, true);
    same($tables, database_tables($db));
    same($columns, database_columns($db, 'intake_requests'));
});
