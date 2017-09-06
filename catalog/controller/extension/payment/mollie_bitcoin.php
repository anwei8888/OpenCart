<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ControllerExtensionPaymentMollieBitcoin extends ControllerExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_BITCOIN;
}
