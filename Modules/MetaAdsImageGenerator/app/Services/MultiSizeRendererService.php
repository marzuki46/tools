<?php

namespace Modules\MetaAdsImageGenerator\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class MultiSizeRendererService
{
    private array $sizeToPlacement = [
        '1:1' => 'feed_square',
        '9:16' => 'story',
        '1.91:1' => 'feed_landscape',
    ];

    private array $placementConfig = [
        'feed_square' => [
            'width' => 1080,
            'height' => 1080,
            'product_zone' => ['x' => 0, 'y' => 0, 'w' => 1080, 'h' => 700],
            'model_zone' => ['x' => 700, 'y' => 50, 'w' => 320, 'h' => 320],
            'text_bg' => ['x' => 0, 'y' => 700, 'w' => 1080, 'h' => 380],
            'headline' => ['x' => 540, 'y' => 800],
            'sub_headline' => ['x' => 540, 'y' => 870],
            'cta' => ['x' => 540, 'y' => 960],
            'logo_pos' => ['x' => 40, 'y' => 40],
        ],
        'story' => [
            'width' => 1080,
            'height' => 1920,
            'product_zone' => ['x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1100],
            'model_zone' => ['x' => 340, 'y' => 900, 'w' => 400, 'h' => 400],
            'text_bg' => ['x' => 0, 'y' => 1300, 'w' => 1080, 'h' => 620],
            'headline' => ['x' => 540, 'y' => 1450],
            'sub_headline' => ['x' => 540, 'y' => 1530],
            'cta' => ['x' => 540, 'y' => 1680],
            'logo_pos' => ['x' => 440, 'y' => 60],
        ],
        'feed_landscape' => [
            'width' => 1200,
            'height' => 628,
            'product_zone' => ['x' => 0, 'y' => 0, 'w' => 700, 'h' => 628],
            'model_zone' => ['x' => 500, 'y' => 50, 'w' => 200, 'h' => 200],
            'text_bg' => ['x' => 700, 'y' => 0, 'w' => 500, 'h' => 628],
            'headline' => ['x' => 950, 'y' => 180],
            'sub_headline' => ['x' => 950, 'y' => 260],
            'cta' => ['x' => 950, 'y' => 400],
            'logo_pos' => ['x' => 720, 'y' => 40],
        ],
    ];

    private int $maxLogoWidth = 180;

    public function renderFromAi(
        string $baseImagePath,
        array $brandKit,
        array $inputForm,
        string $generationId,
        string $disk = 'public'
    ): array {
        $exports = [];

        $fontPath = Setting::getValue('ai.font_path', config('meta-ads-image-generator.font_path'));
        $hasFont = $fontPath && file_exists($fontPath);

        $selectedSizes = $inputForm['sizes'] ?? array_keys($this->sizeToPlacement);
        $placementsToRender = [];
        foreach ($selectedSizes as $size) {
            $placementKey = $this->sizeToPlacement[$size] ?? null;
            if ($placementKey && isset($this->placementConfig[$placementKey])) {
                $placementsToRender[] = $placementKey;
            }
        }
        $placementsToRender = array_unique($placementsToRender);

        $baseImage = $this->createImageFromFile($baseImagePath);
        if (!$baseImage) {
            Log::error('Could not read AI image', ['path' => $baseImagePath]);
            return [];
        }

        $baseOrigW = imagesx($baseImage);
        $baseOrigH = imagesy($baseImage);

        $primaryColor = $brandKit['primary_color'] ?? '#1a1a2e';
        $secondaryColor = $brandKit['secondary_color'] ?? '#e94560';

        foreach ($placementsToRender as $placement) {
            $config = $this->placementConfig[$placement];
            $targetW = $config['width'];
            $targetH = $config['height'];

            try {
                $canvas = imagecreatetruecolor($targetW, $targetH);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);

                $ratio = max($targetW / $baseOrigW, $targetH / $baseOrigH);
                $scaledW = (int) ($baseOrigW * $ratio);
                $scaledH = (int) ($baseOrigH * $ratio);

                $temp = imagecreatetruecolor($scaledW, $scaledH);
                imagealphablending($temp, false);
                imagesavealpha($temp, true);
                imagecopyresampled($temp, $baseImage, 0, 0, 0, 0, $scaledW, $scaledH, $baseOrigW, $baseOrigH);

                $cropX = (int) (($scaledW - $targetW) / 2);
                $cropY = (int) (($scaledH - $targetH) / 2);
                imagecopy($canvas, $temp, 0, 0, $cropX, $cropY, $targetW, $targetH);
                imagedestroy($temp);

                $textBgY = (int) ($targetH * 0.72);
                $textBgH = $targetH - $textBgY;

                $hex = ltrim($primaryColor, '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $overlay = imagecolorallocatealpha($canvas, $r, $g, $b, 40);
                imagefilledrectangle($canvas, 0, $textBgY, $targetW, $targetH, $overlay);

                $headline = $inputForm['headline'] ?? '';
                if (!empty($headline) && $hasFont) {
                    $color = $this->parseColor($canvas, '#FFFFFF');
                    $this->drawTextCentered($canvas, $headline, $fontPath, 52, $color, (int)($targetW / 2), $textBgY + 80);
                }

                $subHeadline = $inputForm['sub_headline'] ?? '';
                if (!empty($subHeadline) && $hasFont) {
                    $color = $this->parseColor($canvas, '#DDDDDD');
                    $this->drawTextCentered($canvas, $subHeadline, $fontPath, 30, $color, (int)($targetW / 2), $textBgY + 160);
                }

                $cta = $inputForm['cta'] ?? '';
                if (!empty($cta) && $hasFont) {
                    $this->drawCtaButton($canvas, $cta, $fontPath, $secondaryColor, (int)($targetW / 2), $textBgY + 260);
                }

                if (!empty($brandKit['logo_path'])) {
                    $this->drawLogo($canvas, $brandKit['logo_path'], ['x' => 40, 'y' => 40], $disk);
                }

                $filename = sprintf(
                    'meta-ads/%s/%s_%dx%d.png',
                    $generationId,
                    $placement,
                    $targetW,
                    $targetH
                );

                $fullPath = Storage::disk($disk)->path($filename);
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                imagepng($canvas, $fullPath);
                imagedestroy($canvas);

                $exports[] = [
                    'placement'       => $placement,
                    'width'           => $targetW,
                    'height'          => $targetH,
                    'final_image_path' => $filename,
                    'overlay_config'  => [
                        'headline'     => $headline,
                        'sub_headline' => $subHeadline,
                        'cta'          => $cta,
                        'font_used'    => $hasFont ? $fontPath : null,
                        'colors'       => [
                            'primary'   => $primaryColor,
                            'secondary' => $secondaryColor,
                        ],
                    ],
                ];
            } catch (Exception $e) {
                Log::error("Failed to render {$placement}", [
                    'error'         => $e->getMessage(),
                    'generation_id' => $generationId,
                ]);
            }
        }

        imagedestroy($baseImage);

        return $exports;
    }

    public function render(
        string $productImagePath,
        ?string $modelImagePath,
        array $brandKit,
        array $inputForm,
        string $generationId,
        string $disk = 'public'
    ): array {
        $exports = [];

        $fontPath = Setting::getValue('ai.font_path', config('meta-ads-image-generator.font_path'));
        $hasFont = $fontPath && file_exists($fontPath);

        $selectedSizes = $inputForm['sizes'] ?? array_keys($this->sizeToPlacement);
        $placementsToRender = [];
        foreach ($selectedSizes as $size) {
            $placementKey = $this->sizeToPlacement[$size] ?? null;
            if ($placementKey && isset($this->placementConfig[$placementKey])) {
                $placementsToRender[] = $placementKey;
            }
        }
        $placementsToRender = array_unique($placementsToRender);

        $productImage = $this->createImageFromFile($productImagePath);
        if (!$productImage) {
            Log::error('Could not read product image', ['path' => $productImagePath]);
            return [];
        }

        $modelImage = null;
        if ($modelImagePath && file_exists($modelImagePath)) {
            $modelImage = $this->createImageFromFile($modelImagePath);
        }

        $primaryColor = $brandKit['primary_color'] ?? '#1a1a2e';
        $secondaryColor = $brandKit['secondary_color'] ?? '#e94560';
        $accentColor = $brandKit['accent_color'] ?? '#0f3460';

        foreach ($placementsToRender as $placement) {
            $config = $this->placementConfig[$placement];
            $targetW = $config['width'];
            $targetH = $config['height'];

            try {
                $canvas = imagecreatetruecolor($targetW, $targetH);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);

                $this->drawProductImage($canvas, $productImage, $config['product_zone'], $targetW, $targetH);

                if ($modelImage) {
                    $this->drawModelImage($canvas, $modelImage, $config['model_zone']);
                }

                $this->drawTextBackground($canvas, $config['text_bg'], $primaryColor);

                $headline = $inputForm['headline'] ?? '';
                $subHeadline = $inputForm['sub_headline'] ?? '';
                $cta = $inputForm['cta'] ?? '';

                if (!empty($headline) && $hasFont) {
                    $zone = $config['headline'];
                    $color = $this->parseColor($canvas, '#FFFFFF');
                    $this->drawTextCentered($canvas, $headline, $fontPath, 48, $color, $zone['x'], $zone['y']);
                }

                if (!empty($subHeadline) && $hasFont) {
                    $zone = $config['sub_headline'];
                    $color = $this->parseColor($canvas, '#CCCCCC');
                    $this->drawTextCentered($canvas, $subHeadline, $fontPath, 28, $color, $zone['x'], $zone['y']);
                }

                if (!empty($cta) && $hasFont) {
                    $zone = $config['cta'];
                    $this->drawCtaButton($canvas, $cta, $fontPath, $secondaryColor, $zone['x'], $zone['y']);
                }

                if (!empty($brandKit['logo_path'])) {
                    $this->drawLogo($canvas, $brandKit['logo_path'], $config['logo_pos'], $disk);
                }

                $filename = sprintf(
                    'meta-ads/%s/%s_%dx%d.png',
                    $generationId,
                    $placement,
                    $targetW,
                    $targetH
                );

                $fullPath = Storage::disk($disk)->path($filename);
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                imagepng($canvas, $fullPath);
                imagedestroy($canvas);

                $exports[] = [
                    'placement'       => $placement,
                    'width'           => $targetW,
                    'height'          => $targetH,
                    'final_image_path' => $filename,
                    'overlay_config'  => [
                        'headline'    => $headline,
                        'sub_headline'=> $subHeadline,
                        'cta'         => $cta,
                        'font_used'   => $hasFont ? $fontPath : null,
                        'colors'      => [
                            'primary'   => $primaryColor,
                            'secondary' => $secondaryColor,
                            'accent'    => $accentColor,
                        ],
                    ],
                ];
            } catch (Exception $e) {
                Log::error("Failed to render {$placement}", [
                    'error'         => $e->getMessage(),
                    'generation_id' => $generationId,
                ]);
            }
        }

        imagedestroy($productImage);
        if ($modelImage) {
            imagedestroy($modelImage);
        }

        return $exports;
    }

