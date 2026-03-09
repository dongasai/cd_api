<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Profile extends BaseEditProfile
{
    public function form(Schema $form): Schema
    {
        $availableLocales = collect(config('locale.available', []))
            ->map(fn ($item) => $item['flag'].' '.$item['label'])
            ->toArray();

        return $form
            ->schema([
                Section::make('个人信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('姓名')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('邮箱')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email', ignoreRecord: true)
                            ->disabled(),

                        Select::make('locale')
                            ->label('语言')
                            ->options($availableLocales)
                            ->required(),

                        Select::make('currency')
                            ->label('货币')
                            ->options(User::SUPPORTED_CURRENCIES)
                            ->required()
                            ->default('USD'),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        session([
            'locale' => $data['locale'],
            'currency' => $data['currency'] ?? 'USD',
        ]);
        app()->setLocale($data['locale']);

        return $data;
    }
}
