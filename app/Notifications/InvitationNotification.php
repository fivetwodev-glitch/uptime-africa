<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitation d'un membre : il choisit lui-même son mot de passe via un lien à usage unique. */
class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $agencyName, public string $invitedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(), 'invitation' => 1]);
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject("Invitation à rejoindre {$this->agencyName} sur Uptime Africa")
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line("{$this->invitedBy} vous invite à rejoindre l'agence {$this->agencyName} sur Uptime Africa.")
            ->action('Activer mon compte', $url)
            ->line("Ce lien expire dans {$minutes} minutes. Passé ce délai, demandez à votre administrateur de renvoyer l'invitation.")
            ->salutation('L\'équipe Uptime Africa');
    }
}
