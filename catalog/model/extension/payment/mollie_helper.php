<?php

class MollieHelper
{
	// Plugin only for Opencart >= 3.0
	const PLUGIN_VERSION = "8.1.1";

	// All available modules. These should correspond to the Mollie_API_Object_Method constants.
	const MODULE_NAME_BANKTRANSFER  = "banktransfer";
	const MODULE_NAME_BELFIUS       = "belfius";
	const MODULE_NAME_BITCOIN       = "bitcoin";
	const MODULE_NAME_CREDITCARD    = "creditcard";
	const MODULE_NAME_DIRECTDEBIT   = "directdebit";
	const MODULE_NAME_IDEAL         = "ideal";
	const MODULE_NAME_MISTERCASH    = "mistercash";
	const MODULE_NAME_PAYPAL        = "paypal";
	const MODULE_NAME_PAYSAFECARD   = "paysafecard";
	const MODULE_NAME_SOFORT        = "sofort";
	const MODULE_NAME_KBC           = "kbc";
	const MODULE_NAME_GIFTCARD      = "giftcard";

	// List of all available module names.
	static public $MODULE_NAMES = array(
		self::MODULE_NAME_BANKTRANSFER,
		self::MODULE_NAME_BELFIUS,
		self::MODULE_NAME_BITCOIN,
		self::MODULE_NAME_CREDITCARD,
		self::MODULE_NAME_DIRECTDEBIT,
		self::MODULE_NAME_IDEAL,
		self::MODULE_NAME_MISTERCASH,
		self::MODULE_NAME_PAYPAL,
		self::MODULE_NAME_PAYSAFECARD,
		self::MODULE_NAME_SOFORT,
		self::MODULE_NAME_KBC,
		self::MODULE_NAME_GIFTCARD,
	);

	static protected $api_client;

	/**
	 * @return bool
	 */
	public static function apiClientFound()
	{
		return file_exists(realpath(DIR_SYSTEM . "/..") . "/catalog/controller/extension/payment/mollie-api-client/");
	}

	/**
	 * @param Config $config
	 * @return Mollie_API_Client
	 */
	public static function getAPIClient($config)
	{
		return self::getAPIClientForKey($config->get('payment_mollie_api_key'));
	}

	/**
	 * @param array $config
	 * @return Mollie_API_Client
	 */
	public static function getAPIClientAdmin($config)
	{
		return self::getAPIClientForKey(isset($config['payment_mollie_api_key']) ? $config['payment_mollie_api_key'] : null);
	}

	/**
	 * @param string $key
	 * @return Mollie_API_Client
	 */
	public static function getAPIClientForKey($key = null)
	{
		if (!self::$api_client && self::apiClientFound()) {
			require_once(realpath(DIR_SYSTEM . "/..") . "/catalog/controller/extension/payment/mollie-api-client/src/Mollie/API/Autoloader.php");

			$mollie = new Mollie_API_Client;

			$mollie->setApiKey(!empty($key) ? $key : null);

			$mollie->addVersionString("OpenCart/" . VERSION);
			$mollie->addVersionString("MollieOpenCart/" . self::PLUGIN_VERSION);

			self::$api_client = $mollie;
		}

		return self::$api_client;
	}
}
