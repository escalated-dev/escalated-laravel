<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Notifications\MentionNotification;
use Escalated\Laravel\Support\StaffAccess;
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

        $users = Escalated::userQuery()
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
     *
     * Only staff-authored content (agent or admin replies and internal
     * notes) mentions anyone. A customer or guest naming an agent in their
     * reply never creates a mention or a notification, so customers cannot
     * ping staff directly.
     */
    public function processMentions(Reply $reply, array $mentionedUserIds): void
    {
        if (empty($mentionedUserIds) || ! $this->authoredByStaff($reply)) {
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

            $user = Escalated::findUser($userId);

            if ($user) {
                $user->notify(new MentionNotification($reply));
            }
        }
    }

    /**
     * Whether the reply's author is an agent or admin. Guest replies have no
     * author; contacts and other non-user authors are never staff.
     */
    public function authoredByStaff(Reply $reply): bool
    {
        if ($reply->author_type === null || $reply->author_id === null) {
            return false;
        }

        return StaffAccess::isStaff($reply->author);
    }

    /**
     * Search agents by name or email for autocomplete.
     */
    public function getAgentSuggestions(string $query): Collection
    {
        $userModel = Escalated::userModel();
        $displayColumn = Escalated::userDisplayColumn();

        return Escalated::userQuery()
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
