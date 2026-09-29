<?php

namespace Tests\Integration;

use App\Services\SqliteToMysql;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/** Opt in with MMC_MYSQL_TRANSFER_TESTS=1; creates/drops only randomly named test databases. */
class SqliteToMysqlTest extends TestCase
{
    private ?PDO $admin = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MMC_MYSQL_TRANSFER_TESTS') !== '1') { $this->markTestSkipped('Requires a dedicated local MySQL test administrator.'); }
        $host = getenv('MMC_MYSQL_TEST_HOST') ?: '127.0.0.1';
        $user = getenv('MMC_MYSQL_TEST_USER') ?: 'root';
        $password = getenv('MMC_MYSQL_TEST_PASSWORD') ?: '';
        $this->admin = new PDO("mysql:host=$host;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database = 'mmc_transfer_test_'.bin2hex(random_bytes(8));
        $this->admin->exec("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.copy_source' => array_replace(config('database.connections.sqlite'), ['url' => null, 'database' => ':memory:']),
            'database.connections.copy_target' => array_replace(config('database.connections.mysql'), ['url' => null, 'host' => $host, 'database' => $this->database, 'username' => $user, 'password' => $password, 'unix_socket' => '', 'engine' => 'InnoDB'])]);
        DB::setDefaultConnection('copy_source');
        Artisan::call('migrate', ['--database' => 'copy_source', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->database && $this->admin) {
            DB::purge('copy_target');
            $this->admin->exec("DROP DATABASE `{$this->database}`");
        }
        parent::tearDown();
    }

    public function test_every_table_credentials_json_foreign_keys_and_deleted_id_sequence_are_preserved(): void
    {
        $source = DB::connection('copy_source');
        $role = $source->table('roles')->first();
        $source->table('users')->insert(['id' => 100, 'name' => 'Sequence placeholder', 'email' => 'removed@example.test', 'password' => 'old']);
        $source->table('users')->where('id', 100)->delete();
        $source->table('users')->insert(['id' => 5, 'name' => 'Customer É', 'email' => 'customer@example.test', 'password' => 'unchanged-hash', 'remember_token' => 'unchanged-token', 'role_id' => $role->id, 'created_at' => '2026-09-30 01:02:03']);
        $source->table('roles')->where('id', $role->id)->update(['permissions' => '{"z":1,"a":["é",2]}']);
        $summary = app(SqliteToMysql::class)->transfer('copy_source', 'copy_target', fn () => null);
        $target = DB::connection('copy_target');
        $this->assertCount(count($source->getSchemaBuilder()->getTables()), $summary);
        $this->assertSame(1, $summary['users']);
        $this->assertSame('unchanged-hash', $target->table('users')->value('password'));
        $this->assertSame('unchanged-token', $target->table('users')->value('remember_token'));
        $this->assertEquals($role->id, $target->table('users')->value('role_id'));
        $this->assertSame(101, $target->table('users')->insertGetId(['name' => 'Next', 'email' => 'next@example.test', 'password' => 'hash']));
    }

    public function test_only_an_empty_database_can_be_reused(): void
    {
        \App\Console\Commands\MoveToMysql::assertEmptyDatabase($this->admin, $this->database);
        DB::connection('copy_target')->statement('CREATE TABLE protected_data (id INT PRIMARY KEY)');
        $this->expectException(\RuntimeException::class);
        \App\Console\Commands\MoveToMysql::assertEmptyDatabase($this->admin, $this->database);
    }

    public function test_existing_destination_is_never_erased(): void
    {
        DB::connection('copy_target')->statement('CREATE TABLE protected_data (id INT PRIMARY KEY)');
        DB::connection('copy_target')->table('protected_data')->insert(['id' => 42]);
        try {
            app(SqliteToMysql::class)->transfer('copy_source', 'copy_target', fn () => null);
            $this->fail('Populated database was accepted');
        } catch (\RuntimeException $e) { $this->assertStringContainsString('not empty', $e->getMessage()); }
        $this->assertEquals(42, DB::connection('copy_target')->table('protected_data')->value('id'));
    }

    public function test_mysql_unique_collation_conflict_rolls_back_without_changing_source(): void
    {
        foreach (['Customer@example.test', 'customer@example.test'] as $email) {
            DB::connection('copy_source')->table('users')->insert(['name' => 'Case conflict', 'email' => $email, 'password' => 'hash']);
        }
        try {
            app(SqliteToMysql::class)->transfer('copy_source', 'copy_target', fn () => null);
            $this->fail('A MySQL uniqueness conflict was not rejected');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(2, DB::connection('copy_source')->table('users')->count());
            $this->assertSame(0, DB::connection('copy_target')->table('users')->count());
        }
    }
}