    private function drawProductImage(\GdImage $canvas, \GdImage $product, array $zone, int $targetW, int $targetH): void
    {
        $prodW = imagesx($product);
        $prodH = imagesy($product);

        $ratio = max($zone['w'] / $prodW, $zone['h'] / $prodH);
        $scaledW = (int) ($prodW * $ratio);
        $scaledH = (int) ($prodH * $ratio);

        $temp = imagecreatetruecolor($scaledW, $scaledH);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        imagecopyresampled($temp, $product, 0, 0, 0, 0, $scaledW, $scaledH, $prodW, $prodH);

        $cropX = (int) (($scaledW - $zone['w']) / 2);
        $cropY = (int) (($scaledH - $zone['h']) / 2);

        imagecopy($canvas, $temp, $zone['x'], $zone['y'], $cropX, $cropY, $zone['w'], $zone['h']);
        imagedestroy($temp);
    }

    private function drawModelImage(\GdImage $canvas, \GdImage $model, array $zone): void
    {
        $modelW = imagesx($model);
        $modelH = imagesy($model);

        $ratio = min($zone['w'] / $modelW, $zone['h'] / $modelH);
        $newW = (int) ($modelW * $ratio);
        $newH = (int) ($modelH * $ratio);

        $temp = imagecreatetruecolor($newW, $newH);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        imagecopyresampled($temp, $model, 0, 0, 0, 0, $newW, $newH, $modelW, $modelH);

        $x = $zone['x'] + (int) (($zone['w'] - $newW) / 2);
        $y = $zone['y'] + (int) (($zone['h'] - $newH) / 2);

        imagecopy($canvas, $temp, $x, $y, 0, 0, $newW, $newH);
        imagedestroy($temp);
    }

