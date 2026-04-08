@component('mail::message')
@if($logoUrl ?? null)
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="max-height: 48px; margin-bottom: 16px;">
@endif

# {{ __('escalated::emails.assigned.heading') }}

{{ __('escalated::emails.assigned.intro') }}

**{{ __('escalated::emails.assigned.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.assigned.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.assigned.priority_label') }}:** {{ $ticket->priority->label() }}
**{{ __('escalated::emails.assigned.status_label') }}:** {{ $ticket->status->label() }}

@component('mail::button', ['url' => $url, 'color' => $accentColor ?? null])
{{ __('escalated::emails.assigned.button') }}
@endcomponent

{{ __('escalated::emails.assigned.closing') }}

{{ __('escalated::emails.assigned.thanks') }},<br>
{{ config('app.name') }}

@if($footerText ?? null)
---
<small>{{ $footerText }}</small>
@endif
@endcomponent
