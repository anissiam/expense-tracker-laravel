<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'currency_code' => 'USD',
            'monthly_salary_day' => 1,
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
        ];
    }
}