<?php

namespace App\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\text;

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
                            {--package-homepage= : The package homepage}
                            {--class-name= : The name of the Service Provider class}
                            {--author= : The author\'s name}
                            {--author-email= : The author\'s email}
                            {--author-homepage= : The author\'s homepage}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Initialize the package';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $data = $this->getData();

        $this->updateComposer(
            $data->get('meta'),
            $data->get('author'),
        );

        $this->updateReadme($data->get('meta'));

        $this->createServiceProviderClass($data->get('meta'));

        $this->updateTests($data->get('meta'));

        return self::SUCCESS;
    }

    protected function getData(): Collection
    {
        $package     = $this->option('package')      ?? text('Package name', 'package-name' , required: true);
        $vendor      = $this->option('vendor')       ?? text('Package vendor', 'vendor');
        $description = $this->option('description')  ?? text('Package description', 'Write a short description of two lines at max', required: true);
        $className   = $this->option('class-name')   ?? text('Service Provider class name', 'PackageServiceProvider');
        $author      = $this->option('author')       ?? text('Author\'s name', required: true);
        $authorEmail = $this->option('author-email') ?? text(
            'Author\'s email',
            placeholder: 'john@doe.com',
            required: true,
            validate: function (string $value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return null;
                }

                return 'The email should be a valid email';
            },
        );

        return collect([
            'meta' => [
                'package' => $this->slugify($package),
                'vendor' => $this->slugify($vendor ?: str($author)->before(' ')),
                'class-name' => $className ?: $this->cleanName($package),
                'description' => $description,
            ],
            'author' => [
                'name' => $author,
                'email' => $authorEmail,
            ],
        ]);
    }

    protected function updateComposer(array $meta, array $author): void
    {
        $file = $this->getBasePath('/composer.json');

        $content = str(File::get($file))
            ->replace(
                [
                    '<vendor>',
                    '<package>',
                    '<description>',
                    '<namespace>',
                    '<class_name>'
                ],
                [
                    $meta['vendor'],
                    $meta['package'],
                    $meta['description'],
                    collect([$meta['vendor'], $meta['package']])
                        ->map($this->cleanName(...))
                        ->join('\\\\'),
                    $meta['class-name'],
                ]
            );

        $content = $content
            ->replace(
                [
                    '<author>',
                    '<author_email>'
                ],
                [
                    $author['name'],
                    $author['email']
                ]
            );

        File::replace($file, $content);
    }

    protected function createServiceProviderClass(array $meta): void
    {
        $file = $this->getBasePath('src/PackageServiceProvider.php.stub');

        $content = str(File::get($file))
            ->replace(
                [
                    '<namespace>',
                    '<class_name>',
                    '<package>'
                ],
                [
                    collect([$meta['vendor'], $meta['package']])
                        ->map($this->cleanName(...))
                        ->join('\\'),
                    $meta['class-name'],
                    $meta['package'],
                ]
            );

        File::delete($file);

        File::append($this->getBasePath('src/'.$meta['class-name'].'.php'), $content);
    }

    protected function updateReadme(array $meta): void
    {
        $file = $this->getBasePath('/README.md');

        $content = str(File::get($file))
            ->replace(
                [
                    '<package>',
                    '{{package-title}}',
                    '{{package}}',
                    '{{description}}'
                ],
                [
                    $meta['package'],
                    str($meta['package'])->title()->replace('-', ' '),
                    $meta['package'],
                    $meta['description']
                ]
            );

        File::replace($file, $content);
    }

    protected function updateTests(array $meta): void
    {
        $testCaseFile = $this->getBasePath('/tests/TestCase.php');
        $pestFile = $this->getBasePath('/tests/Pest.php');

        $package = $this->cleanName($meta['package']);

        $testCaseContent = str(File::get($testCaseFile))->replace('<package>', $package);
        $pestContent = str(File::get($pestFile))->replace('<package>', $package);

        File::replace($testCaseFile, $testCaseContent);
        File::replace($pestFile, $pestContent);
    }

    protected function cleanName(string $name): string
    {
        return str($name)
            ->slug(' ')
            ->studly();
    }

    protected function slugify(string $identifier): string
    {
        return str($identifier)->slug();
    }

    protected function getBasePath(string ...$path): string
    {
        $path = array_filter(
            array_map(fn(string $p) => trim($p, DIRECTORY_SEPARATOR), $path)
        );

        return join(DIRECTORY_SEPARATOR, [getcwd(), ...$path]);
    }
}
