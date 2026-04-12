<?php

namespace App\Mail;

use App\Services\GoogleClient;
use Google\Service\Gmail\Message as GmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class GmailApiTransport extends AbstractTransport
{
    public function __construct(private readonly GoogleClient $googleClient)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $gmail = $this->googleClient->gmail();

        if ($gmail === null) {
            throw new \RuntimeException('Gmail API is not available — Google account may not be connected or gmail.send scope is missing.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $raw = base64_encode($email->toString());
        // Gmail API requires URL-safe base64.
        $raw = strtr($raw, ['+' => '-', '/' => '_', '=' => '']);

        $gmailMessage = new GmailMessage;
        $gmailMessage->setRaw($raw);

        $gmail->users_messages->send('me', $gmailMessage);
    }

    public function __toString(): string
    {
        return 'gmail+api://';
    }
}
