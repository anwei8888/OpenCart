<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ControllerExtensionPaymentMolliePayPal extends ControllerExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_PAYPAL;
}
