<?php

namespace App\Http\Controllers\Settings;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorQrCodeController extends Controller
{
    /**
     * Get the SVG QR code for enabling two-factor authentication.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (
            is_null($user->two_factor_secret) ||
            ! is_null($user->two_factor_confirmed_at)
        ) {
            // 2FA not enabled or already confirmed, so no QR code to show for setup.
            return response()->json(['message' => 'Two-factor authentication is not pending confirmation.'], 400);
        }

        try {
            // Use Laravel Fortify's TwoFactorAuthenticationProvider to generate the proper OTPAUTH URL
            // This ensures all required parameters (algorithm, digits, period) are included
            $otpauthUrl = app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
                config('app.name'),
                $user->email,
                decrypt($user->two_factor_secret) // Properly decrypt the secret
            );

            // Validate that the secret is properly Base32 encoded
            $decryptedSecret = decrypt($user->two_factor_secret);
            if (! preg_match('/^[A-Z2-7]+=*$/', $decryptedSecret)) {
                Log::warning('Invalid Base32 secret detected for user: '.$user->id);

                return response()->json(['message' => 'Invalid two-factor authentication secret.'], 500);
            }

            // Generate QR code with optimal settings for authenticator app compatibility
            $svg = (new Writer(
                new ImageRenderer(
                    new RendererStyle(
                        256, // Increased size for better readability (minimum 192px recommended)
                        4,   // Small margin for better scanning
                        null, // SvgTarget (not needed for string output)
                        null, // EyeFill (default)
                        Fill::uniformColor(
                            new Rgb(255, 255, 255), // White background
                            new Rgb(0, 0, 0)        // Black foreground for maximum contrast
                        )
                    ),
                    new SvgImageBackEnd
                )
            ))->writeString($otpauthUrl);

            return response($svg)->header('Content-Type', 'image/svg+xml');

        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Failed to decrypt two_factor_secret for user: '.$user->id.' - '.$e->getMessage());

            return response()->json(['message' => 'Failed to generate QR code. Please try disabling and re-enabling 2FA.'], 500);
        } catch (\Exception $e) {
            Log::error('QR code generation failed for user: '.$user->id.' - '.$e->getMessage());

            return response()->json(['message' => 'Failed to generate QR code.'], 500);
        }
    }
}
