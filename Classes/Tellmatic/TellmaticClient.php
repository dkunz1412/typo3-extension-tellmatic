<?php
namespace Sto\Tellmatic\Tellmatic;

/*                                                                        *
 * This script belongs to the TYPO3 extension "tellmatic".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Sto\Tellmatic\Tellmatic\Request\AccessibleHttpRequest;
use Sto\Tellmatic\Tellmatic\Request\AddressCountRequest;
use Sto\Tellmatic\Tellmatic\Request\AddressSearchRequest;
use Sto\Tellmatic\Tellmatic\Request\SetCodeRequest;
use Sto\Tellmatic\Tellmatic\Request\SubscribeRequest;
use Sto\Tellmatic\Tellmatic\Request\TellmaticRequestInterface;
use Sto\Tellmatic\Tellmatic\Request\UnsubscribeRequest;
use Sto\Tellmatic\Tellmatic\Response\AddressCountResponse;
use Sto\Tellmatic\Tellmatic\Response\AddressSearchResponse;
use Sto\Tellmatic\Tellmatic\Response\SubscribeStateResponse;
use Sto\Tellmatic\Tellmatic\Response\TellmaticResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Client for executing API request on a Tellmatic server.
 */
class TellmaticClient {

	/**
	 * This URL overrides the default URL from the extension configuration
	 *
	 * @var string
	 */
	protected $customUrl;

	/**
	 * Default URLs to the different APIs
	 *
	 * @var array
	 */
	protected $defaultUrls = array(
		'subscribe' => 'api_subscribe.php',
		'unsubscribe' => 'api_unsubscribe.php',
		'getSubscribeState' => 'api_subscribe_state.php',
		'setCode' => 'api_set_code.php',
		'addressCount' => 'api_address_count.php',
		'addressSearch' => 'api_address_search.php',
	);

	/**
	 * @var \Sto\Tellmatic\Utility\ExtensionConfiguration
	 * @inject
	 */
	protected $extensionConfiguration;

	/**
	 * @var AccessibleHttpRequest
	 */
	protected $httpRequest;

	/**
	 * Configuration that should be used for the HTTP request
	 *
	 * @var array
	 */
	protected $httpRequestConfiguration = array(
		'follow_redirects' => TRUE,
	);

	/**
	 * The class that should be used as response
	 *
	 * @var string
	 */
	protected $responseClass = TellmaticResponse::class;

