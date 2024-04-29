<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GigNotification extends Notification
{
    use Queueable;
    public $gig;
    public $user;
    /**
     * Create a new notification instance.
     */
    public function __construct($gig , $user)
    {
        //
        $this->gig = $gig;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
            'name' => $this->user->name,
            'message' => 'creating a new gig',
            'picture' => $this->user->picture,
        ];
    }
}
