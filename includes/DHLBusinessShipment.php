<?php

// Set correct encoding
mb_internal_encoding('UTF-8');

// Get required classes
require_once('Address.php');
require_once('DHL_Credentials.php');
require_once('DHL_Company.php');
require_once('DHL_Receiver.php');

/**
 * Class DHLBusinessShipment
 */
class DHLBusinessShipment {
	const DHL_SANDBOX_URL = 'https://cig.dhl.de/services/sandbox/soap';
	const DHL_PRODUCTION_URL = 'https://cig.dhl.de/services/production/soap';
	const API_URL = 'https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/1.0/geschaeftskundenversand-api-1.0.wsdl';

	/**
	 * Contains the DHL_Credentials Object
	 *
	 * @var DHL_Credentials $credentials - DHL_Credentials Object
	 */
	private $credentials;

	/**
	 * Contains the DHL_Company Object
	 *
	 * @var DHL_Company $info - DHL_Company Object
	 */
	private $info;

	/**
	 * Contains the SoapClient Object
	 *
	 * @var SoapClient $client - SoapClient Object
	 */
	private $client;

	/**
	 * todo doc
	 *
	 * @var array $errors
	 */
	private $errors = array();

	/**
	 * Contains if the Object runs in Sandbox-Mode
	 *
	 * @var bool $sandbox - Run the Object in Sandbox mode
	 */
	private $sandbox;

	/**
	 * Contains if the Object had enabled Log-Messages
	 *
	 * @var bool $log - Has the Object enabled Log-Messages
	 */
	private $log = false;

	/**
	 * Constructor for Shipment SDK
	 *
	 * @param DHL_Credentials $api_credentials
	 * @param DHL_Company $customer_info
	 * @param boolean $sandbox - Use sandbox or production environment (Default false)
	 */
	public function __construct($api_credentials, $customer_info, $sandbox = false) {
		$this->credentials = $api_credentials;
		$this->info = $customer_info;
		$this->sandbox = $sandbox;
	}

	/**
	 * Clears Memory
	 */
	public function __destruct() {
		unset($this->credentials);
		unset($this->info);
		unset($this->client);
		unset($this->errors);
		unset($this->sandbox);
		unset($this->log);
	}

	/**
	 * @return DHL_Credentials
	 */
	private function getCredentials() {
		return $this->credentials;
	}

	/**
	 * @param DHL_Credentials $credentials
	 */
	private function setCredentials($credentials) {
		$this->credentials = $credentials;
	}

	/**
	 * @return DHL_Company
	 */
	private function getInfo() {
		return $this->info;
	}

	/**
	 * @param DHL_Company $info
	 */
	private function setInfo($info) {
		$this->info = $info;
	}

	/**
	 * @return SoapClient
	 */
	private function getClient() {
		return $this->client;
	}

