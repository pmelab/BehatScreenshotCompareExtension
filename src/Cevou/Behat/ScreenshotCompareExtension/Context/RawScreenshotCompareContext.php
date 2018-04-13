<?php

namespace Cevou\Behat\ScreenshotCompareExtension\Context;

use Behat\MinkExtension\Context\RawMinkContext;
use Gaufrette\Filesystem as GaufretteFilesystem;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class RawScreenshotCompareContext extends RawMinkContext implements ScreenshotCompareAwareContext
{
    private $screenshotCompareConfigurations;
    private $screenshotCompareParameters;

    protected $currentSelector = FALSE;
    protected $ignoredSelectors = [];

    /**
     * {@inheritdoc}
     */
    public function setScreenshotCompareConfigurations(array $configurations)
    {
        $this->screenshotCompareConfigurations = $configurations;
    }

    /**
     * {@inheritdoc}
     */
    public function setScreenshotCompareParameters(array $parameters)
    {
        $this->screenshotCompareParameters = $parameters;
    }

    protected function translateSelector($selector) {
      return array_key_exists($selector, $this->screenshotCompareParameters['selectors']) ? $this->screenshotCompareParameters['selectors'][$selector]['selector'] : $selector;
    }

    /**
     * @param $sessionName
     * @param $fileName
     * @throws \LogicException
     * @throws \ImagickException
     * @throws \Cevou\Behat\ScreenshotCompareExtension\Context\ScreenshotCompareException
     * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
     */
    public function compareScreenshot($sessionName, $fileName, $fullscreen = TRUE, $scrollTop = 0, $selector = FALSE)
    {
        $this->assertSession($sessionName);

        $session = $this->getSession($sessionName);

        if (!array_key_exists($sessionName, $this->screenshotCompareConfigurations)) {
            throw new \LogicException(sprintf('The configuration for session \'%s\' is not defined.', $sessionName));
        }
        $configuration = $this->screenshotCompareConfigurations[$sessionName];

        /** @var GaufretteFilesystem $targetFilesystem */
        $targetFilesystem = $configuration['adapter'];

        if ($this->ignoredSelectors) {
          foreach ($this->ignoredSelectors as $selector) {
            $selector = $this->translateSelector($selector);
            $session->executeScript("document.querySelectorAll('$selector').forEach(function (el) { el.remove() });");
          }
        }

        // When taking full screen screenshots, resize the window to show
        // everything. This might not work on all devices.
        if ($fullscreen) {
          $bodyWidth = (int) $session->evaluateScript("document.documentElement.offsetWidth");
          $bodyHeight = (int) $session->evaluateScript("document.documentElement.offsetHeight");

          $innerWidth = (int) $session->evaluateScript('window.innerWidth');
          $innerHeight = (int) $session->evaluateScript('window.innerHeight');

          $outerWidth = (int) $session->evaluateScript('window.outerWidth');
          $outerHeight = (int) $session->evaluateScript('window.outerHeight');

          $width = $outerWidth + ($bodyWidth - $innerWidth);
          $height = $outerHeight + ($bodyHeight - $innerHeight);

          $session->resizeWindow($width, $height);
        }

        $actualScreenshot = new \Imagick();
        $actualScreenshot->readImageBlob($session->getScreenshot());

        $screenshotDir = $this->screenshotCompareParameters['screenshot_dir'];
        $compareFile = $screenshotDir . DIRECTORY_SEPARATOR . $fileName;
        $sourceFilesystem = new SymfonyFilesystem();

        $actualGeometry = $actualScreenshot->getImageGeometry();

        //Crop the image according to the settings
        if ($selector = $this->currentSelector) {
          $selector = $this->translateSelector($selector);
          $top = (int) $session->evaluateScript("document.querySelector('$selector').getBoundingClientRect().top");
          $left = (int) $session->evaluateScript("document.querySelector('$selector').getBoundingClientRect().left");
          $width = (int) $session->evaluateScript("document.querySelector('$selector').getBoundingClientRect().width");
          $height = (int) $session->evaluateScript("document.querySelector('$selector').getBoundingClientRect().height");
          $actualScreenshot->cropImage($width, $height, $left, $top);
          //Refresh geomerty information
          $actualGeometry = $actualScreenshot->getImageGeometry();
        }

        if (!$sourceFilesystem->exists($compareFile)){
          if ($this->screenshotCompareParameters['autocreate']) {
            $sourceFilesystem->dumpFile($compareFile, $actualScreenshot);
            return;
          }
          else {
            throw new FileNotFoundException(null, 0, null, $compareFile);
          }
        }

        $compareScreenshot = new \Imagick($compareFile);
        $compareGeometry = $compareScreenshot->getImageGeometry();

        //ImageMagick can only compare files which have the same size
        if ($actualGeometry !== $compareGeometry) {
            throw new \ImagickException(sprintf("Screenshots don't have an equal geometry. Should be %sx%s but is %sx%s", $compareGeometry['width'], $compareGeometry['height'], $actualGeometry['width'], $actualGeometry['height']));
        }

        $result = $actualScreenshot->compareImages($compareScreenshot, \Imagick::METRIC_ROOTMEANSQUAREDERROR);

        if ($result[1] > 0) {
            $diffFileName = sprintf('%s_%s', $this->getMinkParameter('browser_name'), $fileName);

            /** @var \Imagick $diffScreenshot */
            $diffScreenshot = $result[0];
            $diffScreenshot->setImageFormat("png");
            $targetFilesystem->delete($diffFileName);
            $targetFilesystem->write($diffFileName, $diffScreenshot);
            throw new ScreenshotCompareException($diffFileName, sprintf("Files are not equal. Diff saved to %s", $diffFileName));
        }
    }
}
