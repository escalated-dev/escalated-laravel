@component('mail::message')
# {{ __('escalated::emails.reply.heading', ['reference' => $ticket->reference]) }}

{{ __('escalated::emails.reply.intro') }}

**{{ __('escalated::emails.reply.subject_label') }}:** {{ $ticket->subject }}

---

{{ $reply->body }}

---

@component('mail::button', ['url' => $url])
{{ __('escalated::emails.reply.button') }}
@endcomponent

{{ __('escalated::emails.reply.thanks') }},<br>
{{ config('app.name') }}
@endcomponent
