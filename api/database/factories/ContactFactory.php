<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Real towns and matching postcode districts across the South West.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const TOWNS = [
        ['Bristol', 'BS6 6'],
        ['Bath', 'BA1 5'],
        ['Cheltenham', 'GL50 2'],
        ['Gloucester', 'GL1 3'],
        ['Exeter', 'EX4 4'],
        ['Taunton', 'TA1 3'],
        ['Weston-super-Mare', 'BS23 1'],
        ['Swindon', 'SN1 3'],
        ['Frome', 'BA11 1'],
        ['Wells', 'BA5 2'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$city, $district] = fake()->randomElement(self::TOWNS);

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '07'.fake()->numerify('### ######'),
            'address_line_1' => fake()->buildingNumber().' '.fake()->streetName(),
            'city' => $city,
            'postcode' => $district.strtoupper(fake()->lexify('??')),
            'country' => 'GB',
        ];
    }
}
