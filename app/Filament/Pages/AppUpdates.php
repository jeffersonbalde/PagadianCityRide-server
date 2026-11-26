<?php

namespace App\Filament\Pages;

use App\AppPlatform;
use App\Models\AppVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Where the admins make an update of the app required: a phone with an older version
 * than the one written here shows only "Update required".
 *
 * @property-read Schema $form
 */
class AppUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'App updates';

    protected static ?string $title = 'App updates';

    protected static ?int $navigationSort = 6;

    /**
     * The oldest version that may be used, for each platform.
     *
     * @var array<string, string|null>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(AppVersion::query()->toBase()->pluck('minimum_version', 'platform')->all());
    }

    public function getSubheading(): ?string
    {
        return 'Make people update the app before they can use it.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (AppPlatform $platform): TextInput => TextInput::make($platform->value)
                    ->label("Oldest {$platform->label()} version allowed")
                    ->placeholder('For example 1.0.2')
                    ->helperText('Write a version only after it can be downloaded from the store. Leave empty to require nothing.')
                    ->regex(AppVersion::PATTERN)
                    ->validationMessages(['regex' => 'Write the version as three numbers, like 1.0.2.']),
                AppPlatform::cases(),
            ))
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([Section::make([EmbeddedSchema::make('form')])])
                ->id('form')
                ->livewireSubmitHandler('review')
                ->footer([
                    Actions::make([
                        Action::make('review')->label('Save')->submit('review'),
                    ]),
                ]),
        ]);
    }

    /**
     * Check what was written, then ask once more before anyone can be locked out.
     */
    public function review(): void
    {
        $this->form->getState();

        $this->mountAction('save');
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->requiresConfirmation()
            ->modalHeading('Require this update?')
            ->modalDescription('Everyone with an older version is stopped until they update. First check in the store that the new version can be downloaded. If it cannot, they are locked out.')
            ->modalSubmitActionLabel('Yes, save')
            ->action(function (): void {
                $data = $this->form->getState();

                foreach (AppPlatform::cases() as $platform) {
                    AppVersion::query()->updateOrCreate(
                        ['platform' => $platform],
                        ['minimum_version' => $data[$platform->value] ?: null],
                    );
                }

                Notification::make()->title('Saved')->success()->send();
            });
    }
}
