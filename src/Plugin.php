<?php

namespace AydinHassan\MagentoConnectPlugin;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer;
use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\PluginInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryInterface;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Class Plugin
 * @package AydinHassan\MagentoConnectPlugin
 * @author Aydin Hassan <aydin@hotmail.co.uk>
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var string
     */
    protected $releasesUrlFormat = 'https://connect20.magentocommerce.com/community/%s/releases.xml';

    /**
     * @var string
     */
    protected $distUrlFormat = 'https://connect20.magentocommerce.com/community/%s/%s/%s-%s.tgz';

    /**
     * @var string
     */
    protected static $packageName = 'aydin-hassan/magento-connect-composer-plugin';

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => [
                ['postPackageInstall', 0]
            ],
        ];
    }

    /**
     * @param PackageEvent $event
     */
    public function postPackageInstall(PackageEvent $event)
    {
        $operation = $event->getOperation();
        if (!$operation instanceof InstallOperation) {
            return;
        }

        $package = $operation->getPackage();

        if ($package->getName() !== static::$packageName) {
            return;
        }

        $extra = $event->getComposer()->getPackage()->getExtra();
        if (!isset($extra['connect-packages']) || !count($extra['connect-packages'])) {
            return;
        }

        //skip if we are installing from lock file
        if ($event->getComposer()->getLocker()->isLocked()) {
            return;
        }

        $packages = implode('", "', array_keys($extra['connect-packages']));
        $message  = '<comment>The package(s): "%s" will be installed the next time you run ';
        $message .= 'composer update</comment>';
        $event->getIO()->write(sprintf($message, $packages));
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $repositoryManager = $composer->getRepositoryManager();
        $extra             = $composer->getPackage()->getExtra();

        if (!isset($extra['connect-packages'])) {
            return;
        }

        $composer
            ->getDownloadManager()
            ->setDownloader(TarDownloader::ARCHIVE_CODE, new TarDownloader($io, $composer->getConfig()));



        $versionParser = new VersionParser;

        $links = [];
        foreach ($extra['connect-packages'] as $connectPackage => $version) {
            try {
                $releases = $this->getVersionsForPackage($connectPackage);
            } catch (InvalidArgumentException $e) {
                $message  = '<error>Could not find release manifest for module with extension key: "%s". ';
                $message .= 'Did you get the casing right? Error: "%s"</error>';

                $io->writeError(sprintf($message, $connectPackage, $e->getMessage()), true);
                continue;
            } catch (UnexpectedValueException $e) {
                $message  = '<error>Non valid XML return from connect for module with extension key: "%s".</error>';
                $message .= $e->getMessage();

                $io->writeError(sprintf($message, $connectPackage), true);
                continue;
            }
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
        $handle = curl_init($url);
        curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
        $xml = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($httpCode != 200){
            throw new InvalidArgumentException(sprintf('URL: "%s" did not return a 200 response', $url));
        }

        if (!$xml) {
            throw new InvalidArgumentException(sprintf('URL: "%s" returned nothing', $url));
        }

        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($xml);
        if ($xmlObj=== false) {
            $message = sprintf(
                'XML Parsing Failed. Url: "%s", Errors: "%s"',
                $url,
                implode(
                    "', '",
                    array_map(function (\LibXMLError $xmlError) {
                        return trim($xmlError->message);
                    }, libxml_get_errors())
                )
            );
            throw new UnexpectedValueException($message);
        }

        $releases = [];
        foreach ($xmlObj->xpath('r') as $release) {
            if (isset($release->v)) {
                $releases[] = (string) $release->v;
            }
        }
        return $releases;
    }
}
