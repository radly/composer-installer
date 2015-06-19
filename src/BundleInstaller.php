<?php

namespace Rad\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Exception;

/**
 * Composer Bundle Installer
 *
 * @package Rad\Composer
 */
class BundleInstaller extends LibraryInstaller
{
    /**
     * {@inheritdoc}
     */
    public function supports($packageType)
    {
        return 'radphp-bundle' === $packageType;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        if (count(explode('-', $package->getPrettyName())) < 2) {
            throw new Exception('Bundle names should be like "NAME-bundle"');
        }

        $name = str_replace('-bundle', '', $package->getPrettyName());
        $name = str_replace(' ', '', ucwords(str_replace('-', '', $name)));

        return "bundles" . DIRECTORY_SEPARATOR . $name;
    }
}
