<?php
/**
 * Copyright (c) 2012-2017, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @package     Mollie
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      Mollie B.V. <info@mollie.com>
 * @copyright   Mollie B.V.
 * @link        https://www.mollie.com
 *
 * @property Config                       $config
 * @property DB                           $db
 * @property Language                     $language
 * @property Loader                       $load
 * @property ModelSettingSetting          $model_setting_setting
 * @property ModelSettingStore            $model_setting_store
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property Request                      $request
 * @property Response                     $response
 * @property URL                          $url
 * @property User                         $user
 */
require_once(dirname(DIR_SYSTEM) . "/catalog/model/extension/payment/mollie_helper.php");

class ControllerExtensionPaymentMollie extends Controller
{
	protected $error = array();
	protected $data = array();

	/**
	 * This method is executed by OpenCart when the Payment module is installed from the admin. It will create the
	 * required tables.
	 *
	 * @return void
	 */
	public function install()
	{
		$this->load->model("setting/setting");

		$this->db->query("
CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mollie_payments` (
	`order_id` int(11) unsigned NOT NULL,
	`method` enum('idl') NOT NULL DEFAULT 'idl',
	`transaction_id` varchar(32) NOT NULL,
	`bank_account` varchar(15) NOT NULL,
	`bank_status` varchar(20) NOT NULL,
	PRIMARY KEY (`order_id`),
	UNIQUE KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		foreach ($this->getMultiStores() as $store) {
			$this->model_setting_setting->editSetting("payment_mollie", array(
				'payment_mollie_status' => true,
			), $store['id']);
		}

		$this->installAllModules();
	}

	/**
	 * The method is executed by OpenCart when the Payment module is uninstalled from the admin. It will not drop the Mollie
	 * table at this point - we want to allow the user to toggle payment modules without losing their settings.
	 *
	 * @return void
	 */
	public function uninstall ()
	{
		$this->uninstallAllModules();
	}

	/**
	 * Trigger installation of all Mollie modules.
	 */
	protected function installAllModules()
	{
		$this->load->model("setting/extension");
		$this->load->model('user/user_group');

		foreach (MollieHelper::$MODULE_NAMES as $module_name) {
			$this->model_setting_extension->install('payment', 'mollie_' . $module_name);

			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/payment/mollie_' . $module_name);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/payment/mollie_' . $module_name);
		}
	}

	/**
	 * Trigger removal of all Mollie modules.
	 */
	protected function uninstallAllModules()
	{
		$this->load->model("setting/extension");

		foreach (MollieHelper::$MODULE_NAMES as $module_name) {
			$this->model_setting_extension->uninstall('payment', 'mollie_' . $module_name);
		}
	}

	/**
	 * @param int $store The Store ID
	 * @return Mollie_API_Client
	 */
	protected function getAPIClient($store = 0)
	{
		return MollieHelper::getAPIClientAdmin($this->data['stores'][$store]);
	}

	/**
	 * Render the payment method's settings page.
	 */
	public function index ()
	{
		$this->load->language("extension/payment/mollie");
		$this->load->model("setting/setting");
		$this->load->model("setting/store");
		$this->load->model("localisation/order_status");
		$this->load->model("localisation/geo_zone");

		$this->document->setTitle($this->language->get("heading_title"));

		$shops = $this->getMultiStores();
		$this->retrieveMultiStoreConfigs();

		// Call validate method on POST

		$doRedirect = false;
		foreach($shops as $store)
		{
			if (($this->request->server['REQUEST_METHOD'] == "POST") && ($this->validate($store['id'])))
			{

				$post = $this->request->post['stores'][$store['id']];

				foreach (MollieHelper::$MODULE_NAMES as $module_name)
				{
					$status = "payment_mollie_" . $module_name . "_status";

					$post[$status] = (isset($post[$status]) && $post[$status] == "on") ? 1 : 0;
				}

				$post['payment_mollie_status'] = 1;

				$this->model_setting_setting->editSetting("payment_mollie", $post, $store['id']);

				// Migrate old settings if needed. We used to use "ideal" as setting group, but Opencart 2 requires us to use "mollie".
				$this->model_setting_setting->deleteSetting("ideal", $store['id']);

				$doRedirect = true;
			}
		}

		if ($doRedirect)
		{
			$this->session->data['success'] = $this->language->get("text_success");
			$this->redirect($this->url->link("marketplace/extension", "type=payment&user_token=" . $this->session->data['user_token'], "SSL"));
		}

		// Set data for template
		$data['api_check_url']          = $this->url->link('extension/payment/mollie/validate_api_key', "user_token=" . $this->session->data['user_token'], "SSL");
		$data['heading_title']          = $this->language->get("heading_title");
		$data['title_global_options']   = $this->language->get("title_global_options");
		$data['title_payment_status']   = $this->language->get("title_payment_status");
		$data['title_mod_about']        = $this->language->get("title_mod_about");
		$data['footer_text']            = $this->language->get("footer_text");

		$data['text_enabled']                 = $this->language->get("text_enabled");
		$data['text_disabled']                = $this->language->get("text_disabled");
		$data['text_yes']                     = $this->language->get("text_yes");
		$data['text_no']                      = $this->language->get("text_no");
		$data['text_none']                    = $this->language->get("text_none");
		$data['text_edit']                    = $this->language->get("text_edit");
		$data['text_missing_api_key']         = $this->language->get("text_missing_api_key");
		$data['text_activate_payment_method'] = $this->language->get("text_activate_payment_method");
		$data['text_no_status_id']            = $this->language->get("text_no_status_id");
		$data['text_all_zones']               = $this->language->get("text_all_zones");

		$data['entry_api_key']                  = $this->language->get("entry_api_key");
		$data['entry_description']              = $this->language->get("entry_description");
		$data['entry_show_icons']               = $this->language->get("entry_show_icons");
		$data['entry_show_order_canceled_page'] = $this->language->get("entry_show_order_canceled_page");
		$data['entry_status']                   = $this->language->get("entry_status");
		$data['entry_mod_status']               = $this->language->get("entry_mod_status");
		$data['entry_comm_status']              = $this->language->get("entry_comm_status");
		$data['entry_geo_zone']                 = $this->language->get("entry_geo_zone");

		$data['help_view_profile']              = $this->language->get("help_view_profile");
		$data['help_api_key']                   = $this->language->get("help_api_key");
		$data['help_description']               = $this->language->get("help_description");
		$data['help_show_icons']                = $this->language->get("help_show_icons");
		$data['help_show_order_canceled_page']  = $this->language->get("help_show_order_canceled_page");
		$data['help_status']                    = $this->language->get("help_status");

		$data['order_statuses']         = $this->model_localisation_order_status->getOrderStatuses();
		$data['entry_failed_status']    = $this->language->get("entry_failed_status");
		$data['entry_canceled_status']  = $this->language->get("entry_canceled_status");
		$data['entry_pending_status']   = $this->language->get("entry_pending_status");
		$data['entry_expired_status']   = $this->language->get("entry_expired_status");
		$data['entry_processing_status']= $this->language->get("entry_processing_status");
		$data['entry_processed_status'] = $this->language->get("entry_processed_status");

		$data['entry_payment_method']   = $this->language->get("entry_payment_method");
		$data['entry_activate']         = $this->language->get("entry_activate");
		$data['entry_sort_order']       = $this->language->get("entry_sort_order");
		$data['entry_support']          = $this->language->get("entry_support");
		$data['entry_mstatus']          = $this->checkModuleStatus();
		$data['entry_module']           = $this->language->get("entry_module");
		$data['entry_version']          = $this->language->get("entry_version") . " " . MollieHelper::PLUGIN_VERSION;

		$data['button_save']            = $this->language->get("button_save");
		$data['button_cancel']          = $this->language->get("button_cancel");

		$data['tab_general']            = $this->language->get("tab_general");

		$data['shops'] = $shops;

		// If there are errors, show the error.
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		foreach($shops as $store)
		{
			$data['stores'][$store['id']]['entry_cstatus'] = $this->checkCommunicationStatus(isset($store['payment_mollie_api_key']) ? $store['payment_mollie_api_key'] : null);

			if (isset($this->error[$store['id']]['api_key'])) {
				$data['stores'][$store['id']]['error_api_key'] = $this->error[$store['id']]['api_key'];
			} else {
				$data['stores'][$store['id']]['error_api_key'] = '';
			}

			if (isset($this->error[$store['id']]['description'])) {
				$data['stores'][$store['id']]['error_description'] = $this->error[$store['id']]['description'];
			} else {
				$data['stores'][$store['id']]['error_description'] = '';
			}

			if (isset($this->error[$store['id']]['show_icons'])) {
				$data['stores'][$store['id']]['error_show_icons'] = $this->error[$store['id']]['show_icons'];
			} else {
				$data['stores'][$store['id']]['error_show_icons'] = '';
			}

			if (isset($this->error[$store['id']]['show_order_canceled_page'])) {
				$data['stores'][$store['id']]['show_order_canceled_page'] = $this->error[$store['id']]['show_order_canceled_page'];
			} else {
				$data['stores'][$store['id']]['show_order_canceled_page'] = '';
			}

			if (isset($this->error[$store['id']]['total'])) {
				$data['stores'][$store['id']]['error_total'] = $this->error[$store['id']]['total'];
			} else {
				$data['stores'][$store['id']]['error_total'] = '';
			}
		}

		$data['error_file_missing'] = $this->language->get("error_file_missing");

		// Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			"href"      => $this->url->link("common/dashboard", "user_token=" . $this->session->data['user_token'], "SSL"),
			"text"      => $this->language->get("text_home"),
			"separator" => FALSE,
		);

		$data['breadcrumbs'][] = array(
			"href"      => $this->url->link("marketplace/extension", "type=payment&user_token=" . $this->session->data['user_token'], "SSL"),
			"text"      => $this->language->get("text_payment"),
			"separator" => ' :: ',
		);

		$data['breadcrumbs'][] = array(
			"href"      => $this->url->link("extension/payment/mollie", "user_token=" . $this->session->data['user_token'], "SSL"),
			"text"      => $this->language->get("heading_title"),
			"separator" => " :: ",
		);

		// Form action url
		$data['action'] = $this->url->link("extension/payment/mollie", "user_token=" . $this->session->data['user_token'], "SSL");
		$data['cancel'] = $this->url->link("marketplace/extension", "type=payment&user_token=" . $this->session->data['user_token'], "SSL");

		// Load global settings. Some are prefixed with mollie_ideal_ for legacy reasons.
		$settings = array(
			"payment_mollie_api_key"                    => NULL,
			"payment_mollie_ideal_description"          => "Order %",
			"payment_mollie_show_icons"                 => FALSE,
			"payment_mollie_show_order_canceled_page"   => TRUE,
			"payment_mollie_ideal_pending_status_id"    => 1,
			"payment_mollie_ideal_processing_status_id" => 2,
			"payment_mollie_ideal_canceled_status_id"   => 7,
			"payment_mollie_ideal_failed_status_id"     => 10,
			"payment_mollie_ideal_expired_status_id"    => 14,
		);

		foreach($shops as $store)
		{
			foreach ($settings as $setting_name => $default_value)
			{
				// Attempt to read from post
				if (isset($this->request->post['stores'][$store['id']][$setting_name]))
				{
					$data['stores'][$store['id']][$setting_name] = $this->request->post['stores'][$store['id']][$setting_name];
				}

				// Otherwise, attempt to get the setting from the database
				else
				{
					// same as $this->config->get()
					$stored_setting = isset($this->data['stores'][$store['id']][$setting_name]) ? $this->data['stores'][$store['id']][$setting_name] : null;

					if($stored_setting === NULL && $default_value !== NULL)
					{
						$data['stores'][$store['id']][$setting_name] = $default_value;
					}
					else
					{
						$data['stores'][$store['id']][$setting_name] = $stored_setting;
					}
				}
			}

			// Check which payment methods we can use with the current API key.
			$allowed_methods = array();

			try
			{
				$api_methods = $this->getAPIClient($store['id'])->methods->all();

				foreach ($api_methods as $api_method)
				{
					$allowed_methods[] = $api_method->id;
				}
			}
			catch (Mollie_API_Exception $e)
			{
				// If we have an unauthorized request, our API key is likely invalid.
				if ($data['stores'][$store['id']]['payment_mollie_api_key'] !== NULL && strpos($e->getMessage(), "Unauthorized request") >= 0)
				{
					$data['stores'][$store['id']]['error_api_key'] = $this->language->get("error_api_key_invalid");
				}
			}

			$data['stores'][$store['id']]['payment_methods'] = array();


			foreach (MollieHelper::$MODULE_NAMES as $module_name)
			{
				$payment_method = array();

				$payment_method['name']    = $this->language->get("name_mollie_" . $module_name);
				$payment_method['icon']    = "https://www.mollie.com/images/payscreen/methods/" . $module_name . ".png";
				$payment_method['allowed'] = in_array($module_name, $allowed_methods);

				// Load module specific settings.
				if (isset($this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_status']))
				{
					$payment_method['status'] = ($this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_status'] == "on");
				}
				else
				{
					$payment_method['status'] = (bool) isset($this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_status"]) ? $this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_status"] : null;
				}

				if (isset($this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_sort_order']))
				{
					$payment_method['sort_order'] = $this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_sort_order'];
				}
				else
				{
					$payment_method['sort_order'] = isset($this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_sort_order"]) ? $this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_sort_order"] : null;
				}

				if (isset($this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_geo_zone']))
				{
					$payment_method['geo_zone'] = $this->request->post['stores'][$store['id']]['payment_mollie_' . $module_name . '_geo_zone'];
				}
				else
				{
					$payment_method['geo_zone'] = isset($this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_geo_zone"]) ? $this->data['stores'][$store['id']]["payment_mollie_" . $module_name . "_geo_zone"] : null;
				}

				$data['stores'][$store['id']]['payment_methods'][$module_name] = $payment_method;
			}

		}

		$this->renderTemplate("extension/payment/mollie", $data, array(
			"header",
			"column_left",
			"footer",
		));
	}

	public function validate_api_key()
	{
		$json = array(
			'error' => false,
			'invalid' => false,
			'valid' => false,
			'message' => '',
		);

		if (empty($this->request->get['key'])) {
			$json['invalid'] = true;
			$json['message'] = 'API client not found.';
		} else {
			try {
				$client = MollieHelper::getAPIClientForKey($this->request->get['key']);

				if (!$client) {
					$json['invalid'] = true;
					$json['message'] = 'API client not found.';
				} else {
					$client->methods->all();

					$json['valid'] = true;
					$json['message'] = 'Ok.';
				}
			} catch (Mollie_API_Exception_IncompatiblePlatform $e) {
				$json['error'] = true;
				$json['message'] = $e->getMessage() . ' You can ask your hosting provider to help with this.';
			} catch (Mollie_API_Exception $e) {
				$json['error'] = true;
				$json['message'] = '<strong>Communicating with Mollie failed:</strong><br/>'
					. htmlspecialchars($e->getMessage())
					. '<br/><br/>'
					. 'Please check the following conditions. You can ask your hosting provider to help with this.'
					. '<ul>'
					. '<li>Make sure outside connections to ' . ($client ? htmlspecialchars($client->getApiEndpoint()) : 'Mollie') . ' are not blocked.</li>'
					. '<li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
					. '<li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
					. '</ul><br/>'
					. 'Contact <a href="mailto:info@mollie.nl">info@mollie.nl</a> if this still does not fix your problem.';
			}
		}


		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Check the post and check if the user has permission to edit the module settings
	 * @param int $store The store id
	 * @return bool
	 */
	private function validate ($store = 0)
	{
		if (!$this->user->hasPermission("modify", "extension/payment/mollie"))
		{
			$this->error['warning'] = $this->language->get("error_permission");
		}

		if (!$this->request->post['stores'][$store]['payment_mollie_api_key'])
		{
			$this->error[$store]['api_key'] = $this->language->get("error_api_key");
		}

		if (!$this->request->post['stores'][$store]['payment_mollie_ideal_description'])
		{
			$this->error[$store]['description'] = $this->language->get("error_description");
		}

		return (count($this->error) == 0);
	}

	protected function checkModuleStatus ()
	{
		$need_files = array();

		$mod_files  = array(
			DIR_APPLICATION . "controller/extension/payment/mollie.php",
			DIR_APPLICATION . "language/en-gb/extension/payment/mollie.php",
			DIR_TEMPLATE . "extension/payment/mollie.twig",
			DIR_CATALOG . "controller/extension/payment/mollie-api-client/",
			DIR_CATALOG . "controller/extension/payment/mollie.php",
			DIR_CATALOG . "model/extension/payment/mollie_helper.php",
			DIR_CATALOG . "model/extension/payment/mollie.php",
			DIR_CATALOG . "language/en-gb/extension/payment/mollie.php",
			DIR_CATALOG . "view/theme/default/template/extension/payment/mollie_checkout_form.twig",
			DIR_CATALOG . "view/theme/default/template/extension/payment/mollie_return.twig",
		);

		foreach (MollieHelper::$MODULE_NAMES as $module) {
			$mod_files[] = DIR_APPLICATION . 'controller/extension/payment/mollie_' . $module . '.php';
			$mod_files[] = DIR_APPLICATION . 'language/en-gb/extension/payment/mollie_' . $module . '.php';
			$mod_files[] = DIR_CATALOG . 'controller/extension/payment/mollie_' . $module . '.php';
			$mod_files[] = DIR_CATALOG . 'model/extension/payment/mollie_' . $module . '.php';
		}

		foreach ($mod_files as $file) {
			$realpath = realpath($file);

			if (!file_exists($realpath)) {
				$need_files[] = '<span class="text-danger">' . $file . '</span>';
			}
		}

		if (!MollieHelper::apiClientFound()) {
			$need_files[] = '<span class="text-danger">'
				. 'API client not found. Please make sure you have installed the module correctly. Use the download '
				. 'button on the <a href="https://github.com/mollie/OpenCart/releases/latest" target="_blank">release page</a>'
				. '</span>';
		}

		if (count($need_files) > 0) {
			return $need_files;
		}

		return '<span class="text-success">OK</span>';
	}

	/**
	 * @param string|null
	 * @return string
	 */
	protected function checkCommunicationStatus($api_key = null)
	{
		if (empty($api_key)) {
			return '<span style="color:red">No API key provided. Please insert your API key.</span>';
		}

		try {
			$client = MollieHelper::getAPIClientForKey($api_key);

			if (!$client) {
				return '<span style="color:red">API client not found.</span>';
			}

			$client->methods->all();

			return '<span style="color: green">OK</span>';
		} catch (Mollie_API_Exception_IncompatiblePlatform $e) {
			return '<span style="color:red">' . $e->getMessage() . ' You can ask your hosting provider to help with this.</span>';
		} catch (Mollie_API_Exception $e) {
			return '<span style="color:red">'
				. '<strong>Communicating with Mollie failed:</strong><br/>'
				. htmlspecialchars($e->getMessage())
				. '</span><br/><br/>'

				. 'Please check the following conditions. You can ask your hosting provider to help with this.'
				. '<ul>'
				. '<li>Make sure outside connections to ' . ($client ? htmlspecialchars($client->getApiEndpoint()) : 'Mollie') . ' are not blocked.</li>'
				. '<li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
				. '<li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
				. '</ul><br/>'

				. 'Contact <a href="mailto:info@mollie.nl">info@mollie.nl</a> if this still does not fix your problem.';
		}
	}

	/**
	 * Map template handling for different Opencart versions
	 *
	 * @param string $template
	 * @param array  $data
	 * @param array  $common_children
	 * @param bool   $echo
	 */
	protected function renderTemplate ($template, $data, $common_children = array(), $echo = TRUE)
	{
		foreach ($common_children as $child) {
			$data[$child] = $this->load->controller("common/" . $child);
		}

		$html = $this->load->view($template, $data);

		if ($echo) {
			return $this->response->setOutput($html);
		}

		return $html;
	}

	/**
	 * @param string $url
	 * @param int    $status
	 */
	protected function redirect ($url, $status = 302)
	{
		$this->response->redirect($url, $status);
	}

	/**
	 * Retrieve additional store id's from store table.
	 * Will not include default store. Only the additional stores. So we inject the default store here.
	 * @return array
	 */
	protected function getMultiStores()
	{
		$sql = $this->db->query(sprintf("SELECT store_id as id, name FROM %sstore", DB_PREFIX));
		$rows = $sql->rows;
		$default = array(
			array(
				'id' => 0,
				'name' => $this->config->get('config_name')
			)
		);
		$allStores = array_merge($default, $rows);

		return $allStores;
	}

	/**
	 * Retrieve mollie options according to multistore (default is store 0)
	 */
	protected function retrieveMultiStoreConfigs()
	{
		$shops = $this->getMultiStores();
		foreach($shops as $store)
		{
			$sql = $this->db->query(sprintf("SELECT * FROM %ssetting WHERE store_id = %s", DB_PREFIX, $store['id']));
			$rows = $sql->rows;
			$newArrray = array();
			foreach($rows as $setting)
			{
				$newArrray[$setting['key']] = $setting['value'];
			}
			$this->data['stores'][$store['id']] = $newArrray;
		}

	}
}
