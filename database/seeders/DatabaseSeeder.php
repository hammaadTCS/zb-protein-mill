<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local computers, cloud sessions and tests only. Servers run SystemDataSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DatabaseSeeder is for local use. Servers use SystemDataSeeder.');
        }

        $this->call(SystemDataSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(TestSettingsSeeder::class);
        }

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'company_id' => Company::query()->orderBy('id')->value('id'),
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
