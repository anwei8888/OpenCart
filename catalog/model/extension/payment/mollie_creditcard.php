<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ModelExtensionPaymentMollieCreditcard extends ModelExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_CREDITCARD;
}
