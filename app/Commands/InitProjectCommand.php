<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;
use function Termwind\render;

class InitProjectCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'init
                            {--package= : The package name}
                            {--vendor= : The package vendor name}
                            {--description= : The package description}
                            {--class-name= : The name of the Service Provider class}
                            {--author= : The author\'s name}
                            {--author-email= : The author\'s email}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Initialize the Package configuration';

    protected string $packageName;

    protected string $packageVendorName;

    protected string $packageDescription;

    protected string $packageClassName;

    protected string $packageAuthor;

    protected string $packageAuthorEmail;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $this->initData();

        \Laravel\Prompts\info('Initializing Package');

        $this->components->task('Configure composer', $this->updateComposer(...));

        $this->components->task('Update README.md', $this->updateReadme(...));

        $this->components->task('Create Service Provider', $this->createServiceProviderClass(...));

        $this->components->task('Updating Tests', $this->updateTests(...));

        $this->components->task('Updating License Copyrights', $this->updateLicense(...));

        render(<<<'HTML'
        <p>
            <span class="bg-green px-1 mr-2">DONE</span>
            <span>Package Initialized</span>
        </p>
        HTML);

        return self::SUCCESS;
    }

    /**
     * Initialize the class properties from the options or by user's input
     */
    protected function initData(): void
    {
        $package = $this->getValue('package', 'Package name', 'package-name', required: true);
        $vendor = $this->getValue('vendor', 'Package vendor', 'vendor');
        $description = $this->getValue('description', 'Package description', 'Write a short description of two lines at max', required: true);
        $className = $this->getValue('class-name', 'Service Provider class name', 'PackageServiceProvider');
        $author = $this->getValue('author', 'Author\'s name', required: true);
        $authorEmail = $this->getValue('author-email',
            'Author\'s email',
            'john@doe.com',
            true,
            validate: function (string $value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return null;
                }

                return 'The email should be a valid email';
            },
        );

        $this->packageName = $this->slugify($package);
        $this->packageVendorName = $this->slugify($vendor ?? $author);
        $this->packageDescription = $description;
        $this->packageClassName = Str::studly($this->cleanName($className ?? $this->packageName));
        $this->packageAuthor = $this->cleanName($author);
        $this->packageAuthorEmail = $authorEmail;
    }

    /**
     * Update the composer.json file
     */
    protected function updateComposer(): void
    {
        $this->replaceOnPackageFile('/composer.json');
    }

    /**
     * Create the ServiceProvider class for the package
     */
    protected function createServiceProviderClass(): void
    {
        $file = 'src/PackageServiceProvider.php.stub';

        $this->replaceOnPackageFile($file);

        $newName = $this->getPackageBasePath('src/'.$this->getPackageClassName().'.php');

        File::move($this->getPackageBasePath($file), $newName);
    }

    /**
     * Update the README.md file
     */
    protected function updateReadme(): void
    {
        $this->replaceOnPackageFile('/README.md');
    }

    /**
     * Update the Tests files
     */
    protected function updateTests(): void
    {
        $this->replaceOnPackageFile('/tests/TestCase.php');
        $this->replaceOnPackageFile('/tests/Pest.php');
    }

    /**
     * Update the LICENSE.md file
     */
    protected function updateLicense(): void
    {
        $this->replaceOnPackageFile('/LICENSE.md');
    }

    /**
     * Convert the given value to title (very work) with space
     */
    protected function cleanName(string $name): string
    {
        return str($name)->slug(' ')->title();
    }

    /**
     * Slugify the given identifier
     */
    protected function slugify(string $identifier): string
    {
        return str($identifier)->slug();
    }

    /**
     * Get the value from the option or by requesting the user's input
     */
    protected function getValue(string $option, string $label, string $placeholder = '', mixed $default = null, bool $required = false, \Closure $validate = null): mixed
    {
        $value = $this->option($option)
            ?? text($label, $placeholder, required: $required, validate: $validate)
            ?: $default;

        return $value;
    }

    /**
     * The class name used for the service provider
     */
    protected function getPackageClassName(): string
    {
        if (str($this->packageClassName)->contains('ServiceProvider')) {
            return $this->packageClassName;
        }

        return $this->packageClassName.'ServiceProvider';
    }

    /**
     * The package namespace
     *
     * The package namespace is form between the package vendor name,
     * and the package name
     *
     * @param bool $escape if the namespace should be escaped ("\\\\")
     */
    protected function getPackageNamespace(bool $escape = false): string
    {
        return collect([$this->packageVendorName, $this->packageName])
            ->map(Str::studly(...))
            ->join('\\'.($escape ? '\\' : ''));
    }

    /**
     * Replace the placeholder with the values
     */
    protected function replaceOnPackageFile(string $file): void
    {
        $file = $this->getPackageBasePath($file);

        $content = str(File::get($file))
            ->replace(
                [
                    '{{package}}',
                    '{{vendor}}',
                    '{{description}}',
                    '{{namespace}}',
                    '{{escape_namespace}}',
                    '{{class_name}}',
                    '{{author}}',
                    '{{author_email}}',
                    '{{title_package}}',
                    '{{year}}',
                    '{{copyright}}',
                ],
                [
                    $this->packageName,
                    $this->packageVendorName,
                    $this->packageDescription,
                    $this->getPackageNamespace(),
                    $this->getPackageNamespace(true),
                    $this->getPackageClassName(),
                    $this->packageAuthor,
                    $this->packageAuthorEmail,
                    $this->cleanName($this->packageName),
                    now()->year,
                    $this->packageAuthor,
                ]
            );

        File::replace($file, $content);
    }

    /**
     * The base path of the Package
     *
     * This method will join the given path(s) to the Package base path
     */
    protected function getPackageBasePath(string ...$path): string
    {
        $path = array_filter(
            array_map(fn (string $p) => trim($p, DIRECTORY_SEPARATOR), $path)
        );

        return implode(DIRECTORY_SEPARATOR, [getcwd(), ...$path]);
    }
}
