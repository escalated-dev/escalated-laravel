<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Notifications\MentionNotification;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Support\StaffDirectory;
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

        // Match @username (single word, no braces). An @ that follows a word
        // character is part of an email address (jo@example.com), not a
        // mention, and a trailing full stop ends the sentence, not the name.
        preg_match_all('/(?<![A-Za-z0-9_.+-])@([a-zA-Z0-9_.-]+)(?![^{]*\})/', $body, $simpleMatches);
        $simple = array_map(fn ($name) => rtrim($name, '.-'), $simpleMatches[1] ?? []);

        $names = array_filter(array_merge($bracedMatches[1] ?? [], $simple), fn ($name) => trim($name) !== '');

        if (empty($names)) {
            return [];
        }

        // The display column falls back to email on hosts without it
        // (no `name` column), as the requester search does.
        $displayColumn = Escalated::userSearchableDisplayColumn();
        $keyName = Escalated::newUserModel()->getKeyName();

        $names = array_values(array_unique(array_map('trim', $names)));

        $users = Escalated::userQuery()
            ->where(function ($query) use ($names, $displayColumn) {
                $query->whereIn($displayColumn, $names);
                if ($displayColumn !== 'email') {
                    // Also try matching email for simple @username format
                    $query->orWhereIn('email', $names);
                }
            })
            ->pluck($keyName)
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
     * Search agents by name or email for autocomplete. Only users the staff
     * gates (or `escalated.authorization.agent_scope`) treat as agents or
     * admins are suggested.
     */
    public function getAgentSuggestions(string $query): Collection
    {
        $search = Escalated::userQuery()
            ->where(fn ($q) => Escalated::applyUserSearch($q, $query));

        return StaffDirectory::staff($search, 50)
            ->take(10)
            ->map(fn ($user) => StaffDirectory::row($user))
            ->values();
    }
}
