<?php

namespace AydinHassan\MagentoConnectPlugin;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvents;
use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Installer\InstallerEvent;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Class Plugin
 * @package AydinHassan\MagentoConnectPlugin
 * @author Aydin Hassan <aydin@hotmail.co.uk>
 */
class Plugin implements PluginInterface
{

    /**
     * @var string
     */
    protected $releasesUrlFormat = 'http://connect20.magentocommerce.com/community/%s/releases.xml';

    /**
     * @var string
     */
    protected $distUrlFormat = 'http://connect20.magentocommerce.com/community/%s/%s/%s-%s.tgz';

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $name = '\nosto_\tagging';

        $newName = '';
        $arr = str_split($name);
        while ($elem = array_shift($arr)) {
            if ($elem === '\\') {
                $newName .= strtoupper(array_shift($arr));
            } else {
                $newName .= $elem;
            }
        }







        $repositoryManager = $composer->getRepositoryManager();
        $extra             = $composer->getPackage()->getExtra();

        if (!isset($extra['connect-packages'])) {
            return;
        }

        $versionParser = new VersionParser;

        $links = [];
        foreach ($extra['connect-packages'] as $connectPackage => $version) {
            $releases   = $this->getVersionsForPackage($connectPackage);
            $repository = $this->addPackages($releases, $connectPackage);
            $repositoryManager->addRepository($repository);

            $constraint = $versionParser->parseConstraints($version);
            $links[] = new Link($composer->getPackage()->getName(), $connectPackage, $constraint);
        }

        if (!empty($links)) {
            $requires = $composer->getPackage()->getRequires();
            $requires = array_merge($requires, $links);
            $composer->getPackage()->setRequires($requires);
        }
    }

    /**
     * @param array $releases
     * @param string $connectPackage
     * @return RepositoryInterface
     */
    private function addPackages(array $releases, $connectPackage)
    {
        return new ArrayRepository(array_map(function ($release) use ($connectPackage) {
            $distUrl = sprintf($this->distUrlFormat, $connectPackage, $release, $connectPackage, $release);

            $package = new Package(strtolower($connectPackage), $release, $release);
            $package->setDistUrl($distUrl);
            $package->setDistType('tar');
            $package->setType('magento-module');
            $package->setExtra([
                'package-xml' => "package.xml",
            ]);

            return $package;
        }, $releases));
    }

    /**
     * @param string $package
     * @return array
     */
    private function getVersionsForPackage($package)
    {
        $url = sprintf($this->releasesUrlFormat, $package);
        $xml = file_get_contents($url);

        if (!$xml) {
            throw new \InvalidArgumentException(sprintf('URL: "%s" returned nothing', $xml));
        }

        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($xml);
        if ($xmlObj=== false) {
            $message = sprintf(
                "XML Parsing Failed. Errors: '%s'",
                implode(
                    "', '",
                    array_map(function (\LibXMLError $xmlError) {
                        return trim($xmlError->message);
                    }, libxml_get_errors())
                )
            );
            throw new \UnexpectedValueException($message);
        }

        $releases = [];
        foreach ($xmlObj->xpath('r') as $release) {
            if (isset($release->v)) {
                $releases[] = (string) $release->v;
            }
        }
        return $releases;
    }


    private function parseFromRequires()
    {

        //TODO: How to denote an uppercase letter?
        $name = '\nosto_\tagging';
        $newName = '';
        $arr = str_split($name);
        while ($elem = array_shift($arr)) {
            if ($elem === '\\') {
                $newName .= strtoupper(array_shift($arr));
            } else {
                $newName .= $elem;
            }
        }

    }
}
