<?php

namespace Cevou\Behat\ScreenshotCompareExtension\Context;

class ScreenshotCompareException extends \Exception {

  public function __construct(
    string $screenshot,
    string $message = "",
    int $code = 0,
    \Throwable $previous = NULL
  ) {
    $this->diffScreenshot = $screenshot;
    parent::__construct($message, $code, $previous);
  }


  protected $diffScreenshot;

  public function getDiffScreenshot() {
    return $this->diffScreenshot;
  }

}
