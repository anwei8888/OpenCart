<?php
require_once(dirname(__FILE__) . "/mollie.php");

class ControllerExtensionPaymentMollieBankTransfer extends ControllerExtensionPaymentMollie
{
	const MODULE_NAME = MollieHelper::MODULE_NAME_BANKTRANSFER;

	/**
	 * Bank transfers can't be cancelled. They need 'pending' as an initial order status.
	 *
	 * @return bool
	 */
	protected function startAsPending()
	{
		return true;
	}
}
