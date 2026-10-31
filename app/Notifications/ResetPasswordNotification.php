<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Lien de réinitialisation du mot de passe.
 * Mise en file : le temps de réponse ne révèle pas si l'adresse existe.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe Uptime Africa')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une demande de réinitialisation du mot de passe a été faite pour votre compte.')
            ->action('Choisir un nouveau mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien expire dans {$minutes} minutes et ne peut servir qu'une fois.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.')
            ->salutation('L\'équipe Uptime Africa');
    }
}
