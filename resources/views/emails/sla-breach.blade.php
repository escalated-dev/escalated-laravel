@component('mail::message')
@if($logoUrl ?? null)
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="max-height: 48px; margin-bottom: 16px;">
@endif

# {{ __('escalated::emails.sla_breach.heading') }}

{{ __('escalated::emails.sla_breach.intro') }}

**{{ __('escalated::emails.sla_breach.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.sla_breach.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.sla_breach.priority_label') }}:** {{ $ticket->priority->label() }}
**{{ __('escalated::emails.sla_breach.breach_type_label') }}:** {{ $breachType === 'first_response' ? __('escalated::emails.sla_breach.first_response') : __('escalated::emails.sla_breach.resolution') }}

{{ __('escalated::emails.sla_breach.attention_note') }}

@component('mail::button', ['url' => $url, 'color' => $accentColor ?? null])
{{ __('escalated::emails.sla_breach.button') }}
@endcomponent

{{ __('escalated::emails.sla_breach.thanks') }},<br>
{{ config('app.name') }}

@if($footerText ?? null)
---
<small>{{ $footerText }}</small>
@endif
@endcomponent
