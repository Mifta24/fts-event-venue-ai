<?php

namespace App\Console\Commands;

use App\Models\Space;
use App\Models\SpaceInventory;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=365 : How many days ahead spaces should be open for booking}')]
#[Description('Open further days for every space, repeating the units and prices of the week before; existing days and bookings are never changed')]
class ExtendInventory extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        Space::query()->where('is_active', true)->with('venue')->each(function (Space $space) use ($days, &$added): void {
            $added += $this->extend($space, $days);
        });

        $this->info("Opened {$added} day(s).");

        return self::SUCCESS;
    }

    private function extend(Space $space, int $days): int
    {
        $last = SpaceInventory::query()->where('space_id', $space->id)->orderByDesc('event_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::now($space->venue->timezone)->startOfDay()->addDays($days);
        $week = SpaceInventory::query()->where('space_id', $space->id)
            ->whereDate('event_date', '>', $last->event_date->copy()->subDays(7)->toDateString())
            ->get()
            ->keyBy(fn (SpaceInventory $day) => $day->event_date->toDateString())
            ->map(fn (SpaceInventory $day) => ['total_units' => $day->total_units, 'price' => (float) $day->price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->event_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $template = $week[$date->subDays(7)->toDateString()] ?? ['total_units' => $last->total_units, 'price' => (float) $last->price];
            $week[$date->toDateString()] = $template;

            $rows[] = [
                'space_id' => $space->id,
                'event_date' => $date->startOfDay()->toDateTimeString(),
                'total_units' => $template['total_units'],
                'booked_units' => 0,
                'price' => $template['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $inserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $inserted += SpaceInventory::query()->insertOrIgnore($chunk);
        }

        return $inserted;
    }
}
