<?php

namespace Modules\MetaAdsImageGenerator\Facades;

use Illuminate\Support\Facades\Facade;

class MetaAdsImageGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'meta-ads-image-generator';
    }
}