<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * The sign-in page of the admin website. The first field takes any text, so an admin can sign in with a
 * short username as well as an email.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email or username')
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus();
    }
}
