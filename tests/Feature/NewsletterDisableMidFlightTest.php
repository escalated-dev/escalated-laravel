<?php

use Escalated\Laravel\Mail\NewsletterMail;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterListMember;
use Illuminate\Support\Facades\Mail;

it('halts dispatch and 404s tracking endpoints when flag flips off mid-flight', function () {
    Mail::fake();
    config(['escalated.enable_newsletters' => true]);

    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $contactIds = [];
    for ($i = 0; $i < 5; $i++) {
        $c = Contact::create(['email' => "c{$i}@example.com"]);
        $contactIds[] = $c->id;
        NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c->id]);
    }
    $n = Newsletter::create([
        'subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id,
        'body_markdown' => 'hi', 'status' => 'sending', 'summary_total' => 5,
    ]);
    foreach ($contactIds as $i => $contactId) {
        NewsletterDelivery::create([
            'newsletter_id' => $n->id,
            'contact_id' => $contactId,
            'email_at_send' => "c{$i}@example.com",
            'status' => 'pending',
            'tracking_token' => 'tk-'.$i,
            'attempt_count' => 0,
            'is_test' => false,
        ]);
    }

    config(['escalated.enable_newsletters' => false]);

    $this->artisan('escalated:newsletters:dispatch')->assertExitCode(0);
    Mail::assertNothingSent();

    expect(NewsletterDelivery::count())->toBe(5);
    expect($n->fresh()->summary_sent)->toBe(0);
});
