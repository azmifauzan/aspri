<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $user->profile()->create([
            'birth_day' => $input['birth_day'],
            'birth_month' => $input['birth_month'],
            'call_preference' => $input['call_preference'],
            'aspri_name' => $input['aspri_name'],
            'aspri_persona' => $input['aspri_persona'],
            'timezone' => $input['timezone'] ?? 'Asia/Jakarta',
            'locale' => $input['locale'] ?? 'id',
        ]);

        return $user;
    }
}
