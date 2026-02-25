<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketMergeService
{
    /**
     * Merge source ticket into target ticket.
     *
     * Moves all replies from the source to the target, adds system notes
     * on both tickets, and closes the source with a merged_into_id reference.
     */
    public function merge(Ticket $source, Ticket $target, ?int $mergedByUserId = null): void
    {
        DB::transaction(function () use ($source, $target, $mergedByUserId) {
            // Move all replies from source to target
            Reply::where('ticket_id', $source->id)->update(['ticket_id' => $target->id]);

            // Add system note on the target ticket
            Reply::create([
                'ticket_id' => $target->id,
                'body' => "Ticket {$source->reference} was merged into this ticket.",
                'is_internal_note' => true,
                'is_pinned' => false,
                'author_type' => null,
                'author_id' => null,
                'metadata' => [
                    'system_note' => true,
                    'merge_source' => $source->reference,
                    'merged_by' => $mergedByUserId,
                ],
            ]);

            // Add system note on the source ticket
            Reply::create([
                'ticket_id' => $source->id,
                'body' => "This ticket was merged into {$target->reference}.",
                'is_internal_note' => true,
                'is_pinned' => false,
                'author_type' => null,
                'author_id' => null,
                'metadata' => [
                    'system_note' => true,
                    'merge_target' => $target->reference,
                    'merged_by' => $mergedByUserId,
                ],
            ]);

            // Close source ticket and set merged_into_id
            $source->update([
                'status' => TicketStatus::Closed->value,
                'merged_into_id' => $target->id,
            ]);
        });
    }
}
