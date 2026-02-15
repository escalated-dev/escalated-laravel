@component('mail::message')
# {{ __('escalated::emails.new_ticket.heading') }}

{{ __('escalated::emails.new_ticket.intro') }}

**{{ __('escalated::emails.new_ticket.reference_label') }}:** {{ $ticket->reference }}
**{{ __('escalated::emails.new_ticket.subject_label') }}:** {{ $ticket->subject }}
**{{ __('escalated::emails.new_ticket.priority_label') }}:** {{ $ticket->priority->label() }}

{{ $ticket->description }}

@component('mail::button', ['url' => $url])
{{ __('escalated::emails.new_ticket.button') }}
@endcomponent

{{ __('escalated::emails.new_ticket.thanks') }},<br>
{{ config('app.name') }}
@endcomponent
