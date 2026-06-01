<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->scanDir = base_path('translation-scan');
    $this->langPath = base_path('lang/en.json');

    File::deleteDirectory($this->scanDir);
    File::deleteDirectory(base_path('lang'));

    File::makeDirectory($this->scanDir, 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->scanDir);
    File::deleteDirectory(base_path('lang'));
});

it('extracts translation keys and writes a json file', function (): void {
    File::put($this->scanDir.'/Example.php', <<<'PHP'
        <?php

        echo __('Hello world');
        echo trans('Welcome back');
        echo trans_choice('You have :count messages', 1);
        PHP);

    $exitCode = Artisan::call('translations:extract', [
        'directory' => $this->scanDir,
        'lang' => 'en',
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::exists($this->langPath))->toBeTrue()
        ->and(json_decode(File::get($this->langPath), true))->toBe([
            'Hello world' => '',
            'Welcome back' => '',
            'You have :count messages' => '',
        ])
        ->and(Artisan::output())->toContain('File '.base_path('lang/en.json').' successfully updated!');
});

it('merges new keys without overwriting existing translations', function (): void {
    File::makeDirectory(base_path('lang'), 0755, true);
    File::put($this->langPath, json_encode([
        'Existing key' => 'Existing translation',
    ], JSON_THROW_ON_ERROR));

    File::put($this->scanDir.'/Example.php', <<<'PHP'
        <?php

        echo __('Existing key');
        echo __('New key');
        PHP);

    Artisan::call('translations:extract', [
        'directory' => $this->scanDir,
        'lang' => 'en',
    ]);

    expect(json_decode(File::get($this->langPath), true))->toBe([
        'Existing key' => 'Existing translation',
        'New key' => '',
    ]);
});

it('reports when no translation strings are found', function (): void {
    File::put($this->scanDir.'/Example.php', '<?php echo "plain text";');

    $exitCode = Artisan::call('translations:extract', [
        'directory' => $this->scanDir,
        'lang' => 'en',
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No translation strings found.')
        ->and(File::exists($this->langPath))->toBeFalse();
});

it('extracts blade and attribute translation patterns', function (): void {
    File::put($this->scanDir.'/form.blade.php', <<<'BLADE'
        @lang('Dashboard')

        #[Title('Users list')]
        #[Validate(as: 'Email address')]
        BLADE);

    Artisan::call('translations:extract', [
        'directory' => $this->scanDir,
        'lang' => 'en',
    ]);

    expect(json_decode(File::get($this->langPath), true))->toBe([
        'Dashboard' => '',
        'Email address' => '',
        'Users list' => '',
    ]);
});
