@component('mail::message')
@if($logoUrl ?? null)
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="max-height: 48px; margin-bottom: 16px;">
@endif

# {{ __('escalated::emails.status_changed.heading') }}

{{ __('escalated::emails.status_changed.intro') }}

**{{ __('escalated::emails.status_changed.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.status_changed.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.status_changed.previous_status_label') }}:** {{ $oldStatus->label() }}
**{{ __('escalated::emails.status_changed.new_status_label') }}:** {{ $newStatus->label() }}

@component('mail::button', ['url' => $url, 'color' => $accentColor ?? null])
{{ __('escalated::emails.status_changed.button') }}
@endcomponent

{{ __('escalated::emails.status_changed.thanks') }},<br>
{{ config('app.name') }}

@if($footerText ?? null)
---
<small>{{ $footerText }}</small>
@endif
@endcomponent
