<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeploymentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $subject = $this->data['success'] 
            ? '🚀 Deployment Successful: ' . (isset($this->data['commit']['message']) ? substr($this->data['commit']['message'], 0, 50) . '...' : 'Unknown')
            : '❌ Deployment Failed: ' . (isset($this->data['error']) ? substr($this->data['error'], 0, 50) . '...' : 'Unknown error');

        return $this->subject($subject)
            ->view('emails.deployment-notification') // Changed from markdown() to view()
            ->with(['data' => $this->data]);
    }
}