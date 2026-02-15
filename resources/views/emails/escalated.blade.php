@component('mail::message')
# {{ __('escalated::emails.escalated.heading') }}

{{ __('escalated::emails.escalated.intro') }}

**{{ __('escalated::emails.escalated.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.escalated.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.escalated.priority_label') }}:** {{ $ticket->priority->label() }}
@if($reason)
**{{ __('escalated::emails.escalated.reason_label') }}:** {{ $reason }}
@endif

{{ __('escalated::emails.escalated.attention_note') }}

@component('mail::button', ['url' => $url])
{{ __('escalated::emails.escalated.button') }}
@endcomponent

{{ __('escalated::emails.escalated.thanks') }},<br>
{{ config('app.name') }}
@endcomponent
