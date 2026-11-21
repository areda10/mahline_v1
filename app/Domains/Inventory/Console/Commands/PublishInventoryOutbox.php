<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Console\Commands;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PublishInventoryOutbox extends Command
{
    protected $signature = 'inventory:publish-outbox {--limit=100}';

    protected $description = 'Publish pending Inventory outbox messages.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = InventoryOutboxMessage::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', now())
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        $published = 0;
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$published): void {
                    $message = InventoryOutboxMessage::query()
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if ($message === null || $message->published_at !== null) {
                        return;
                    }

                    event(new InventoryDomainEvent(
                        (string) $message->event_key,
                        (array) $message->payload,
                    ));

                    $message->attempts++;
                    $message->published_at = now();
                    $message->last_error = null;
                    $message->save();
                    $published++;
                }, 3);
            } catch (Throwable $exception) {
                InventoryOutboxMessage::query()
                    ->whereKey($id)
                    ->whereNull('published_at')
                    ->update([
                        'attempts' => DB::raw('attempts + 1'),
                        'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                        'available_at' => now()->addMinutes(5),
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->info(sprintf('%d outbox message(s) published.', $published));

        return self::SUCCESS;
    }
}
