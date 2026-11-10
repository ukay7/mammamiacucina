<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Business dates use Toronto; stored instants, audit records and gateway clocks stay UTC. */
final class BusinessTime
{
    public static function zone(): string
    {
        return config('app.business_timezone', 'America/Toronto');
    }

    public static function local(DateTimeInterface|string $value): CarbonImmutable
    {
        return ($value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC'))->setTimezone(self::zone());
    }

    public static function now(): CarbonImmutable
    {
        return self::local(Carbon::now('UTC'));
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'd M Y H:i T'): ?string
    {
        return $value === null || $value === '' ? null : self::local($value)->format($format);
    }

    /** Convert a Toronto calendar date to the existing UTC query/storage convention. */
    public static function start(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::zone())->startOfDay()->utc();
    }

    /** Exclusive end, including the entire local day even when it has 23 or 25 hours. */
    public static function end(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::zone())->startOfDay()->addDay()->utc();
    }

    public static function input(?string $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, self::zone());
        if (! $local || $local->format('Y-m-d\TH:i') !== $value) {
            throw ValidationException::withMessages([$field => 'Enter a valid Toronto date and time. This time may fall in the daylight-saving clock change.']);
        }
        // datetime-local has no UTC offset. Refuse the repeated fall-back hour rather than guess.
        $transitions = (new DateTimeZone(self::zone()))->getTransitions($local->timestamp - 86400, $local->timestamp + 86400);
        foreach (array_slice($transitions ?: [], 1) as $i => $transition) {
            $overlap = $transitions[$i]['offset'] - $transition['offset'];
            if ($overlap > 0 && $local->timestamp >= $transition['ts'] - $overlap && $local->timestamp < $transition['ts'] + $overlap) {
                throw ValidationException::withMessages([$field => 'This Toronto time occurs twice during the daylight-saving clock change. Choose a time outside the repeated hour.']);
            }
        }
        return $local->utc();
    }

    /** SQL day buckets with PHP's DST rules; no MySQL timezone-table installation required. */
    public static function dayExpression(string $column, string $from, string $to): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_.]*$/iD', $column)) {
            throw new \InvalidArgumentException('Invalid timestamp column.');
        }
        $start = self::start($from);
        $end = self::end($to);
        $transitions = (new DateTimeZone(self::zone()))->getTransitions($start->timestamp, $end->timestamp);
        $offset = $transitions[0]['offset'];
        $date = static function (int $seconds) use ($column): string {
            return match (DB::connection()->getDriverName()) {
                'sqlite' => "DATE($column, '$seconds seconds')",
                'pgsql' => "DATE($column + INTERVAL '$seconds seconds')",
                default => "DATE(DATE_ADD($column, INTERVAL $seconds SECOND))",
            };
        };
        $cases = [];
        foreach (array_slice($transitions, 1) as $transition) {
            $boundary = gmdate('Y-m-d H:i:s', $transition['ts']);
            $cases[] = "WHEN $column < '$boundary' THEN ".$date($offset);
            $offset = $transition['offset'];
        }
        return $cases ? 'CASE '.implode(' ', $cases).' ELSE '.$date($offset).' END' : $date($offset);
    }
}
