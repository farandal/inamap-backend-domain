<?php

namespace Domain\App\Http\Controllers\API\Prerelease;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Domain\App\Mail\Prerelease\PrereleaseWelcomeMail;
use Domain\App\Models\Prerelease\PrereleaseSignup;

/**
 * Public (unauthenticated) endpoint that captures pre-release interest from
 * the landing page and queues the localized welcome email.
 */
class PublicPrereleaseSignupController extends Controller
{
    /**
     * POST /api/prerelease/register
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'contact_phone'      => 'nullable|string|max:50',
            'business_website'   => 'nullable|string|max:255',
            'business_instagram' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|in:en,es',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        // Idempotent on email: re-submitting the same address updates the
        // details but does not create a duplicate row nor re-send the email.
        $existing = PrereleaseSignup::where('email', $email)->first();

        if ($existing) {
            $existing->update([
                'name'               => $validated['name'],
                'contact_phone'      => $validated['contact_phone'] ?? $existing->contact_phone,
                'business_website'   => $validated['business_website'] ?? $existing->business_website,
                'business_instagram' => $validated['business_instagram'] ?? $existing->business_instagram,
                'preferred_language' => $validated['preferred_language'] ?? $existing->preferred_language,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Already registered'),
            ]);
        }

        $signup = PrereleaseSignup::create([
            'name'               => $validated['name'],
            'email'              => $email,
            'contact_phone'      => $validated['contact_phone'] ?? null,
            'business_website'   => $validated['business_website'] ?? null,
            'business_instagram' => $validated['business_instagram'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'es',
        ]);

        try {
            Mail::to($signup->email)->queue(new PrereleaseWelcomeMail($signup));
        } catch (\Exception $e) {
            // Signup is stored either way; the email failure must not break the flow
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' - Failed to queue welcome email: ' . $e->getMessage(), [
                'signup_id' => $signup->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'id'      => $signup->id,
        ], 201);
    }
}
