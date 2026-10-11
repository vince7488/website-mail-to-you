<?php

declare(strict_types=1);

namespace StaticFormMailer\Mail;

interface Mailer
{
    public function send(MailMessage $message): void;
}
