<?php

namespace App;

/**
 * The phones the app is made for. Each one gets the app from its own store.
 */
enum AppPlatform: string
{
    case Android = 'android';
    case Ios = 'ios';

    public function label(): string
    {
        return match ($this) {
            self::Android => 'Android',
            self::Ios => 'iPhone',
        };
    }

    /**
     * The page of the app in the store, where the person taps Update.
     */
    public function storeUrl(): string
    {
        return config("mobile.store_urls.{$this->value}");
    }
}
