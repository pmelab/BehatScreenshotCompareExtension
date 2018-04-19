<?php

namespace Cevou\Behat\ScreenshotCompareExtension\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;

class ScreenshotCompareContext extends RawScreenshotCompareContext {

  /**
   * Checks if the screenshot of the default session  is equal to a defined screen
   *
   * @Then /^the screenshot should be equal to "(?P<fileName>[^"]+)"$/
   */
  public function assertScreenshotCompare($fileName) {
      $this->compareScreenshot($this->getMink()->getDefaultSessionName(), $fileName);
  }

  /**
   * @Given /^I look at (?P<selector>.+)$/
   */
  public function iLookAt($selector) {
    $this->currentSelector = $selector;
  }

  /**
   * @Given /^I ignore (.*)$/
   */
  public function iIgnore($selector) {
    $this->ignoredSelectors[] = $selector;
  }


  /**
   * Take screenshot when step fails.
   *
   * @AfterStep
   */
  public function takeScreenshotAfterFailedStep(AfterStepScope $event)
  {
    $sessionName = $this->getMink()->getDefaultSessionName();
    if (!$this->getSession($sessionName)->getDriver() instanceof Selenium2Driver) {
      return;
    }

    if (!$event->getTestResult()->isPassed() && $this->getSession()) {
      $filename = $event->getFeature()->getFile() . '.' . $event->getStep()->getLine() . '.png';
      $exception = $event->getTestResult()->getCallResult()->getException();
      $this->takeFullPageScreenshot($sessionName, $filename);
      $exception->diffScreenshot = $filename;
    }
  }

}
