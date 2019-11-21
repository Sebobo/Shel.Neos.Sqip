<?php

namespace Shel\Neos\Sqip\FusionObjects;

/*
 * This script belongs to the package "Shel.Neos.Sqip".
 */

use Neos\Cache\Exception as CacheException;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Eel\CompilingEvaluator;
use Neos\Eel\Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\Exception\UnknownPackageException;
use Neos\Flow\Package\PackageManager;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Eel\Utility as EelUtility;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Service\ThumbnailService;
use Neos\Media\Exception\ThumbnailServiceException;

/**
 * Implementation class for rendering sqip images in fusion
 */
class SqipImageImplementation extends AbstractFusionObject
{

    /**
     * @Flow\InjectConfiguration(package="Shel.Neos.Sqip")
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $imageCache;

    /**
     * @Flow\Inject
     * @var ThumbnailService
     */
    protected $thumbnailService;

    /**
     * @return AssetInterface
     */
    public function getAsset(): AssetInterface
    {
        return $this->fusionValue('asset');
    }

    /**
     * Evaluate this Fusion object and return the result
     * @return string
     * @throws CacheException
     * @throws UnknownPackageException
     */
    public function evaluate(): string
    {
        $asset = $this->getAsset();
        $cacheIdentifier = $asset->getResource()->getSha1();

        /** @var string $cachedPlaceholder */
        $cachedPlaceholder = $this->imageCache->get($cacheIdentifier);
        if ($cachedPlaceholder) {
            return $cachedPlaceholder;
        }

        $asset = $this->getThumbnail($asset);
        $resource = $asset->getResource();

        $streamMetaData = stream_get_meta_data($resource->getStream());
        $pathAndFilename = $streamMetaData['uri'];
        $file = escapeshellarg($pathAndFilename);

        $binaryRootPath = 'Private/Library/node_modules/';
        $useGlobalBinary = $this->settings['useGlobalBinary'];
        $library = $this->settings['library'];
        $binaryPath = $this->settings['binaryPath'];
        $binaryPath = $useGlobalBinary === true ? $this->settings['globalBinaryPath'] . $library : $this->packageManager->getPackage('Shel.Neos.Sqip')->getResourcesPath() . $binaryRootPath . $binaryPath;
        $parameters = array_merge($this->settings['parameters'], ['file' => $file, 'binaryPath' => $binaryPath]);
        $eelExpression = $this->settings['arguments'];
        try {
            $cmd = escapeshellcmd(EelUtility::evaluateEelExpression($eelExpression, $this->eelEvaluator, $parameters));
        } catch (Exception $e) {
            return '';
        }

        $output = [];
        exec($cmd, $output, $result);
        $failed = (int)$result !== 0;

        if (!$failed) {
            // Extract base64 encoded svg data. This should be easier in future sqip versions.
            preg_match('/background-image: url\((.*)\)/m', join('', $output), $matches);
            $this->imageCache->set($cacheIdentifier, $matches[1]);
            return $matches[1];
        }

        return '';
    }

    /**
     * @param $asset
     * @return ImageInterface
     */
    protected function getThumbnail($asset): ImageInterface
    {
        try {
            $thumbnailConfiguration = $this->thumbnailService->getThumbnailConfigurationForPreset($this->settings['thumbnailPreset']);
            $asset = $this->thumbnailService->getThumbnail($asset, $thumbnailConfiguration);
            return $asset;
        } catch (ThumbnailServiceException $e) {
            // Didn't find preset, just use the original image to create the placeholder
        } catch (\Exception $e) {
            // Couldn't create thumbnail, just use the original image to create the placeholder
        }
        return $asset;
    }
}
