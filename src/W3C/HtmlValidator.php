<?php

namespace W3C;

use SimpleXMLElement;
use W3C\Validation\Result;
use W3C\Validation\Violation;

/**
 * Class which validates HTML with the W3C Validator API.
 *
 * @author Michel Hunziker <info@michelhunziker.com>
 * @copyright Copyright (c) 2014 Michel Hunziker <info@michelhunziker.com>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD-3-Clause License
 */
class HtmlValidator {
	/**
	 * @var string
	 */
	protected $url = 'http://validator.w3.org/check';
	protected $html5url = 'http://validator.w3.org/nu/?out=json';

	/**
	 * Validates the provided HTML string and returns a result.
	 *
	 * @param string $html HTML string to validate
	 * @return Result
	 */
	public function validateInput($html) {
		$data = array('fragment' => $html);
		return $this->validate($data);
	}

	/**
	 * External call to the W3C HTML5 Validation API, using curl.
	 *
	 * @param array $data The data to post to the API.
	 * @return Result
	 */
	public function validateHTML5($data) {
		// Fuck CURL
		$socket = fsockopen("validator.w3.org", 80, $errno, $errstr, 15);
		$http = "POST /nu/?out=json HTTP/1.1\r\n";
		$http .= "Host: validator.w3.org\r\n";
		$http .= "User-Agent: curl\r\n";
		$http .= "Content-Type: text/html; charset=utf-8\r\n";
		$http .= "Content-length: " . strlen($data) . "\r\n";
		$http .= "Connection: close\r\n\r\n";
		$http .= $data . "\r\n\r\n";
		fwrite($socket, $http);
		$response = "";
		while (!feof($socket)) {
			$response .= fgets($socket, 4096);
		}
		fclose($socket);
		$response = substr($response, strpos($response, "\r\n\r\n") + 4);
		return $this->parseHTML5Response($response);
	}
	/**
	 * Parses the SOAP response of the API and returns a new Result object.
	 *
	 * @param string $response SOAP response of the API
	 * @return Result
	 */
	protected function parseHTML5Response($response) {
		//var_dump($response);exit;

		$json = json_decode($response);
		$result = new Result();
		$result->setIsValid(true);
		foreach ($json->messages as $index => $value) {
			if ($value->type == 'info') {
				continue;
			}

			$entry = new Violation();
			$entry->setLine($value->lastLine)
				->setColumn($value->lastColumn)
				->setMessage($value->message)
				->setSource($value->extract);

			if ($value->type == "error") {

				$result->addError($entry);
			} else if ($value->type == "warning") {
				$result->addWarning($entry);
			}
			$result->setIsValid(false);
		}
		return $result;
	}
	/**
	 * External call to the W3C Validation API, using curl.
	 *
	 * @param array $data The data to post to the API.
	 * @return Result
	 */
	public function validate(array $data) {
		$data['output'] = 'soap12';

		$resource = curl_init($this->url);
		curl_setopt($resource, CURLOPT_USERAGENT, 'curl');
		curl_setopt($resource, CURLOPT_POST, true);
		curl_setopt($resource, CURLOPT_POSTFIELDS, $data);
		curl_setopt($resource, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($resource);
		return $this->parseResponse($response);
	}
	/**
	 * Parses the SOAP response of the API and returns a new Result object.
	 *
	 * @param string $response SOAP response of the API
	 * @return Result
	 */
	protected function parseResponse($response) {
		$xml = new SimpleXMLElement($response);
		var_dump($response);exit;

		$ns = $xml->getNamespaces(true);
		$data = $xml->children($ns['env'])->children($ns['m'])->markupvalidationresponse;

		$result = new Result();
		$result->setIsValid($data->validity == 'true');

		foreach ($data->errors->errorlist->error as $error) {
			$entry = $this->getEntry($error);
			$result->addError($entry);
		}

		foreach ($data->warnings->warninglist->warning as $warning) {
			if (strpos($warning->messageid, 'W') === false) {
				$entry = $this->getEntry($warning);
				$result->addWarning($entry);
			}
		}

		return $result;
	}

	/**
	 * Create a violation object from the provided xml.
	 *
	 * @param SimpleXMLElement $xml XML element which contains the violation details
	 * @return Violation
	 */
	protected function getEntry(SimpleXMLElement $xml) {
		$entry = new Violation();
		$entry->setLine($xml->line)
			->setColumn($xml->col)
			->setMessage($xml->message)
			->setExplanation($xml->explanation)
			->setSource($xml->source);

		return $entry;
	}
}