    private function drawTextBackground(\GdImage $canvas, array $zone, string $primaryColor): void
    {
        $hex = ltrim($primaryColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $bg = imagecolorallocatealpha($canvas, $r, $g, $b, 30);
        imagefilledrectangle($canvas, $zone['x'], $zone['y'], $zone['x'] + $zone['w'], $zone['y'] + $zone['h'], $bg);
    }

    private function drawCtaButton(\GdImage $canvas, string $text, string $fontPath, string $bgColor, int $cx, int $cy): void
    {
        $boxes = imagettfbbox(30, 0, $fontPath, $text);
        $textW = abs($boxes[4] - $boxes[0]);
        $textH = abs($boxes[5] - $boxes[1]);

        $padX = 40;
        $padY = 16;
        $btnW = $textW + ($padX * 2);
        $btnH = $textH + ($padY * 2);
        $btnX = (int) ($cx - $btnW / 2);
        $btnY = (int) ($cy - $btnH / 2);

        $hex = ltrim($bgColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $color = imagecolorallocate($canvas, $r, $g, $b);
        $this->imagefilledroundrect($canvas, $btnX, $btnY, $btnX + $btnW, $btnY + $btnH, 12, 12, $color);

        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        $textX = (int) ($cx - $textW / 2);
        $textY = (int) ($cy + $textH / 4);
        imagettftext($canvas, 30, 0, $textX, $textY, $textColor, $fontPath, $text);
    }

    private function drawLogo(\GdImage $canvas, string $logoPath, array $pos, string $disk): void
    {
        try {
            $logoFullPath = Storage::disk($disk)->path($logoPath);
            if (!file_exists($logoFullPath)) return;

            $logo = $this->createImageFromFile($logoFullPath);
            if (!$logo) return;

            $logoW = imagesx($logo);
            $logoH = imagesy($logo);

            if ($logoW > $this->maxLogoWidth) {
                $scale = $this->maxLogoWidth / $logoW;
                $newW = $this->maxLogoWidth;
                $newH = (int) ($logoH * $scale);
                $scaled = imagecreatetruecolor($newW, $newH);
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $newW, $newH, $logoW, $logoH);
                imagedestroy($logo);
                $logo = $scaled;
                $logoW = $newW;
                $logoH = $newH;
            }

            imagealphablending($canvas, true);
            imagesavealpha($canvas, true);
            imagecopy($canvas, $logo, $pos['x'], $pos['y'], 0, 0, $logoW, $logoH);
            imagedestroy($logo);
        } catch (Exception $e) {
            Log::warning('Logo overlay failed', ['error' => $e->getMessage()]);
        }
    }

    private function imagefilledroundrect(\GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $rX, int $rY, mixed $color): void
    {
        imagefilledrectangle($canvas, $x1 + $rX, $y1, $x2 - $rX, $y2, $color);
        imagefilledrectangle($canvas, $x1, $y1 + $rY, $x2, $y2 - $rY, $color);
        imagefilledellipse($canvas, $x1 + $rX, $y1 + $rY, $rX * 2, $rY * 2, $color);
        imagefilledellipse($canvas, $x2 - $rX, $y1 + $rY, $rX * 2, $rY * 2, $color);
        imagefilledellipse($canvas, $x1 + $rX, $y2 - $rY, $rX * 2, $rY * 2, $color);
        imagefilledellipse($canvas, $x2 - $rX, $y2 - $rY, $rX * 2, $rY * 2, $color);
    }

    private function createImageFromFile(string $path): ?\GdImage
    {
        if (!file_exists($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        return match ($info['mime']) {
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            default => @imagecreatefromjpeg($path),
        };
    }

    private function parseColor(\GdImage $canvas, string $hex): mixed
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return imagecolorallocate($canvas, $r, $g, $b);
    }

    private function drawTextCentered(\GdImage $canvas, string $text, string $fontPath, int $size, mixed $color, int $cx, int $cy): void
    {
        $boxes = imagettfbbox($size, 0, $fontPath, $text);
        $textW = abs($boxes[4] - $boxes[0]);
        $x = (int) ($cx - $textW / 2);
        $y = (int) ($cy + abs($boxes[5] - $boxes[1]) / 4);
        imagealphablending($canvas, true);
        imagettftext($canvas, $size, 0, $x, $y, $color, $fontPath, $text);
    }
}
