<?php

namespace {
// Create aliases of WebDriver classes in root namespace to provide compatibility layer with php-webdriver <1.0.
    if (!class_exists('RemoteWebDriver') && class_exists('Facebook\WebDriver\Remote\RemoteWebDriver')) {
        /** @deprecated */
        interface WebDriverAction extends \Facebook\WebDriver\WebDriverAction {};
        /** @deprecated */
        class WebDriverAlert extends \Facebook\WebDriver\WebDriverAlert {};
        /** @deprecated */
        class WebDriverBy extends \Facebook\WebDriver\WebDriverBy {};
        /** @deprecated */
        interface WebDriverCapabilities extends \Facebook\WebDriver\WebDriverCapabilities {};
        /** @deprecated */
        interface WebDriverCommandExecutor extends \Facebook\WebDriver\WebDriverCommandExecutor {};
        /** @deprecated */
        class WebDriverDimension extends \Facebook\WebDriver\WebDriverDimension {};
        /** @deprecated */
        class WebDriverDispatcher extends \Facebook\WebDriver\WebDriverDispatcher {};
        /** @deprecated */
        interface WebDriverElement extends \Facebook\WebDriver\WebDriverElement {};
        /** @deprecated */
        interface WebDriverEventListener extends \Facebook\WebDriver\WebDriverEventListener {};
        /** @deprecated */
        class WebDriverExpectedCondition extends \Facebook\WebDriver\WebDriverExpectedCondition {};
        /** @deprecated */
        interface WebDriverHasInputDevices extends \Facebook\WebDriver\WebDriverHasInputDevices {};
        /** @deprecated */
        interface WebDriverKeyboard extends \Facebook\WebDriver\WebDriverKeyboard {};
        /** @deprecated */
        class WebDriverKeys extends \Facebook\WebDriver\WebDriverKeys {};
        /** @deprecated */
        interface WebDriverMouse extends \Facebook\WebDriver\WebDriverMouse {};
        /** @deprecated */
        class WebDriverNavigation extends \Facebook\WebDriver\WebDriverNavigation {};
        /** @deprecated */
        class WebDriverOptions extends \Facebook\WebDriver\WebDriverOptions {};
        /** @deprecated */
        interface WebDriver extends \Facebook\WebDriver\WebDriver {};
        /** @deprecated */
        class WebDriverPlatform extends \Facebook\WebDriver\WebDriverPlatform {};
        /** @deprecated */
        class WebDriverPoint extends \Facebook\WebDriver\WebDriverPoint {};
        /** @deprecated */
        interface WebDriverSearchContext extends \Facebook\WebDriver\WebDriverSearchContext {};
        /** @deprecated */
        class WebDriverSelect extends \Facebook\WebDriver\WebDriverSelect {};
        /** @deprecated */
        interface WebDriverTargetLocator extends \Facebook\WebDriver\WebDriverTargetLocator {};
        /** @deprecated */
        class WebDriverTimeouts extends \Facebook\WebDriver\WebDriverTimeouts {};
        /** @deprecated */
        class WebDriverUpAction extends \Facebook\WebDriver\WebDriverUpAction {};
        /** @deprecated */
        class WebDriverWait extends \Facebook\WebDriver\WebDriverWait {};
        /** @deprecated */
        class WebDriverWindow extends \Facebook\WebDriver\WebDriverWindow {};
        /** @deprecated */
        class DesiredCapabilities extends \Facebook\WebDriver\Remote\DesiredCapabilities {};
        /** @deprecated */
        class DriverCommand extends \Facebook\WebDriver\Remote\DriverCommand {};
        /** @deprecated */
        interface ExecuteMethod extends \Facebook\WebDriver\Remote\ExecuteMethod {};
        /** @deprecated */
        interface FileDetector extends \Facebook\WebDriver\Remote\FileDetector {};
        /** @deprecated */
        class HttpCommandExecutor extends \Facebook\WebDriver\Remote\HttpCommandExecutor {};
        /** @deprecated */
        class LocalFileDetector extends \Facebook\WebDriver\Remote\LocalFileDetector {};
        /** @deprecated */
        class RemoteExecuteMethod extends \Facebook\WebDriver\Remote\RemoteExecuteMethod {};
        /** @deprecated */
        class RemoteKeyboard extends \Facebook\WebDriver\Remote\RemoteKeyboard {};
        /** @deprecated */
        class RemoteMouse extends \Facebook\WebDriver\Remote\RemoteMouse {};
        /** @deprecated */
        class RemoteTargetLocator extends \Facebook\WebDriver\Remote\RemoteTargetLocator {};
        /** @deprecated */
        class RemoteTouchScreen extends \Facebook\WebDriver\Remote\RemoteTouchScreen {};
        /** @deprecated */
        class RemoteWebDriver extends \Facebook\WebDriver\Remote\RemoteWebDriver {};
        /** @deprecated */
        class RemoteWebElement extends \Facebook\WebDriver\Remote\RemoteWebElement {};
        /** @deprecated */
        class UselessFileDetector extends \Facebook\WebDriver\Remote\UselessFileDetector {};
        /** @deprecated */
        class WebDriverBrowserType extends \Facebook\WebDriver\Remote\WebDriverBrowserType {};
        /** @deprecated */
        class WebDriverCapabilityType extends \Facebook\WebDriver\Remote\WebDriverCapabilityType {};
        /** @deprecated */
        class WebDriverCommand extends \Facebook\WebDriver\Remote\WebDriverCommand {};
        /** @deprecated */
        class WebDriverResponse extends \Facebook\WebDriver\Remote\WebDriverResponse {};
        /** @deprecated */
        class ElementNotSelectableException extends \Facebook\WebDriver\Exception\ElementNotSelectableException {};
        /** @deprecated */
        class ElementNotVisibleException extends \Facebook\WebDriver\Exception\ElementNotVisibleException {};
        /** @deprecated */
        class ExpectedException extends \Facebook\WebDriver\Exception\ExpectedException {};
        /** @deprecated */
        class IMEEngineActivationFailedException extends \Facebook\WebDriver\Exception\IMEEngineActivationFailedException {};
        /** @deprecated */
        class IMENotAvailableException extends \Facebook\WebDriver\Exception\IMENotAvailableException {};
        /** @deprecated */
        class IndexOutOfBoundsException extends \Facebook\WebDriver\Exception\IndexOutOfBoundsException {};
        /** @deprecated */
        class InvalidCookieDomainException extends \Facebook\WebDriver\Exception\InvalidCookieDomainException {};
        /** @deprecated */
        class InvalidCoordinatesException extends \Facebook\WebDriver\Exception\InvalidCoordinatesException {};
        /** @deprecated */
        class InvalidElementStateException extends \Facebook\WebDriver\Exception\InvalidElementStateException {};
        /** @deprecated */
        class InvalidSelectorException extends \Facebook\WebDriver\Exception\InvalidSelectorException {};
        /** @deprecated */
        class MoveTargetOutOfBoundsException extends \Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException {};
        /** @deprecated */
        class NoAlertOpenException extends \Facebook\WebDriver\Exception\NoAlertOpenException {};
        /** @deprecated */
        class NoCollectionException extends \Facebook\WebDriver\Exception\NoCollectionException {};
        /** @deprecated */
        class NoScriptResultException extends \Facebook\WebDriver\Exception\NoScriptResultException {};
        /** @deprecated */
        class NoStringException extends \Facebook\WebDriver\Exception\NoStringException {};
        /** @deprecated */
        class NoStringLengthException extends \Facebook\WebDriver\Exception\NoStringLengthException {};
        /** @deprecated */
        class NoStringWrapperException extends \Facebook\WebDriver\Exception\NoStringWrapperException {};
        /** @deprecated */
        class NoSuchCollectionException extends \Facebook\WebDriver\Exception\NoSuchCollectionException {};
        /** @deprecated */
        class NoSuchDocumentException extends \Facebook\WebDriver\Exception\NoSuchDocumentException {};
        /** @deprecated */
        class NoSuchDriverException extends \Facebook\WebDriver\Exception\NoSuchDriverException {};
        /** @deprecated */
        class NoSuchElementException extends \Facebook\WebDriver\Exception\NoSuchElementException {};
        /** @deprecated */
        class NoSuchFrameException extends \Facebook\WebDriver\Exception\NoSuchFrameException {};
        /** @deprecated */
        class NoSuchWindowException extends \Facebook\WebDriver\Exception\NoSuchWindowException {};
        /** @deprecated */
        class NullPointerException extends \Facebook\WebDriver\Exception\NullPointerException {};
        /** @deprecated */
        class ScriptTimeoutException extends \Facebook\WebDriver\Exception\ScriptTimeoutException {};
        /** @deprecated */
        class SessionNotCreatedException extends \Facebook\WebDriver\Exception\SessionNotCreatedException {};
        /** @deprecated */
        class StaleElementReferenceException extends \Facebook\WebDriver\Exception\StaleElementReferenceException {};
        /** @deprecated */
        class TimeOutException extends \Facebook\WebDriver\Exception\TimeOutException {};
        /** @deprecated */
        class UnableToSetCookieException extends \Facebook\WebDriver\Exception\UnableToSetCookieException {};
        /** @deprecated */
        class UnexpectedAlertOpenException extends \Facebook\WebDriver\Exception\UnexpectedAlertOpenException {};
        /** @deprecated */
        class UnexpectedJavascriptException extends \Facebook\WebDriver\Exception\UnexpectedJavascriptException {};
        /** @deprecated */
        class UnexpectedTagNameException extends \Facebook\WebDriver\Exception\UnexpectedTagNameException {};
        /** @deprecated */
        class UnknownCommandException extends \Facebook\WebDriver\Exception\UnknownCommandException {};
        /** @deprecated */
        class UnknownServerException extends \Facebook\WebDriver\Exception\UnknownServerException {};
        /** @deprecated */
        class UnrecognizedExceptionException extends \Facebook\WebDriver\Exception\UnrecognizedExceptionException {};
        /** @deprecated */
        class UnsupportedOperationException extends \Facebook\WebDriver\Exception\UnsupportedOperationException {};
        /** @deprecated */
        class WebDriverCurlException extends \Facebook\WebDriver\Exception\WebDriverCurlException {};
        /** @deprecated */
        class WebDriverException extends \Facebook\WebDriver\Exception\WebDriverException {};
        /** @deprecated */
        class XPathLookupException extends \Facebook\WebDriver\Exception\XPathLookupException {};
        /** @deprecated */
        class FirefoxDriver extends \Facebook\WebDriver\Firefox\FirefoxDriver {};
        /** @deprecated */
        class FirefoxProfile extends \Facebook\WebDriver\Firefox\FirefoxProfile {};
        /** @deprecated */
        class ChromeDriver extends \Facebook\WebDriver\Chrome\ChromeDriver {};
        /** @deprecated */
        class ChromeDriverService extends \Facebook\WebDriver\Chrome\ChromeDriverService {};
        /** @deprecated */
        class ChromeOptions extends \Facebook\WebDriver\Chrome\ChromeOptions {};
        /** @deprecated */
        class WebDriverActions extends \Facebook\WebDriver\Interactions\WebDriverActions {};
        /** @deprecated */
        class WebDriverCompositeAction extends \Facebook\WebDriver\Interactions\WebDriverCompositeAction {};
        /** @deprecated */
        class WebDriverTouchActions extends \Facebook\WebDriver\Interactions\WebDriverTouchActions {};
        /** @deprecated */
        class WebDriverButtonReleaseAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverButtonReleaseAction {};
        /** @deprecated */
        class WebDriverClickAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverClickAction {};
        /** @deprecated */
        class WebDriverClickAndHoldAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverClickAndHoldAction {};
        /** @deprecated */
        class WebDriverContextClickAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverContextClickAction {};
        /** @deprecated */
        class WebDriverCoordinates extends \Facebook\WebDriver\Interactions\Internal\WebDriverCoordinates {};
        /** @deprecated */
        class WebDriverDoubleClickAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverDoubleClickAction {};
        /** @deprecated */
        class WebDriverKeyDownAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverKeyDownAction {};
        /** @deprecated */
        class WebDriverKeysRelatedAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverKeysRelatedAction {};
        /** @deprecated */
        class WebDriverKeyUpAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverKeyUpAction {};
        /** @deprecated */
        class WebDriverMouseAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverMouseAction {};
        /** @deprecated */
        class WebDriverMouseMoveAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverMouseMoveAction {};
        /** @deprecated */
        class WebDriverMoveToOffsetAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverMoveToOffsetAction {};
        /** @deprecated */
        class WebDriverSendKeysAction extends \Facebook\WebDriver\Interactions\Internal\WebDriverSendKeysAction {};
        /** @deprecated */
        class WebDriverDoubleTapAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverDoubleTapAction {};
        /** @deprecated */
        class WebDriverDownAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverDownAction {};
        /** @deprecated */
        class WebDriverFlickAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverFlickAction {};
        /** @deprecated */
        class WebDriverFlickFromElementAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverFlickFromElementAction {};
        /** @deprecated */
        class WebDriverLongPressAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverLongPressAction {};
        /** @deprecated */
        class WebDriverMoveAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverMoveAction {};
        /** @deprecated */
        class WebDriverScrollAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverScrollAction {};
        /** @deprecated */
        class WebDriverScrollFromElementAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverScrollFromElementAction {};
        /** @deprecated */
        class WebDriverTapAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverTapAction {};
        /** @deprecated */
        class WebDriverTouchAction extends \Facebook\WebDriver\Interactions\Touch\WebDriverTouchAction {};
        /** @deprecated */
        interface WebDriverTouchScreen extends \Facebook\WebDriver\Interactions\Touch\WebDriverTouchScreen {};
        /** @deprecated */
        interface WebDriverLocatable extends \Facebook\WebDriver\Internal\WebDriverLocatable {};
    }
}
