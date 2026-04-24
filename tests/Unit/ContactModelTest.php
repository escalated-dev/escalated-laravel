<?php

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Ticket;

it('uses the dynamic table name from config', function () {
    $c = new Contact;
    expect($c->getTable())->toBe('escalated_contacts');
});

it('casts metadata to array', function () {
    $c = Contact::create([
        'email' => 'alice@example.com',
        'name' => 'Alice',
        'metadata' => ['referrer' => 'widget'],
    ]);
    $fresh = Contact::find($c->id);
    expect($fresh->metadata)->toBe(['referrer' => 'widget']);
});

describe('findOrCreateByEmail', function () {
    it('creates a new Contact for a never-seen email', function () {
        $c = Contact::findOrCreateByEmail('new@user.com', 'New User');
        expect($c->email)->toBe('new@user.com');
        expect($c->name)->toBe('New User');
    });

    it('normalizes case + whitespace on create', function () {
        $c = Contact::findOrCreateByEmail('  MIX@Case.COM ');
        expect($c->email)->toBe('mix@case.com');
    });

    it('returns the existing contact for a repeat email', function () {
        $first = Contact::findOrCreateByEmail('alice@example.com', 'Alice');
        $second = Contact::findOrCreateByEmail('ALICE@example.com');
        expect($second->id)->toBe($first->id);
    });

    it('fills in a blank name on an existing contact', function () {
        $c = Contact::create([
            'email' => 'alice@example.com',
            'name' => null,
            'metadata' => [],
        ]);
        $result = Contact::findOrCreateByEmail('alice@example.com', 'Alice');
        expect($result->name)->toBe('Alice');
    });

    it('does not overwrite a non-blank name', function () {
        $c = Contact::create([
            'email' => 'alice@example.com',
            'name' => 'Alice',
            'metadata' => [],
        ]);
        $result = Contact::findOrCreateByEmail('alice@example.com', 'Different');
        expect($result->name)->toBe('Alice');
    });
});

describe('promoteToUser', function () {
    it('sets user_id on the contact and back-stamps requester_id on prior tickets', function () {
        $c = Contact::findOrCreateByEmail('guest@example.com', 'Guest');
        $t1 = Ticket::factory()->create(['contact_id' => $c->id]);
        $t2 = Ticket::factory()->create(['contact_id' => $c->id]);

        $c->promoteToUser(555);

        expect($c->fresh()->user_id)->toBe(555);
        expect($t1->fresh()->requester_id)->toBe(555);
        expect($t2->fresh()->requester_id)->toBe(555);
    });
});
