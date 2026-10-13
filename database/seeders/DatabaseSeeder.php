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

        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'company_id' => Company::query()->orderBy('id')->value('id'),
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        // T1.3: the local test user sees every screen. Never on a server (this seeder refuses production).
        if (app()->environment(['local', 'testing'])) {
            $user->syncRoles([RolesAndPermissionsSeeder::OWNER]);
        }

        // T4.1: the proposed expense categories (O-11) on local computers; never on a server.
        if (app()->environment(['local', 'testing'])) {
            $this->call(TestExpenseCategoriesSeeder::class);
        }
    }
}
