<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ControllerExtensionPaymentMollieKbc extends ControllerExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_KBC;
}
