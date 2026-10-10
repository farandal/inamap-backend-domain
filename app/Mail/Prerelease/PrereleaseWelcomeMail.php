<?php

namespace Domain\App\Mail\Prerelease;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Domain\App\Models\Prerelease\PrereleaseSignup;

/**
 * Welcome email for pre-release signups, sent in the language the visitor was
 * using on the landing page (stored as preferred_language on the signup).
 *
 * The brochure link comes from config('prerelease.brochure_url'); when empty
 * (e.g. the S3 asset is not published yet) the template omits it entirely.
 */
class PrereleaseWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public PrereleaseSignup $signup;

    public function __construct(PrereleaseSignup $signup)
    {
        $this->signup = $signup;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $locale = in_array($this->signup->preferred_language, ['en', 'es'])
            ? $this->signup->preferred_language
            : 'es';

        app()->setLocale($locale);

        $subject = $locale === 'en'
            ? 'Welcome to the INAMAP community!'
            : '¡Bienvenido a la comunidad de INAMAP!';

        $brochureUrl = trim((string) config('prerelease.brochure_url', ''));

        return $this
            ->subject($subject)
            ->view('emails.prerelease.welcome-' . $locale, [
                'signup'      => $this->signup,
                'brochureUrl' => $brochureUrl !== '' ? $brochureUrl : null,
            ]);
    }
}
