<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryRateSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/little-guys-delivery.json')), true, 512, JSON_THROW_ON_ERROR);
        $expected = ['bullet', 'direct', 'rush', 'same_day', 'overnight'];
        if (array_column($data['services'], 'code') !== $expected || count($data['postal_zones']) !== 169) {
            throw new RuntimeException('Incomplete delivery rate dataset.');
        }
        $zones = [];
        foreach ($data['postal_zones'] as $prefix => $zone) {
            if (!preg_match('/^[A-Z][0-9][A-Z]$/', $prefix) || !is_int($zone) || $zone < 1 || $zone > 30) {
                throw new RuntimeException('Invalid postal zone.');
            }
            $zones[] = compact('prefix', 'zone');
        }
        $rates = $services = [];
        foreach ($data['services'] as $service) {
            $services[] = array_intersect_key($service, array_flip(['code', 'name', 'description']));
            if (count($service['rates']) !== 30) throw new RuntimeException('Missing origin zones.');
            foreach ($service['rates'] as $from => $row) {
                if (count($row) !== 30) throw new RuntimeException('Missing destination zones.');
                foreach ($row as $to => $amount) {
                    if ($amount !== null && (!is_int($amount) || $amount < 0)) throw new RuntimeException('Invalid rate.');
                    $rates[] = ['service_code' => $service['code'], 'from_zone' => $from + 1, 'to_zone' => $to + 1, 'amount_cents' => $amount];
                }
            }
        }
        DB::transaction(function () use ($zones, $services, $rates) {
            // Serialize imports with checkout/settings changes. Existing orders retain their charge snapshots.
            DB::table('general_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('delivery_postal_zones')->upsert($zones, ['prefix'], ['zone']);
            DB::table('delivery_services')->upsert($services, ['code'], ['name', 'description']);
            $edited = DB::table('delivery_rates')->where('revision', '>', 0)->get()->keyBy(fn($r) => $r->service_code.':'.$r->from_zone.':'.$r->to_zone);
            $rates = array_filter($rates, fn($r) => !$edited->has($r['service_code'].':'.$r['from_zone'].':'.$r['to_zone']));
            foreach (array_chunk($rates, 100) as $chunk) {
                DB::table('delivery_rates')->upsert($chunk, ['service_code', 'from_zone', 'to_zone'], ['amount_cents']);
            }
        });
    }
}
