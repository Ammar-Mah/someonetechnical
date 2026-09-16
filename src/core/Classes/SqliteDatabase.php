<?php

/**
 * The SQL engine without a database server: SQLite, in DB_PATH/database.sqlite.
 *
 * Database::getInstance() returns one whenever DB_NAME is empty, so a project
 * on DB_ENGINE 'sql' runs wherever PHP has pdo_sqlite, and moves to MySQL by
 * naming a database in runtime.local.php. Everything else is Database's:
 * SQLite reads the framework's backtick-quoted, bound SQL as written, but for
 * one number.
 *
 * Schema is where the two differ. A migration is written once, in the subset
 * both read (RULES.md section 8), and schema() makes the three adjustments
 * SQLite needs.
 */
class SqliteDatabase extends Database
{
    /** @param string|null $file a path or ':memory:'; null is DB_PATH/database.sqlite */
    public function __construct(private ?string $file = null)
    {
        parent::__construct();
    }

    protected function connect(): PDO
    {
        // FileStorage owns DB_PATH: it resolves the path, creates the directory
        // and denies it over HTTP.
        $file = $this->file ?? (new FileStorage())->directory() . DIRECTORY_SEPARATOR . 'database.sqlite';

        $pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // InnoDB enforces foreign keys; SQLite does only when asked.
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    /** SqlStorage writes MySQL's "no limit" for an offset alone; SQLite's is -1. */
    public function query($sql, $params = [])
    {
        return parent::query(str_replace('LIMIT 18446744073709551615 ', 'LIMIT -1 ', $sql), $params);
    }

    /**
     * MySQL's AUTO_INCREMENT is SQLite's AUTOINCREMENT. MySQL compares text
     * case-insensitively by default, so every text column is declared NOCASE,
     * which also covers sorting and UNIQUE. And MySQL names the table of an
     * index it drops, which SQLite refuses. Data statements pass untouched.
     */
    public function schema(string $sql): string
    {
        if (preg_match('/^\s*DROP\s+INDEX\s+(\S+)\s+ON\s/i', $sql, $m)) {
            return 'DROP INDEX ' . $m[1];
        }
        if (!preg_match('/^\s*(CREATE|ALTER)\s+TABLE\b/i', $sql)) {
            return $sql;
        }
        return preg_replace([
            '/\bAUTO_INCREMENT\b/i',
            '/((?:^|[(,]|\bADD(?:\s+COLUMN)?)\s*(?:`[^`]+`|"[^"]+"|\w+)\s+(?:(?:VAR)?CHAR\s*\(\s*\d+\s*\)|(?:TINY|MEDIUM|LONG)?TEXT\b))(?!\s+COLLATE\b)/im',
        ], ['AUTOINCREMENT', '$1 COLLATE NOCASE'], $sql);
    }
}
