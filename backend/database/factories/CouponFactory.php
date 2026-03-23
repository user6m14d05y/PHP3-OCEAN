<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['fixed', 'percent']);
        
        return [
            'code' => strtoupper($this->faker->unique()->lexify('????')) . $this->faker->unique()->numerify('####'),
            'type' => $type,
            'value' => $type === 'percent' ? $this->faker->randomFloat(2, 5, 50) : $this->faker->randomFloat(2, 10000, 100000),
            'min_order_value' => $this->faker->optional(0.7)->randomFloat(2, 50000, 500000), // 70% chance to have a min_order_value
            'usage_limit' => $this->faker->optional(0.5)->numberBetween(10, 500), // 50% chance to have a usage limit
            'used_count' => $this->faker->numberBetween(0, 5),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 week'),
            'end_date' => $this->faker->dateTimeBetween('+2 weeks', '+2 months'),
            'is_active' => $this->faker->boolean(80), // 80% active
        ];
    }
}
