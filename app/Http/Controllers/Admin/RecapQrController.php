<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\RecapLink;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The QR a printed shift recap carries.
 *
 * The receipt's QR is rendered with the slip because the server knows the whole
 * address up front. A recap's does not exist until the desk has picked a day
 * and a shift tab, so the encoding happens here instead, on request.
 *
 * The address is built from the page key and the two filters rather than taken
 * from the caller, so this cannot be used to put an arbitrary destination
 * behind the outlet's own domain.
 */
class RecapQrController extends Controller
{
    /** Matches the QR the receipt slip prints. */
    private const SIZE = 196;

    private const MARGIN = 2;

    public function __invoke(Request $request): Response
    {
        $page = $request->query('page');

        abort_unless(is_string($page) && RecapLink::isPage($page), 404);

        Gate::authorize($page === 'pos' ? 'admin.pos.read' : 'admin.finance.read');

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::SIZE, self::MARGIN),
            new SvgImageBackEnd,
        ));
        $url = RecapLink::url(
            $page,
            $request->query('date'),
            $request->query('shift'),
        );

        /*
         * Cached in the browser for the day: the same tab reprints the same
         * recap more than once, and the address only moves when a filter does.
         */
        return response($writer->writeString($url), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
