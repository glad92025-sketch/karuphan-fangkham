<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;

class QrCodeService
{
    public function svg(string $payload, int $size = 180, int $margin = 1, bool $pureBlack = false): string
    {
        $colorKey = $pureBlack ? 'blk' : 'navy';
        $cacheKey = 'qr_svg_v3_' . md5("{$payload}|{$size}|{$margin}|{$colorKey}");

        return Cache::rememberForever($cacheKey, function () use ($payload, $size, $margin, $pureBlack) {
            $fg = $pureBlack ? new Rgb(0, 0, 0) : new Rgb(30, 58, 138);
            $fill = Fill::uniformColor(new Rgb(255, 255, 255), $fg);
            $renderer = new ImageRenderer(
                new RendererStyle($size, $margin, null, null, $fill),
                new SvgImageBackEnd()
            );

            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::M());

            return trim(preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg) ?? $svg);
        });
    }
}
