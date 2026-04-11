<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Menimpa form bawaan Filament
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    /**
     * Membuat input login fleksibel (Bisa Email ATAU Username)
     */
    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login') // Kita beri nama 'login' agar netral
            ->label('Username / Email')
            ->placeholder('Masukkan NISN, NIP, atau Email Anda')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * Mesin Deteksi Otomatis: Email atau Username?
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        // Jika teks yang diketik mengandung format email (@), gunakan kolom 'email'
        // Jika tidak, gunakan kolom 'username'
        $login_type = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $login_type => $data['login'],
            'password' => $data['password'],
        ];
    }

    /**
     * Memperbaiki bug notifikasi error yang hilang
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}