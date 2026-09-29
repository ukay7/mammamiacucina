<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SqliteToMysql
{
    /** The destination MUST be a new, empty database. No existing database is erased. */
    public function transfer(string $source, string $target, callable $progress): array
    {
        $src = DB::connection($source);
        $dst = DB::connection($target);
        if ($src->getDriverName() !== 'sqlite' || $dst->getDriverName() !== 'mysql') {
            throw new RuntimeException('Expected SQLite source and MySQL destination.');
        }
        if ($dst->getSchemaBuilder()->getTables($dst->getDatabaseName())) {
            throw new RuntimeException('Destination database is not empty. Nothing was overwritten.');
        }
        if ($src->select('PRAGMA foreign_key_check')) {
            throw new RuntimeException('Source has invalid foreign keys. Repair them before migrating.');
        }
        $expected = collect(glob(database_path('migrations/*.php')))->map(fn ($p) => basename($p, '.php'))->sort()->values()->all();
        $applied = $src->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($expected !== $applied) {
            throw new RuntimeException('Source migrations must match this release before transferring.');
        }
        $original = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection($target);
            if (Artisan::call('migrate', ['--database' => $target, '--force' => true]) !== 0) {
                throw new RuntimeException('Destination schema creation failed.');
            }
        } finally {
            DB::setDefaultConnection($original);
        }
        $tables = array_column($src->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        $metadata = $dst->getSchemaBuilder()->getTables($dst->getDatabaseName());
        foreach ($metadata as $table) {
            if (strtolower($table['engine'] ?? '') !== 'innodb') { throw new RuntimeException('All target tables must use InnoDB for a transactional copy.'); }
        }
        $targetTables = array_column($metadata, 'name');
        sort($targetTables);
        if ($tables !== $targetTables) {
            throw new RuntimeException('Source/destination table sets differ; refusing to omit any tables.');
        }
        $summary = [];
        $dst->statement('SET FOREIGN_KEY_CHECKS=0');
        $dst->beginTransaction();
        try {
            // Only migration-created defaults in the newly created destination are removed.
            foreach ($tables as $table) {
                $dst->table($table)->delete();
            }
            foreach ($tables as $table) {
                $columns = $dst->getSchemaBuilder()->getColumns($table);
                $names = array_column($columns, 'name');
                $sourceColumns = $src->getSchemaBuilder()->getColumns($table);
                $sourceNames = array_column($sourceColumns, 'name');
                sort($names); sort($sourceNames);
                if ($names !== $sourceNames) {
                    throw new RuntimeException("Column mismatch in $table.");
                }
                $batch = [];
                foreach ($src->table($table)->cursor() as $row) {
                    $batch[] = (array) $row;
                    if (count($batch) === 100) {
                        $dst->table($table)->insert($batch); $batch = [];
                    }
                }
                if ($batch) { $dst->table($table)->insert($batch); }
                $types = [];
                foreach ($sourceColumns as $column) { $types[$column['name']] = $column['type_name']; }
                foreach ($columns as $column) {
                    // MySQL JSON canonicalizes whitespace/key order. MariaDB reports JSON as longtext.
                    if (in_array($column['type_name'], ['json', 'decimal', 'numeric', 'double', 'float', 'tinyint', 'int', 'bigint', 'smallint', 'mediumint'])) {
                        $types[$column['name']] = $column['type_name'];
                    }
                }
                $left = $this->fingerprints($src, $table, $types);
                $right = $this->fingerprints($dst, $table, $types);
                if ($left !== $right) {
                    throw new RuntimeException("Content verification failed for $table. No cutover performed.");
                }
                $summary[$table] = count($left);
                $progress("Verified $table: ".count($left).' rows');
            }
            // Enabling FOREIGN_KEY_CHECKS does not validate rows loaded while it was disabled.
            foreach ($tables as $table) {
                foreach ($dst->getSchemaBuilder()->getForeignKeys($table) as $fk) {
                    $q = $dst->table($table.' as child')->whereNotExists(function ($q) use ($fk) {
                        $q->selectRaw('1')->from($fk['foreign_table'].' as parent');
                        foreach ($fk['columns'] as $i => $column) {
                            $q->whereColumn('child.'.$column, 'parent.'.$fk['foreign_columns'][$i]);
                        }
                    });
                    foreach ($fk['columns'] as $column) { $q->whereNotNull('child.'.$column); }
                    if ($q->exists()) { throw new RuntimeException("Foreign key verification failed for $table."); }
                }
            }
            $dst->commit();
        } catch (\Throwable $e) {
            $dst->rollBack();
            throw $e;
        } finally {
            $dst->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        // Preserve sequences even when the highest historical ID has been deleted.
        foreach ($src->select('SELECT name, seq FROM sqlite_sequence') as $sequence) {
            if (in_array($sequence->name, $tables, true)) {
                $table = str_replace('`', '``', $sequence->name);
                $next = (int) $sequence->seq + 1;
                $dst->statement("ALTER TABLE `$table` AUTO_INCREMENT = $next");
            }
        }
        return $summary;
    }

    private function fingerprints($connection, string $table, array $types): array
    {
        $hashes = [];
        foreach ($connection->table($table)->cursor() as $row) {
            $values = (array) $row; ksort($values);
            foreach ($values as $key => &$value) {
                if ($value === null) { continue; }
                $type = $types[$key] ?? '';
                if ($type === 'json') {
                    $value = $this->canonicalJson(json_decode($value, true, 512, JSON_THROW_ON_ERROR));
                } elseif (preg_match('/int|decimal|numeric|double|float|boolean/', $type)) {
                    $value = (string) $value;
                    if (str_contains(strtolower($value), 'e')) { throw new RuntimeException("Scientific numeric notation in $table.$key needs manual review."); }
                    if (str_contains($value, '.')) { $value = rtrim(rtrim($value, '0'), '.'); }
                    if ($value === '-0') { $value = '0'; }
                } else { $value = (string) $value; }
            }
            unset($value);
            $hashes[] = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        }
        sort($hashes, SORT_STRING);
        return $hashes;
    }

    private function canonicalJson(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        if (!array_is_list($value)) { ksort($value); }
        foreach ($value as &$item) { $item = $this->canonicalJson($item); }
        return $value;
    }
}
