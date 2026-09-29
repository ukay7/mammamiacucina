<?php

namespace Tests\Unit;

use App\Console\Commands\MoveToMysql;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class MysqlEnvironmentTest extends TestCase
{
    public function test_credentials_round_trip_without_changing_app_key_or_other_settings(): void
    {
        $original = "APP_KEY=base64:keep-existing-key\nDB_CONNECTION=sqlite\nDB_DATABASE=/path/old.sqlite\nMAIL_MAILER=smtp\nDB_URL=mysql://old\n";
        $password = 'quotes" backslash\\ hash# dollar${APP_KEY} space';
        $updated = MoveToMysql::mysqlEnvironment($original, '127.0.0.1', '3306', 'mammamiacucina', 'mmc_app', $password);
        $values = Dotenv::parse($updated);
        $this->assertSame($password, $values['DB_PASSWORD']);
        $this->assertSame('base64:keep-existing-key', $values['APP_KEY']);
        $this->assertSame('smtp', $values['MAIL_MAILER']);
        $this->assertSame('mysql', $values['DB_CONNECTION']);
        $this->assertSame('', $values['DB_URL']);
        $this->assertSame('mammamiacucina', $values['DB_DATABASE']);
    }

    public function test_generated_password_meets_mysql_character_class_requirements(): void
    {
        $password = MoveToMysql::generateDatabasePassword();
        $this->assertGreaterThanOrEqual(64, strlen($password));
        foreach (['/[A-Z]/', '/[a-z]/', '/[0-9]/', '/[^A-Za-z0-9]/'] as $pattern) {
            $this->assertMatchesRegularExpression($pattern, $password);
        }
        $this->assertNotSame($password, MoveToMysql::generateDatabasePassword());
    }

    public function test_multiline_credentials_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        MoveToMysql::mysqlEnvironment('', 'localhost', '3306', 'mmc', 'mmc', "one\ntwo");
    }
}
