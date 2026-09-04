<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the account holder that their password was changed, whether from
 * the settings screen or through a reset link. It is the only way they find
 * out about a change they did not make, and it explains why every other
 * device has just been signed out.
 *
 * Sent by ResetUserPassword and UpdateUserPassword, so both the web routes
 * and the mobile twins are covered. Never sent from the forgot-password
 * request itself, which would reveal whether an address exists.
 */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.password_changed.subject'))
            ->line(__('mail.password_changed.changed', ['email' => $notifiable->email]))
            ->line(__('mail.password_changed.signed_out'))
            ->line(__('mail.password_changed.not_you'))
            ->action(__('mail.password_changed.action'), config('app.frontend_url').'/forgot-password');
    }
}
