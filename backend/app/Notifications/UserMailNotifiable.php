<?php

namespace App\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;

/**
 * On-demand notifiable that routes email while preserving the user's name
 * for notification templates (e.g. "Hello {name}") and never touches the database channel.
 */
class UserMailNotifiable extends AnonymousNotifiable
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        $this->route('mail', $email);
    }
}
