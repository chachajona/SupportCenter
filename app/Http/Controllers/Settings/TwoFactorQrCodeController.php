<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Writer;

class TwoFactorQrCodeController extends Controller
{
    /**
     * Get the SVG QR code for enabling two-factor authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (
            is_null($user->two_factor_secret) ||
            !is_null($user->two_factor_confirmed_at)
        ) {
            // 2FA not enabled or already confirmed, so no QR code to show for setup.
            // You might want to return a specific error or redirect.
            return response()->json(['message' => 'Two-factor authentication is not pending confirmation.'], 400);
        }

        $otpauthUrl = Google2FA::getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(
                    192, // size
                    0,   // margin
                    null, // SvgTarget (not needed for string output)
                    null, // EyeFill (default)
                    Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72)) // Module fill (white modules, dark-gray background)
                ),
                new SvgImageBackEnd()
            )
        ))->writeString($otpauthUrl);

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }
}
