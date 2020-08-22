<?php

namespace phpBB2;

use LatteFactory;
use Nette\Mail\FallbackMailer;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Nette\Mail\SmtpMailer;

/**
 * Class Mailer
 *
 * @package phpBB2
 */
class Mailer
{

    /**
     * @var array $boardConfig
     *
     */
    private $boardConfig;

    /**
     * @var Message $message
     */
    private $message;

    /**
     * Mailer constructor.
     * @param LatteFactory $latteFactory
     * @param array $boardConfig
     * @param string $emailName
     * @param array $params
     * @param string $lang
     * @param string $subject
     * @param string $to
     */
    public function __construct(LatteFactory $latteFactory, array $boardConfig, $emailName, array $params, $lang, $subject, $to)
    {
        $this->boardConfig = $boardConfig;

        $mailMessage = new Message();
        $mailMessage->setFrom($boardConfig['board_email'])
            ->addReplyTo($boardConfig['board_email'])
            ->setSubject($subject)
            ->addTo($to)
            ->setHtmlBody($latteFactory->renderMail($emailName, $lang, $params));

        $this->message = $mailMessage;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return void
     */
    public function send()
    {
        $sendMailMailer = new SendmailMailer();

        $smtpMailer = new SmtpMailer(
            [
                'host'     => $this->boardConfig['smtp_host'],
                'username' => $this->boardConfig['smtp_username'],
                'password' => $this->boardConfig['smtp_password'],
                'secure'    => 'ssl',
            ]
        );

        $mailer = new FallbackMailer([$smtpMailer, $sendMailMailer]);
        $mailer->send($this->message);
    }
}
