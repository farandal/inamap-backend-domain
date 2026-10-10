<?php

namespace Domain\Database\Seeders\Common;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Monedas disponibles en la plataforma. El registro de prueba valida
 * `primary_currency` contra esta tabla (exists:currencies,code) y usa
 * config('system.default_currency') = CLP si no se envía ninguna, así que sin
 * estas filas ningún cliente puede registrarse. Idempotente.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'CLP', 'symbol' => '$', 'format' => ',', 'is_default' => true],
            ['code' => 'USD', 'symbol' => 'US$', 'format' => ',00', 'is_default' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency + ['is_enabled' => true]);
        }

        $this->command?->info('INAMAP: monedas sembradas (CLP, USD)');
    }
}
