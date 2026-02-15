@component('mail::message')
# {{ __('escalated::emails.status_changed.heading') }}

{{ __('escalated::emails.status_changed.intro') }}

**{{ __('escalated::emails.status_changed.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.status_changed.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.status_changed.previous_status_label') }}:** {{ $oldStatus->label() }}
**{{ __('escalated::emails.status_changed.new_status_label') }}:** {{ $newStatus->label() }}

@component('mail::button', ['url' => $url])
{{ __('escalated::emails.status_changed.button') }}
@endcomponent

{{ __('escalated::emails.status_changed.thanks') }},<br>
{{ config('app.name') }}
@endcomponent
