<?php declare(strict_types = 1);

(new class {

    /** @var string */
    private $base;

    /** @var list<string> */
    private $devDirs;

    /** @var bool */
    private $failed = false;

    public function __invoke(): void
    {
        echo "Checking package configuration\n";

        $base = getcwd();
        if ($base === false) {
            echo "\e[0;33m Could not get working directory \e[0m\n";
            exit(1);
        }
        $this->base = $base;

        $dirs = ['build', 'doc', 'test', 'tests'];
        $this->devDirs = array_filter(array_map(function (string $dir): ?string {
            return is_dir($this->base . '/' . $dir) ? $dir : null;
        }, $dirs));

        $this->checkGitAttributes();
        $this->checkComposerJson();

        if ($this->failed) {
            exit(1);
        }

        echo "OK\n";
    }

    public function checkGitAttributes(): void
    {
        if ($this->devDirs === []) {
            return;
        }

        if (!file_exists($this->base . '/.gitattributes')) {
            echo "\e[0;33m .gitattributes file is missing \e[0m\n";
            $this->failed = true;
            return;
        }

        $data = file_get_contents($this->base . '/.gitattributes');
        if ($data === false) {
            echo "\e[0;33m Cannot read .gitattributes \e[0m\n";
            $this->failed = true;
            return;
        }
        foreach ($this->devDirs as $dir) {
            if (preg_match("~^/{$dir} export-ignore$~m", $data) === 0) {
                echo "\e[0;33m '/{$dir} export-ignore' is missing in .gitattributes \e[0m\n";
                $this->failed = false;
            }
        }
    }

    public function checkComposerJson(): void
    {
        $json = file_get_contents($this->base . '/composer.json');
        if ($json === false) {
            echo "\e[0;33m Cannot read composer.json \e[0m\n";
            $this->failed = true;
            return;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            echo "\e[0;33m Wrong contents of composer.json \e[0m\n";
            $this->failed = true;
            return;
        }

        if (!array_key_exists('autoload', $data)) {
            echo "\e[0;33m 'autoload' key is missing in composer.json \e[0m\n";
            $this->failed = true;
        } else {
            foreach ($data['autoload'] as $method => $paths) {
                if ($method === 'classmap' || $method === 'psr-4' || $method === 'psr-0') {
                    foreach ($paths as $path) {
                        $path = trim($path, '\\/');
                        if (in_array($path, $this->devDirs, true)) {
                            echo "\e[0;33m Dev directory '{$path}' should not be listed in 'autoload' in composer.json \e[0m\n";
                            $this->failed = true;
                        }
                    }
                }
            }
        }

        if (!array_key_exists('autoload-dev', $data)) {
            echo "\e[0;33m 'autoload-dev' key is missing in composer.json \e[0m\n";
            $this->failed = true;
        }
    }

})();
