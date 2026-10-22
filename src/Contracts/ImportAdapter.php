<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Support\ExtractResult;

interface ImportAdapter
{
    /** Unique slug, e.g. "zendesk" */
    public function name(): string;

    /** Human-readable name, e.g. "Zendesk" */
    public function displayName(): string;

    /**
     * Fields required for authentication.
     * Each element: ['name' => string, 'label' => string, 'type' => 'text'|'password'|'url', 'help' => string]
     */
    public function credentialFields(): array;

    /** Validate credentials by making a test API call. */
    public function testConnection(array $credentials): bool;

    /** Ordered list of importable entity types, e.g. ['agents', 'tags', 'contacts', 'tickets', 'replies', 'attachments'] */
    public function entityTypes(): array;

    /** Default field mappings for an entity type. */
    public function defaultFieldMappings(string $entityType): array;

    /** Available source fields for an entity type (fetched from API using credentials). */
    public function availableSourceFields(string $entityType, array $credentials): array;

    /**
     * Extract a batch of records. Returns null cursor in ExtractResult when exhausted.
     *
     * Attachment records carry the file's bytes as `content`. A `path` is
     * accepted instead only inside `escalated.attachments.import_path` on the
     * import disk.
     */
    public function extract(string $entityType, array $credentials, ?string $cursor): ExtractResult;
}
