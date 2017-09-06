<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ModelExtensionPaymentMollieGiftcard extends ModelExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_GIFTCARD;
}
