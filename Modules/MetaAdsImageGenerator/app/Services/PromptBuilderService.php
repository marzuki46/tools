<?php

namespace Modules\MetaAdsImageGenerator\Services;

class PromptBuilderService
{
    public function build(array $inputForm, ?string $template = null): string
    {
        $product = $inputForm['product_name'] ?? 'Product';
        $vibe = $inputForm['vibe'] ?? 'professional';
        $notes = $inputForm['notes'] ?? '';

        if ($template) {
            $prompt = str_replace(
                ['{product}', '{vibe}', '{notes}'],
                [$product, $vibe, $notes],
                $template
            );
        } else {
            $prompt = "A high-quality, professional commercial photograph of {$product}. ";
            $prompt .= "The visual style and vibe should be {$vibe}. ";
            if ($notes) {
                $prompt .= "Additional details: {$notes}. ";
            }
            $prompt .= "Ensure there is enough negative space around the main subject for text overlay in various aspect ratios (square, vertical, horizontal). Do not include any text in the image.";
        }

        return $prompt;
    }
}