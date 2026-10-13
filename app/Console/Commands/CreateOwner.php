<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Companies\SystemCompany;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * BR-USR-07: the first Owner is created only on the server, with a hidden password.
 * Refused when any Owner exists. There is no web route for this.
 */
class CreateOwner extends Command
{
    protected $signature = 'zb:create-owner';

    protected $description = 'Create the first Owner (server only, refused when an Owner exists)';

    public function handle(SystemCompany $system): int
    {
        $company = $system->find();

        if ($company === null) {
            $this->error('System data is missing. Deploy a release first (zb-deploy-release runs SystemDataSeeder).');

            return self::FAILURE;
        }

        if (User::query()->role(RolesAndPermissionsSeeder::OWNER)->exists()) {
            $this->error('An Owner already exists. Add more users on the Users page.');

            return self::FAILURE;
        }

        $input = [
            'name' => (string) $this->ask('Name'),
            'email' => mb_strtolower(trim((string) $this->ask('E-mail'))),
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Password again'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()], // BR-SEC-02
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($company, $input): User {
            $user = new User;
            $user->forceFill([
                'company_id' => $company->id,
                'name' => trim($input['name']),
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'is_active' => true,
                'must_change_password' => false, // the administrator chose it (BR-USR-07)
            ])->save();
            $user->assignRole(RolesAndPermissionsSeeder::OWNER);

            AuditLogger::log(AuditActions::SERVER_COMMAND, $user, [], ['command' => 'zb:create-owner', 'user_id' => $user->id]);

            return $user;
        });

        $this->info("Owner {$user->name} created.");

        return self::SUCCESS;
    }
}