	/**
	 * @param SoapClient $client
	 */
	private function setClient($client) {
		$this->client = $client;
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param array $errors
	 */
	private function setErrors($errors) {
		$this->errors = $errors;
	}

	/**
	 * @return boolean
	 */
	private function isSandbox() {
		return $this->sandbox;
	}

	/**
	 * @param boolean $sandbox
	 */
	private function setSandbox($sandbox) {
		$this->sandbox = $sandbox;
	}

	/**
	 * @return boolean
	 */
	public function isLog() {
		return $this->log;
	}

	/**
	 * @param boolean $log
	 */
	public function setLog($log) {
		$this->log = $log;
	}

	/**
	 * Add the Massage to Log if enabled
	 *
	 * @param mixed $message - Message to add to Log
	 */
	private function log($message) {
		if($this->isLog()) {
			if(is_array($message) || is_object($message))
				error_log(print_r($message, true));
			else
				error_log($message);
		}
	}

	/**
	 * todo doc
	 */
	private function buildClient() {
		$header = $this->buildAuthHeader();

		if($this->sandbox)
			$location = self::DHL_SANDBOX_URL;
		else
			$location = self::DHL_PRODUCTION_URL;

		$auth_params = array(
			'login' => $this->credentials['api_user'],
			'password' => $this->credentials['api_password'],
			'location' => $location,
			'trace' => 1
		);

		$this->log($auth_params);
		$this->client = new SoapClient(self::API_URL, $auth_params);
		$this->client->__setSoapHeaders($header);
		$this->log($this->client);
	}

	/**
	 * todo doc
	 *
	 * @param DHL_Receiver $customer_details
	 * @param null $shipment_details - todo param not used yet? leave it to null
	 * @return array|bool
	 */
	function createNationalShipment($customer_details, $shipment_details = null) {
		$this->buildClient();

		$shipment = array();

		// Version
		$shipment['Version'] = array('majorRelease' => '1', 'minorRelease' => '0');

		// Order
		$shipment['ShipmentOrder'] = array();

		// Fixme/TODO
		$shipment['ShipmentOrder']['SequenceNumber'] = '1';

		// Shipment
		$s = array();
		$s['ProductCode'] = 'EPN';
		$s['ShipmentDate'] = date('Y-m-d');
		$s['EKP'] = $this->credentials['ekp'];

		$s['Attendance'] = array();
		$s['Attendance']['partnerID'] = '01';

		if($shipment_details == null) {
			$s['ShipmentItem'] = array();
			$s['ShipmentItem']['WeightInKG'] = '5';
			$s['ShipmentItem']['LengthInCM'] = '50';
			$s['ShipmentItem']['WidthInCM'] = '50';
			$s['ShipmentItem']['HeightInCM'] = '50';
			// FIXME/TODO: What is this - maybe pk for international pl is palette? see https://github.com/tobias-redmann/dhl-php-sdk/issues/2
			// $s['ShipmentItem']['PackageType'] = 'PL'; may removed?
		}

		$shipment['ShipmentOrder']['Shipment']['ShipmentDetails'] = $s;

		$shipper = array();
		$shipper['Company'] = array();
		$shipper['Company']['Company'] = array();
		$shipper['Company']['Company']['name1'] = $this->info['company_name'];

		$shipper['Address'] = array();
		$shipper['Address']['streetName'] = $this->info['street_name'];
		$shipper['Address']['streetNumber'] = $this->info['street_number'];
		$shipper['Address']['Zip'] = array();
		$shipper['Address']['Zip'][strtolower($this->info['country'])] = $this->info['zip'];
		$shipper['Address']['city'] = $this->info['city'];

		$shipper['Address']['Origin'] = array('countryISOCode' => 'DE');

		$shipper['Communication'] = array();
		$shipper['Communication']['email'] = $this->info['email'];
		$shipper['Communication']['phone'] = $this->info['phone'];
		$shipper['Communication']['internet'] = $this->info['internet'];
		$shipper['Communication']['contactPerson'] = $this->info['contact_person'];


		$shipment['ShipmentOrder']['Shipment']['Shipper'] = $shipper;

		$receiver = array();

		$receiver['Company'] = array();
		$receiver['Company']['Person'] = array();
		$receiver['Company']['Person']['firstname'] = $customer_details['first_name'];
		$receiver['Company']['Person']['lastname'] = $customer_details['last_name'];

		$receiver['Address'] = array();
		$receiver['Address']['streetName'] = $customer_details['street_name'];
		$receiver['Address']['streetNumber'] = $customer_details['street_number'];
		$receiver['Address']['Zip'] = array();
		$receiver['Address']['Zip'][strtolower($customer_details['country'])] = $customer_details['zip'];
		$receiver['Address']['city'] = $customer_details['city'];
		$receiver['Communication'] = array();

		$receiver['Address']['Origin'] = array('countryISOCode' => 'DE');

		$shipment['ShipmentOrder']['Shipment']['Receiver'] = $receiver;


		$response = $this->client->CreateShipmentDD($shipment);

		if(is_soap_fault($response) || $response->status->StatusCode != 0) {
			if(is_soap_fault($response))
				$this->errors[] = $response->faultstring;
			else
				$this->errors[] = $response->status->StatusMessage;

			return false;
		} else {
			$r = array();
			$r['shipment_number'] = (String) $response->CreationState->ShipmentNumber->shipmentNumber;
			$r['piece_number'] = (String) $response->CreationState->PieceInformation->PieceNumber->licensePlate;
			$r['label_url'] = (String) $response->CreationState->Labelurl;

			return $r;
		}
	}

	/**
	 * todo doc
	 *
	 * @return SoapHeader
	 */
	private function buildAuthHeader() {
		$head = $this->credentials;

		$auth_params = array(
			'user' => $this->credentials['user'],
			'signature' => $this->credentials['signature'],
			'type' => 0

		);

		return new SoapHeader('http://dhl.de/webservice/cisbase', 'Authentification', $auth_params);
	}

	/*
	*function getVersion() {
	*	$this->buildClient();
	*	$this->log("Response: \n");
	*	$response = $this->client->getVersion(array('majorRelease' => '1', 'minorRelease' => '0'));
	*	$this->log($response);
	*}
	*/
}
