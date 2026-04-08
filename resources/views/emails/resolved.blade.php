@component('mail::message')
@if($logoUrl ?? null)
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="max-height: 48px; margin-bottom: 16px;">
@endif

# {{ __('escalated::emails.resolved.heading') }}

{{ __('escalated::emails.resolved.intro') }}

**{{ __('escalated::emails.resolved.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.resolved.subject_label') }}:** {{ $ticket->subject }}

{{ __('escalated::emails.resolved.reopen_note') }}

@component('mail::button', ['url' => $url, 'color' => $accentColor ?? null])
{{ __('escalated::emails.resolved.button') }}
@endcomponent

{{ __('escalated::emails.resolved.thanks') }},<br>
{{ config('app.name') }}

@if($footerText ?? null)
---
<small>{{ $footerText }}</small>
@endif
@endcomponent
