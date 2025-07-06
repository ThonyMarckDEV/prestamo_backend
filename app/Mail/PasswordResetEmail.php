<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $resetUrl;

    public function __construct($user, $resetUrl)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
    }

    public function build()
    {
        return $this->view('emails.password_reset')
            ->subject('Solicitud de Cambio de Contraseña')
            ->with([
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
            ]);
    }
}