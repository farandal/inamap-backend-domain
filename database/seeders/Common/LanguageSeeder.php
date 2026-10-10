<?php

namespace Domain\Database\Seeders\Common;

use App\Models\Language;
use Illuminate\Database\Seeder;

/** Idiomas de la plataforma (el registro acepta es | en). Idempotente. */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_active' => true],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }

        $this->command?->info('INAMAP: idiomas sembrados (es, en)');
    }
}