	/**
	 * @param string $email
	 * @return \Sto\Tellmatic\Tellmatic\Response\SubscribeStateResponse
	 * @throws \RuntimeException
	 */
	public function getSubscribeState($email) {

		$this->initializeHttpRequest();
		$this->httpRequest->setUrl($this->getUrl('getSubscribeState'));

		if (!GeneralUtility::validEmail($email)) {
			throw new \RuntimeException('The provided email address is invalid');
		}

		$this->responseClass = SubscribeStateResponse::class;
		$this->httpRequest->addPostParameter('email', $email);

		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * @param AddressCountRequest $addressCountRequest
	 * @return AddressCountResponse
	 */
	public function sendAddressCountRequest(AddressCountRequest $addressCountRequest) {
		$this->initializeRequest('addressCount', $addressCountRequest, AddressCountResponse::class);
		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * @param AddressSearchRequest $addressSearchRequest
	 * @return AddressSearchResponse
	 */
	public function sendAddressSearchRequest(AddressSearchRequest $addressSearchRequest) {
		$this->initializeRequest('addressSearch', $addressSearchRequest, AddressSearchResponse::class);
		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * @param SetCodeRequest $setCodeRequest
	 * @return TellmaticResponse
	 */
	public function sendSetCodeRequest(SetCodeRequest $setCodeRequest) {
		$this->initializeRequest('setCode', $setCodeRequest);
		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * Sends a subscribe request to the Tellmatic server.
	 *
	 * @param SubscribeRequest $subscribeRequest
	 * @return TellmaticResponse
	 */
	public function sendSubscribeRequest(SubscribeRequest $subscribeRequest) {
		$this->initializeRequest('subscribe', $subscribeRequest);
		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * @param UnsubscribeRequest $unsubscribeRequest
	 * @return TellmaticResponse
	 */
	public function sendUnsubscribeRequest(UnsubscribeRequest $unsubscribeRequest) {
		$this->initializeRequest('unsubscribe', $unsubscribeRequest);
		return $this->doRequestAndGenerateResponse();
	}

	/**
	 * Sets a custom URL that should be used for the next request
	 *
	 * @param string $customUrl
	 */
	public function setCustomUrl($customUrl) {
		if (!empty($customUrl)) {
			$this->customUrl = $customUrl;
		}
	}

	/**
	 * Setter for the HTTP request that should be used.
	 *
	 * @param \TYPO3\CMS\Core\Http\HttpRequest $httpRequest
	 */
	public function setHttpRequest($httpRequest) {
		$this->httpRequest = $httpRequest;
	}

	/**
	 * Adds a hmac POST parameter based on the serialized POST parameters array.
	 */
	protected function addApiKeyToPostParameters() {

		$postParameters = $this->httpRequest->getPostParameters();

		$encryptionKeyBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->getTellmaticApiKey();

		$this->httpRequest->addPostParameter('apiKey', GeneralUtility::hmac(serialize($postParameters), 'TellmaticAPI'));

		$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $encryptionKeyBackup;
	}


	/**
	 * @return \Sto\Tellmatic\Tellmatic\Response\TellmaticResponse
	 */
	protected function createResponse() {
		return GeneralUtility::makeInstance($this->responseClass);
	}

	/**
	 * @return \Sto\Tellmatic\Tellmatic\Response\TellmaticResponse
	 * @throws \RuntimeException
	 */
	protected function doRequestAndGenerateResponse() {

		$this->addApiKeyToPostParameters();

		$tellmaticResponse = $this->createResponse();

		$this->httpRequest->setMethod('POST');
		$httpResponse = $this->httpRequest->send();

		$responseStatus = $httpResponse->getStatus();
		if ($responseStatus !== 200) {
			throw new \RuntimeException(sprintf('HTTP error code %d: %s (requested URL was: %s', $responseStatus, $httpResponse->getReasonPhrase(), $httpResponse->getEffectiveUrl()));
		}

		$responseBody = $httpResponse->getBody();

		$parsedResponse = json_decode($responseBody, TRUE);
		if (!isset($parsedResponse)) {
			throw new \RuntimeException('JSON parser error: ' . json_last_error() . 'The parsed string was: ' . $responseBody, 1377802585);
		}

		if ($parsedResponse['success']) {
			$tellmaticResponse->setRequestSuccessful();
			$tellmaticResponse->processAdditionalResponseData($parsedResponse);
		} else {
			$tellmaticResponse->setFailureFromJsonResponse($parsedResponse);
		}

		// Reset HTTP request.
		$this->httpRequest = NULL;

		return $tellmaticResponse;
	}

	/**
	 * Reads the API key from the extension configuration.
	 *
	 * Moved to a seperate method for unit testing.
	 *
	 * @return string
	 */
	protected function getTellmaticApiKey() {
		return $this->extensionConfiguration->getTellmaticApiKey();
	}

	/**
	 * Generates the URL for the given request type
	 *
	 * @param string $requestType
	 * @return string
	 */
	protected function getUrl($requestType) {

		if (isset($this->customUrl)) {
			$url = $this->customUrl;
		} else {
			$baseUrl = $this->extensionConfiguration->getTellmaticUrl();

			if (empty($baseUrl) || parse_url($baseUrl) === FALSE) {
				throw new \RuntimeException('No valid base URL was configured: ' . $baseUrl);
			}

			$url = $baseUrl . $this->defaultUrls[$requestType];
		}

		return $url;
	}

	/**
	 * If no HTTP request was set externally it will be created.
	 *
	 * Additionally the configuration of the HTTP request will be
	 * initialized.
	 */
	protected function initializeHttpRequest() {

		if (isset($this->httpRequest)) {
			return;
		}

		$this->httpRequest = GeneralUtility::makeInstance(AccessibleHttpRequest::class);
		$this->httpRequest->setConfiguration($this->httpRequestConfiguration);
	}

	/**
	 * Initializes the HttpRequest, the URL and the response class.
	 *
	 * @param string $requestType
	 * @param TellmaticRequestInterface $request
	 * @param string $responseClass
	 */
	protected function initializeRequest($requestType, TellmaticRequestInterface $request, $responseClass = NULL) {

		$this->initializeHttpRequest();

		$this->httpRequest->setUrl($this->getUrl($requestType));
		$request->initializeHttpRequest($this->httpRequest);

		if (isset($responseClass)) {
			$this->responseClass = $responseClass;
		} else {
			$this->responseClass = TellmaticResponse::class;
		}
	}
}