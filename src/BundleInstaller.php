<?php

namespace Rad\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Exception;
use RuntimeException;

/**
 * Composer Bundle Installer
 *
 * @package Rad\Composer
 */
class BundleInstaller extends LibraryInstaller
{
    private $projectPath;
    const DS = DIRECTORY_SEPARATOR;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);
        $this->projectPath = dirname(dirname(dirname(dirname(__DIR__))));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($packageType)
    {
        return 'radphp-bundle' === $packageType;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->updateConfigFile($package);
    }

    /**
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        $this->updateConfigFile($target);
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        return "src" . DIRECTORY_SEPARATOR . $this->getPackageName($package);
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     * @throws Exception
     */
    private function getPackageName(PackageInterface $package)
    {
        if (!is_array($package->getExtra()) && isset($package->getExtra()['installer-name'])) {
            return $package->getExtra()['installer-name'];
        }

        $name = explode('/', $package->getPrettyName());
        $packageName = $name[1];

        if (count(explode('-', $packageName)) < 2) {
            throw new Exception('Bundle names should be like "vendor/NAME-bundle"');
        }

        $packageName = str_replace('-bundle', '', $packageName);

        return str_replace(' ', '', ucwords(str_replace('-', '', $packageName)));
    }

    /**
     * Get primary namespace
     *
     * @param PackageInterface $package
     *
     * @return string
     */
    private function getPrimaryNamespace(PackageInterface $package)
    {
        $primaryNs = null;

        foreach ($package->getAutoload() as $type => $pathMap) {
            if ($type !== 'psr-4') {
                continue;
            }

            if (count($pathMap) === 1) {
                $primaryNs = key($pathMap);
                break;
            }

            $matches = preg_grep('#^(\./)?src/?$#', $pathMap);
            if ($matches) {
                $primaryNs = key($matches);
                break;
            }

            if (false !== ($key = array_search('', $pathMap, true))
                || false !== ($key = array_search('.', $pathMap, true))
            ) {
                $primaryNs = $key;
            }

            break;
        }

        if (!$primaryNs) {
            throw new RuntimeException(sprintf("Unable to get primary namespace for package %s.", $package->getName()));
        }

        return $primaryNs;
    }

    /**
     * Update config file
     *
     * @param PackageInterface $package
     *
     * @throws Exception
     */
    private function updateConfigFile(PackageInterface $package)
    {
        $defaultConfigFile = $this->projectPath . self::DS .
            'App' . self::DS . 'Resource' . self::DS . 'config' . self::DS . 'config.default.php';

        if (is_writeable($defaultConfigFile)) {
            $output = include $defaultConfigFile;
            $bundleConf = [
                'bundles' => [
                    $this->getPackageName($package) => [
                        'namespace' => str_replace('\\', '\\', $this->getPrimaryNamespace($package)),
                        'options' => []
                    ]
                ]
            ];

            $data = array_merge($output, $bundleConf);

            if (false === file_put_contents($defaultConfigFile, '<?php return ' . var_export($data, true) . ';')) {
                throw new RuntimeException(sprintf('Cannot update config file "%s"', $defaultConfigFile));
            }
        }
    }
}
