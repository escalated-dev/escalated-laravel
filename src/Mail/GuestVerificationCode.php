<?php

namespace Escalated\Laravel\Mail;

use Illuminate\Mail\Mailable;

class GuestVerificationCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function build(): static
    {
        return $this->subject('Your support verification code')
            ->text('escalated::mail.guest-verification');
    }
}
