<?php

namespace Modules\MetaAdsImageGenerator\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class MultiSizeRendererService
{
    private ImageManager $manager;

    /**
     * Placement configurations with dimensions and safe zones for text/logo.
     *
     * Safe zone positions use Intervention Image v3 naming:
     * https://image.intervention.io/v3/modifying/placing
     */
    private array $placementConfig = [
        'feed_square' => [
            'width' => 1080,
            'height' => 1080,
            'safe_zones' => [
                'logo'     => ['position' => 'top-center', 'offset_x' => 0, 'offset_y' => 60],
                'headline' => ['position' => 'bottom-center', 'offset_x' => 0, 'offset_y' => -170],
                'cta'      => ['position' => 'bottom-center', 'offset_x' => 0, 'offset_y' => -50],
            ],
        ],
        'story' => [
            'width' => 1080,
            'height' => 1920,
            'safe_zones' => [
                'logo'     => ['position' => 'top-center', 'offset_x' => 0, 'offset_y' => 80],
                'headline' => ['position' => 'bottom-center', 'offset_x' => 0, 'offset_y' => -320],
                'cta'      => ['position' => 'bottom-center', 'offset_x' => 0, 'offset_y' => -170],
            ],
        ],
        'feed_landscape' => [
            'width' => 1200,
            'height' => 628,
            'safe_zones' => [
                'logo'     => ['position' => 'top-center', 'offset_x' => 0, 'offset_y' => 40],
            ],
            'text_zones' => [
                'headline' => ['x' => 600, 'y' => 450],
                'cta'      => ['x' => 600, 'y' => 540],
            ],
        ],
    ];

    /**
     * Max logo width in pixels (scaled down proportionally from original).
     */
    private int $maxLogoWidth = 200;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Render a base image into multiple placement sizes with overlays.
     *
     * @param  string  $baseImagePath  Absolute path to the downloaded base image.
     * @param  array   $brandKit       Brand kit data (logo_path, primary_color, secondary_color, font_family).
     * @param  array   $inputForm      User input (headline, cta, product_name, vibe, notes).
     * @param  string  $generationId   ID used for organizing stored files.
     * @param  string  $disk           Storage disk to save exports.
     * @return array                   Array of export data arrays.
     */
    public function render(
        string $baseImagePath,
        array $brandKit,
        array $inputForm,
        string $generationId,
        string $disk = 'public'
    ): array {
        $exports = [];

        // Resolve font path once from config (optional — text overlays skipped if absent).
        $fontPath = config('meta-ads-image-generator.font_path');
        $hasFont = $fontPath && file_exists($fontPath);

        foreach ($this->placementConfig as $placement => $config) {
            try {
                $image = $this->manager->read($baseImagePath);

                // 1. Smart resize + crop to exact dimensions
                $image->cover($config['width'], $config['height']);

                // 2. Overlay brand logo (scaled proportionally)
                if (!empty($brandKit['logo_path'])) {
                    try {
                        $logoFullPath = Storage::disk($disk)->path($brandKit['logo_path']);
                        if (file_exists($logoFullPath)) {
                            $logo = $this->manager->read($logoFullPath);
                            $logo->scale(width: $this->maxLogoWidth);
                            $zone = $config['safe_zones']['logo'];
                            $image->place($logo, $zone['position'], $zone['offset_x'], $zone['offset_y']);
                        }
                    } catch (Exception $e) {
                        Log::warning('Logo overlay failed for ' . $placement, [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // 3. Overlay headline text (only if a .ttf font is configured)
                $headline = $inputForm['headline'] ?? '';
                if (!empty($headline) && $hasFont) {
                    $zone = $config['text_zones']['headline'];
                    $textColor = $brandKit['primary_color'] ?? '#FFFFFF';
                    $image->text($headline, $zone['x'], $zone['y'], function ($font) use ($fontPath, $textColor) {
                        $font->file($fontPath);
                        $font->size(52);
                        $font->color($textColor);
                        $font->align('center');
                        $font->valign('middle');
                    });
                } elseif (!empty($headline) && !$hasFont) {
                    Log::warning('Skipping text overlay — no font file configured. Set META_ADS_FONT_PATH in .env');
                }

                // 4. Overlay CTA text (only if a .ttf font is configured)
                $cta = $inputForm['cta'] ?? '';
                if (!empty($cta) && $hasFont) {
                    $zone = $config['text_zones']['cta'];
                    $ctaColor = $brandKit['secondary_color'] ?? '#000000';
                    $image->text($cta, $zone['x'], $zone['y'], function ($font) use ($fontPath, $ctaColor) {
                        $font->file($fontPath);
                        $font->size(36);
                        $font->color($ctaColor);
                        $font->align('center');
                        $font->valign('middle');
                    });
                }

                // 5. Save final image to storage
                $filename = sprintf(
                    'meta-ads/%s/%s_%dx%d.png',
                    $generationId,
                    $placement,
                    $config['width'],
                    $config['height']
                );

                $encoded = $image->encode('png');
                Storage::disk($disk)->put($filename, (string) $encoded);

                $exports[] = [
                    'placement'       => $placement,
                    'width'           => $config['width'],
                    'height'          => $config['height'],
                    'final_image_path' => $filename,
                    'overlay_config'  => [
                        'headline'          => $headline,
                        'cta'               => $cta,
                        'headline_position' => $config['text_zones']['headline'],
                        'cta_position'      => $config['text_zones']['cta'],
                        'logo_position'     => $config['safe_zones']['logo'],
                        'max_logo_width'    => $this->maxLogoWidth,
                        'font_used'         => $hasFont ? $fontPath : null,
                        'colors'            => [
                            'primary'   => $brandKit['primary_color'] ?? '#FFFFFF',
                            'secondary' => $brandKit['secondary_color'] ?? '#000000',
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

        return $exports;
    }
}
