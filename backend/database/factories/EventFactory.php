<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->catchPhrase();
        $startsAt = $this->faker->dateTimeBetween('now', '+3 months');

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description' => $this->faker->optional()->paragraph(),
            'location' => $this->faker->optional()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+1 day'),
            'allow_guest_checkout' => $this->faker->boolean(),
            'ticket_price' => $this->faker->randomElement([0, 25, 50, 100]),
            'currency' => 'TND',
            'capacity' => $this->faker->optional()->numberBetween(50, 1000),
            'is_published' => $this->faker->boolean(80),
            'is_default' => false,
        ];
    }

    /**
     * Indicate the event is published.
     */
    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    /**
     * Indicate the event allows guest checkout.
     */
    public function guestCheckout(): static
    {
        return $this->state(fn () => ['allow_guest_checkout' => true]);
    }
}
