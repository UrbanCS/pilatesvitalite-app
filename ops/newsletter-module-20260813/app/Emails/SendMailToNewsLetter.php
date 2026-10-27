<?php

namespace Modules\NewsLetter\app\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMailToNewsLetter extends Mailable
{
    use Queueable, SerializesModels;

    public $mail_subject;
    public $mail_template;
    public $unsubscribe_url;

    public function __construct($mail_subject, $mail_template, $unsubscribe_url)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_template = $mail_template;
        $this->unsubscribe_url = $unsubscribe_url;
    }

    public function build(): self
    {
        return $this->subject($this->mail_subject)->view('newsletter::send_bulk_mail_template', [
            'mail_template' => $this->mail_template,
            'unsubscribe_url' => $this->unsubscribe_url,
        ]);
    }
}
