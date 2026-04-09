<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Notifications\MentionNotification;
use Illuminate\Support\Collection;

class MentionService
{
    /**
     * Extract mentioned user IDs from a reply/note body.
     *
     * Supports two formats:
     *
     *   @username   — matched against the user display column
     *
     *   @{Full Name} — matched against the user display column
     *
     * @return array<int> Array of user IDs
     */
    public function extractMentions(string $body): array
    {
        $matches = [];

        // Match @{Full Name} (braces format)
        preg_match_all('/@\{([^}]+)\}/', $body, $bracedMatches);

        // Match @username (single word, no braces)
        preg_match_all('/@([a-zA-Z0-9_.-]+)(?![^{]*\})/', $body, $simpleMatches);

        $names = array_merge($bracedMatches[1] ?? [], $simpleMatches[1] ?? []);

        if (empty($names)) {
            return [];
        }

        $userModel = Escalated::userModel();
        $displayColumn = Escalated::userDisplayColumn();

        $names = array_unique(array_map('trim', $names));

        $users = $userModel::query()
            ->where(function ($query) use ($names, $displayColumn) {
                foreach ($names as $name) {
                    $query->orWhere($displayColumn, $name);
                }
                // Also try matching email for simple @username format
                foreach ($names as $name) {
                    $query->orWhere('email', $name);
                }
            })
            ->pluck('id')
            ->all();

        return array_values(array_unique($users));
    }

    /**
     * Create mention records and send notifications for the given reply.
     */
    public function processMentions(Reply $reply, array $mentionedUserIds): void
    {
        if (empty($mentionedUserIds)) {
            return;
        }

        $userModel = Escalated::userModel();

        foreach ($mentionedUserIds as $userId) {
            // Don't let the author mention themselves
            if (
                $reply->author_type !== null
                && $reply->author_id == $userId
                && $reply->author_type === (new $userModel)->getMorphClass()
            ) {
                continue;
            }

            $mention = Mention::create([
                'reply_id' => $reply->id,
                'user_id' => $userId,
            ]);

            $user = $userModel::find($userId);

            if ($user) {
                $user->notify(new MentionNotification($reply));
            }
        }
    }

    /**
     * Search agents by name or email for autocomplete.
     */
    public function getAgentSuggestions(string $query): Collection
    {
        $userModel = Escalated::userModel();
        $displayColumn = Escalated::userDisplayColumn();

        return $userModel::query()
            ->where(function ($q) use ($query, $displayColumn) {
                $q->where($displayColumn, 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->filter(function ($user) {
                if (method_exists($user, 'escalated_agent') && $user->escalated_agent()) {
                    return true;
                }
                if (method_exists($user, 'escalated_admin') && $user->escalated_admin()) {
                    return true;
                }

                // Fallback: check common boolean attributes
                return ! empty($user->is_agent) || ! empty($user->is_admin);
            })
            ->map(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();
    }
}
