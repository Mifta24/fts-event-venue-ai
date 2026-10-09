<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\Planner\PlannerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('planner:chat {venue? : Venue slug} {--locale=id : id|en|ja}')]
#[Description('Talk to the AI Planner for a venue from the terminal, for testing the tool-calling loop before the web UI exists.')]
class PlannerChatCommand extends Command
{
    public function handle(PlannerService $service): int
    {
        $venue = $this->argument('venue')
            ? Venue::where('slug', $this->argument('venue'))->first()
            : Venue::first();

        if (! $venue) {
            $this->error('No venue found. Seed one first: php artisan db:seed');

            return self::FAILURE;
        }

        if (blank(config('services.local_llm.base_url'))) {
            $this->error('LOCAL_LLM_BASE_URL is not set in .env — the planner has no model endpoint to call.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $conversation = $service->startConversation($venue, $locale);

        $this->info("Chatting with the AI Planner for {$venue->name} ({$locale}). Type 'exit' to quit.");
        $this->newLine();

        while (true) {
            $guestMessage = $this->ask('You');

            if ($guestMessage === null || in_array(trim($guestMessage), ['exit', 'quit'], true)) {
                break;
            }

            $message = $service->reply($venue, $conversation, $guestMessage);

            $this->newLine();
            $this->line('<fg=cyan>Planner:</> '.($message->content ?? '(no text — see UI payload below)'));

            if ($message->ui_payload) {
                $this->line('<fg=gray>[ui_payload] '.json_encode($message->ui_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</>');
            }

            $conversation->refresh();
            if ($conversation->isHandedOver()) {
                $this->warn('Conversation handed over to human staff. Ending session.');
                break;
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
