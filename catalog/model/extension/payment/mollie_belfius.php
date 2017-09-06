<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ModelExtensionPaymentMollieBelfius extends ModelExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_BELFIUS;
}
