<?php

declare(strict_types=1);

namespace StaticFormMailer\Mail;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerAdapter implements Mailer
{
    /**
     * @param array<string, mixed> $smtpConfig
     * @param array<string, mixed> $mailConfig
     */
    public function __construct(
        private readonly array $smtpConfig,
        private readonly array $mailConfig
    ) {
    }

    public function send(MailMessage $message): void
    {
        $mailer = new PHPMailer(true);

        try {
            $this->configureTransport($mailer);
            $this->configureMessage($mailer, $message);
            if (!$mailer->send()) {
                throw new RuntimeException('SMTP delivery failed.');
            }
        } catch (PhpMailerException $exception) {
            throw new RuntimeException('SMTP delivery failed.', 0, $exception);
        }
    }

    private function configureTransport(PHPMailer $mailer): void
    {
        $mailer->isSMTP();
        $mailer->Host = (string) $this->smtpConfig['host'];
        $mailer->Port = (int) $this->smtpConfig['port'];
        $mailer->SMTPAuth = ($this->smtpConfig['authenticate'] ?? true) === true;
        $mailer->Username = (string) ($this->smtpConfig['username'] ?? '');
        $mailer->Password = (string) ($this->smtpConfig['password'] ?? '');
        $mailer->Timeout = (int) ($this->smtpConfig['timeout_seconds'] ?? 15);

        $authType = $this->smtpConfig['auth_type'] ?? null;
        if (is_string($authType) && $authType !== '') {
            $mailer->AuthType = $authType;
        }

        $encryption = $this->smtpConfig['encryption'] ?? 'none';
        if ($encryption === 'smtps') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'starttls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        $tls = $this->smtpConfig['tls'] ?? [];
        $sslOptions = [
            'verify_peer' => ($tls['verify_peer'] ?? true) === true,
            'verify_peer_name' => ($tls['verify_peer_name'] ?? true) === true,
            'allow_self_signed' => ($tls['allow_self_signed'] ?? false) === true,
        ];

        $optionalTlsOptions = [
            'cafile' => $tls['ca_file'] ?? null,
            'peer_name' => $tls['peer_name'] ?? null,
            'local_cert' => $tls['client_certificate'] ?? null,
            'local_pk' => $tls['client_private_key'] ?? null,
            'passphrase' => $tls['client_private_key_passphrase'] ?? null,
        ];
        foreach ($optionalTlsOptions as $name => $value) {
            if (is_string($value) && $value !== '') {
                $sslOptions[$name] = $value;
            }
        }

        $mailer->SMTPOptions = ['ssl' => $sslOptions];
    }

    private function configureMessage(PHPMailer $mailer, MailMessage $message): void
    {
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom(
            (string) $this->mailConfig['from']['address'],
            (string) ($this->mailConfig['from']['name'] ?? '')
        );

        $returnPath = $this->mailConfig['return_path'] ?? null;
        if (is_string($returnPath) && $returnPath !== '') {
            $mailer->Sender = $returnPath;
        }

        foreach ($message->recipients as $recipient) {
            $mailer->addAddress($recipient['address'], $recipient['name'] ?? '');
        }
        foreach ($message->cc as $recipient) {
            $mailer->addCC($recipient['address'], $recipient['name'] ?? '');
        }
        foreach ($message->bcc as $recipient) {
            $mailer->addBCC($recipient['address'], $recipient['name'] ?? '');
        }

        if ($message->replyToAddress !== '') {
            $mailer->addReplyTo($message->replyToAddress, $message->replyToName);
        }

        $mailer->Subject = $message->subject;
        $mailer->isHTML(true);
        $mailer->Body = $message->htmlBody;
        $mailer->AltBody = $message->textBody;
    }
}
