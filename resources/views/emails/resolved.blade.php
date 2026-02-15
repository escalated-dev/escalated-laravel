@component('mail::message')
# {{ __('escalated::emails.resolved.heading') }}

{{ __('escalated::emails.resolved.intro') }}

**{{ __('escalated::emails.resolved.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.resolved.subject_label') }}:** {{ $ticket->subject }}

{{ __('escalated::emails.resolved.reopen_note') }}

@component('mail::button', ['url' => $url])
{{ __('escalated::emails.resolved.button') }}
@endcomponent

{{ __('escalated::emails.resolved.thanks') }},<br>
{{ config('app.name') }}
@endcomponent
