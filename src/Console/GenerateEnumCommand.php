<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class GenerateEnumCommand extends Command
{
    protected $signature = 'flag:generate
        {--class=App\\Features\\AppFeature : Fully-qualified enum class name}
        {--path= : Output path (defaults from the class name)}
        {--force : Overwrite an existing file}';

    protected $description = 'Generate a typed FeatureKey enum from the stored flag keys';

    public function handle(FeatureFlagManager $manager): int
    {
        /** @var string $fqcn */
        $fqcn = ltrim((string) $this->option('class'), '\\');
        $namespace = Str::beforeLast($fqcn, '\\');
        $shortName = Str::afterLast($fqcn, '\\');

        $path = (string) ($this->option('path') ?: base_path(str_replace('\\', '/', Str::replaceFirst('App\\', 'app/', $fqcn)).'.php'));

        if (file_exists($path) && ! (bool) $this->option('force')) {
            $this->error("File already exists: {$path} (use --force to overwrite).");

            return self::FAILURE;
        }

        $keys = $manager->distinctKeys();
        $cases = $this->buildCases($keys);

        $contents = $this->render($namespace, $shortName, $cases);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($path, $contents);

        $this->info("Generated {$fqcn} with ".count($cases)." case(s) at {$path}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string> case name => key
     */
    private function buildCases(array $keys): array
    {
        $cases = [];
        foreach ($keys as $key) {
            $name = Str::studly(str_replace(['-', '.', ' '], '_', $key));
            if ($name === '' || ! preg_match('/^[A-Za-z]/', $name)) {
                $name = 'Flag'.$name;
            }
            // Guarantee uniqueness on collision.
            $candidate = $name;
            $i = 2;
            while (isset($cases[$candidate])) {
                $candidate = $name.$i++;
            }
            $cases[$candidate] = $key;
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $cases
     */
    private function render(string $namespace, string $shortName, array $cases): string
    {
        $lines = '';
        foreach ($cases as $name => $key) {
            $lines .= "    case {$name} = '".addslashes($key)."';\n";
        }

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Chemaclass\\FeatureFlags\\Contracts\\FeatureKey;

        enum {$shortName}: string implements FeatureKey
        {
        {$lines}
            public function key(): string
            {
                return \$this->value;
            }
        }

        PHP;
    }
}
