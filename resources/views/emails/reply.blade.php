@component('mail::message')
@if($logoUrl ?? null)
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="max-height: 48px; margin-bottom: 16px;">
@endif

# {{ __('escalated::emails.reply.heading', ['reference' => $ticket->reference]) }}

{{ __('escalated::emails.reply.intro') }}

**{{ __('escalated::emails.reply.subject_label') }}:** {{ $ticket->subject }}

---

{{ $reply->body }}

---

@component('mail::button', ['url' => $url, 'color' => $accentColor ?? null])
{{ __('escalated::emails.reply.button') }}
@endcomponent

{{ __('escalated::emails.reply.thanks') }},<br>
{{ config('app.name') }}

@if($footerText ?? null)
---
<small>{{ $footerText }}</small>
@endif
@endcomponent
