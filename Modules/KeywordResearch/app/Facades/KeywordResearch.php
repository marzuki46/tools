<?php

namespace Modules\KeywordResearch\Facades;

use Illuminate\Support\Facades\Facade;

class KeywordResearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'keyword-research';
    }
}
