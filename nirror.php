<?php
/*
 * Author  : Antoine Dubourg
 * Email   : tcheko@no-log.org
 * Licence : WTFPL 
 * 
 * For more information about Nirror, please visit http://www.nirror.com
 * 
 * The registering process in Nirror has been made to flow nicely:
 * 1. Clic 'Authenticate to Nirror Application' button
 * 2. Login to / Create your Nirror account
 * 3. Authorize 'Nirror for Prestashop' access
 * 4. Once OAuth access token is acquired, Nirror for Prestashop module 
 *   - tries to fetch a matching domain in the already registered Nirror sites
 *   - if previous step failed, it creates a Nirror site Id automatically via
 *     Nirror API calls.
 * 5. Confirm your Nirror account and profit from all its nice features. Enjoy! 
 */

if (!defined('_PS_VERSION_'))
	exit;

	require('OAuth2/Client.php');
	require('OAuth2/GrantType/IGrantType.php');
	require('OAuth2/GrantType/AuthorizationCode.php');

class Nirror extends Module
{

/*
 *			NIRROR CONST FOR API
*/

	const NIRROR_API_URI = 'https://api.nirror.com/v1/';
	const NIRROR_API_KEY = '54e5c7003a678cd01b77a1b6';
	const NIRROR_SECRET_KEY = 'tfd76Haux3KvFSQy5T2HupB1VGDngoTO6dzVKvsU0Ux31cmS';

/*
 * 			SETUP, INSTALL, UNINSTALL
*/

	public function __construct()
	{
		$this->name = 'nirror';
		$this->tab = 'analytics_stats';
		$this->version = '1.1';
		$this->author = 'Antoine Dubourg';
		$this->bootstrap = true;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 

		parent::__construct();

		$this->displayName = $this->l('Nirror');
		$this->description = $this->l('Record customer browsing activity. Watch in real time or replay the browsing activity later for analysis.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

		$this->nirror_checkContent();
		$this->context->smarty->assign('module_name', $this->name);
	}

	public function install()
	{
		if ( !parent::install() || !$this->registerHook('header') || !$this->nirror_createContent() )
		{
			return false;
		}
		return true;
	}

	public function uninstall()
	{
		if ( !parent::uninstall() || !$this->nirror_deleteContent() )
		{
			return false;
		}
		return true;
	}

/*
 * 			HEADER OUTPUT
 * 
 * Nirror application basically requires a handful lines of javasript 
 * embedded in the header of your shop.
*/

	public function hookHeader()
	{
		$tag = "";

		if ( Configuration::get('MODULE_NIRROR_ID') )
		{
			// Handling of banned IPs addresses
			if ( !in_array($_SERVER['REMOTE_ADDR'], explode("\n", Configuration::get('MODULE_NIRROR_BANNED_IP'))) )
			{
				$tag = "\n" . '<script type="text/javascript">' . "\n";

				/* 
				 * Shall we record form fields (password kind is *always* excluded)?
				 * Modifying smarty templates can be cumbersome. Let's use some jQuery trick instead.
				 * All form fields are excluded by default. If you requires some fine tuning, you'll 
				 * have to go the rough way: smarty template editing.
				 * Nirror application excludes form field recording when tagged with 
				 * date-ni="bind: false" attribute.
				*/
				if ( !Configuration::get('MODULE_NIRROR_RECORD_FIELDS') )
				{
					$tag .= '$(document).ready(function(){$(\'input\').attr(\'data-ni\', \'bind: false\');$(\'textarea\').attr(\'data-ni\', \'bind: false\');$(\'select\').attr(\'data-ni\', \'bind: false\');console.log($(\'input\').data());});';
				}

				// Here comes the Nirror javascript embedding.
				$tag .= '(function(i,s,o,g,r,a,m){i[\'NirrorObject\']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();i[r].scriptURL=g;a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,\'script\',\'https://static.nirror.com/client/nirrorclient.js\',\'Ni\');' . "\n";
				// Your Nirror Id for site recording.
				$tag .= 'Ni(\'site\', \'' .  Configuration::get('MODULE_NIRROR_ID')  . '\');' . "\n";

				/* 
				 * User tracking feature tags the Nirror recording/live support with customer email 
				 * and client Id registered in the shop context.
				 */
				if ( $this->context->customer->isLogged() && Configuration::get('MODULE_NIRROR_TRACE_LOGGED_USER') )
				{
					global $cookie;

					$tag .= "Ni('user', 'username', '" . $cookie->email . "');\n";
					$tag .= "Ni('user', 'cid', '" . $this->context->customer->id . "');\n";
				}
				$tag .= "\n</script>";
			}
		}
		return $tag;
	}

/*
 * 			SETTINGS
*/

	/*
	 * This function returns a button that triggers the OAuth2 process to get
	 * an access token to Nirror API.
	*/
	private function nirror_gettoken_renderForm($auth_url)
	{
		$content = '<form id="module_form" class="defaultForm form-horizontal" action="index.php?controller=AdminModules&amp;configure=nirror&amp;tab_module=analytics_stats&amp;module_name=nirror&amp;token=b35eccf99139844f43e34569c41cfa7e" method="post" enctype="multipart/form-data" novalidate>';
		$content .= '<input type="hidden" name="submitNirrorSite" value="1" />';
		$content .= '<div class="panel" id="fieldset_1">';
		$content .= '<div class="panel-heading">';
		$content .=	'<i class="icon-key"></i> ' . $this->l('Nirror Application Access');
		$content .= '</div>';
		$content .= '<div class="form-wrapper">';
		$content .= '<div class="form-group">';
		$content .= '<label class="control-label col-lg-3">';
		$content .= '';
		$content .= '</label>';
		$content .= '<div class="col-lg-9 ">';
		$content .= '<a href="' . $auth_url . '" class="btn btn-warning">' . $this->l('Authenticate to Nirror Application') . '</a>';
		$content .= '<p class="help-block">';
		$content .= $this->l('Nirror for Prestashop requires you to connect to the Nirror Application before proceeding to further settings.');
		$content .= '</p>';
		$content .= '</div>';
		$content .= '</div>';
		$content .= '</div>';
		$content .= '</div>';
		$content .= '</form>';
		return $content;
	}

	// This function returns the form for Nirror for Prestashop settings.
	private function nirror_renderForm()
	{
		$fields_form = array(
						'form' => array(
							'legend' => array(
									'title' => $this->l('Settings'),
									'icon' => 'icon-cogs'
							),
							'input' => array(
								array(
									'type' => 'switch',
									'label' => $this->l('Trace Customer'),
									'name' => 'MODULE_NIRROR_TRACE_LOGGED_USER',
									'desc' => $this->l('Tag event recording with customer ID and email. Works only when customer has logged in.'),
									'values' => array(
										array(
											'id' => 'active_on',
											'value' => 1,
											'label' => $this->l('Enabled')
										),
										array(
											'id' => 'active_off',
											'value' => 0,
											'label' => $this->l('Disabled')
										)
									)
								),
								array(
									'type' => 'switch',
									'label' => $this->l('Form Field Recording'),
									'name' => 'MODULE_NIRROR_RECORD_FIELDS',
									'desc' => $this->l('Record keyboard events in form fields.'),
									'values' => array(
										array(
											'id' => 'active_on',
											'value' => 1,
											'label' => $this->l('Enabled')
										),
										array(
											'id' => 'active_off',
											'value' => 0,
											'label' => $this->l('Disabled')
										)
									)
								),
								array(
									'type' => 'textarea',
									'label' => $this->l('Banned IP Addresses'),
									'name' => 'MODULE_NIRROR_BANNED_IP',
									'desc' => $this->l('Insert IP address on a new line. Banned IP addresses will not be recorded. Your current IP address is ') . $_SERVER['REMOTE_ADDR']
								)
							),
							'submit' => array('title' => $this->l('Save'))
						)
					);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitNirror';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => 
				array(
					'MODULE_NIRROR_TRACE_LOGGED_USER'	=> Configuration::get('MODULE_NIRROR_TRACE_LOGGED_USER'),
					'MODULE_NIRROR_BANNED_IP'			=> Configuration::get('MODULE_NIRROR_BANNED_IP'),
					'MODULE_NIRROR_RECORD_FIELDS'		=> Configuration::get('MODULE_NIRROR_RECORD_FIELDS'),
				),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);
		return $helper->generateForm(array($fields_form));
	}

/*
 *		FORM HANDLING 
*/

	public function getContent()
	{
		$top = "";
		$message = "";
		$shop_url = parse_url(Tools::getHttpHost(true));

		$redirect_uri = Tools::getHttpHost(true) . $this->unset_uri_var("code", $_SERVER['REQUEST_URI'] );

		$client = new OAuth2\Client(Nirror::NIRROR_API_KEY, Nirror::NIRROR_SECRET_KEY);
		$auth_url = $client->getAuthenticationUrl(Nirror::NIRROR_API_URI . 'oauth2/authorize', ($redirect_uri) );

		// By default, do not request an authorization.
		$display_getaccesstoken = false;

		/*
		 * This part of the module is triggered when the OAuth callback is requested.
		 * Nirror application redirects to $redirect_uri with a code argument added to the URI.
		*/
		if ( Tools::getValue('code') )
		{
			$params = array('code' => Tools::getValue('code'), 'redirect_uri' => $redirect_uri);
			$response = $client->getAccessToken(Nirror::NIRROR_API_URI . 'oauth2/token', 'authorization_code', $params);
 
			// Defaulting to error message
			$reply_code = 1;

			if ( isset($response['result']['access_token']) )
			{
				// Keep the access token for later usage in Prestashop configuration table.
				Configuration::updateValue('MODULE_NIRROR_OAUTH2_ACCESS_TOKEN', $response['result']['access_token']);

				// success message!
				$reply_code = 2;
			}

			// Reload the module without the code argument in the URI and pass the 
			header('Location: ' . $redirect_uri . '&msg=' . $reply_code);
			die();
		}

		/*
		 * Handle the message from previous if block.  
		 * Two cases to handle:
		 * - case 1, Nirror refused to provide an access token with the returned code
		 * - case 2, Nirror provided an access token
		 */
		switch ( Tools::getValue('msg') )
		{
			case '1':
				$top .= $this->displayError($this->l('Access to Nirror API was denied. Please retry.'));
			break;

			case '2':
				$top .= $this->displayConfirmation($this->l('You were granted access to Nirror API.'));
			break;
		}

		/*
		 * We have an access token. Let's try to do some Nirror API magic
		 * only if Nirror for Prestashop has no Nirror Id to use.
		 * Site acquisition can basically be triggered again by nulling the 
		 * MODULE_NIRROR_ID directly in database.
		 */
		if ( Configuration::get('MODULE_NIRROR_OAUTH2_ACCESS_TOKEN') && Configuration::get('MODULE_NIRROR_ID') == '' ) 
		{
			$client->setAccessToken(Configuration::get('MODULE_NIRROR_OAUTH2_ACCESS_TOKEN'));

			// Fetch sites from Nirror API. Using GET /sites
			$response = $client->fetch(Nirror::NIRROR_API_URI . 'sites');

			/*
			 * The API returns 200 (OK) if we are still granted access to the API with 
			 * the access token we have stored in configuration table under the  
			 * MODULE_NIRROR_OAUTH2_ACCESS_TOKEN key.
			 * If the access token is expired, a new grant is required.
			*/
			if ( $response['code'] == 200 )
			{
				// Defaulting to create a site.
				$create_site = true;
				$nirror_sites = $response['result'];

				foreach ( $nirror_sites as $site )
				{
					// Check for perfect match between Nirror site hostname and current shop hostname.
					if ( $site['hostname'] == $shop_url['host'] )
					{
						// Got a perfect match. 
						$top .= $this->displayConfirmation(sprintf($this->l('Nirror site (Id: %s) has a matching hostname. It will be used for this shop.'), $site['id'] ));
						Configuration::updateValue('MODULE_NIRROR_ID', $site['id']);
						$create_site = false;
					}
				}

				/*
				 * Not matching domain has been found in returned Nirror sites.
				 * Let's create a site.
				*/
				if ( $create_site )
				{
					/*
					 * We use POST /sites API here.
					 * Let's provide shop name and shop hostname to the API.
					*/
					$params = array(
						'name' => Configuration::get('PS_SHOP_NAME'),
						'hostname' => $shop_url['host']
					);

					// Fire!
					$response = $client->fetch(Nirror::NIRROR_API_URI . 'sites', $params, OAuth2\Client::HTTP_METHOD_POST, array(), OAuth2\Client::HTTP_FORM_CONTENT_TYPE_APPLICATION);

					if ( $response['code'] == 200 )
					{
						// All is rolling nice.
						$top .= $this->displayConfirmation(sprintf($this->l('A new Nirror site named \'%s\' has been automatically created. Module configuration is now complete.'), Configuration::get('PS_SHOP_NAME')));
						// Keep the Nirror site Id. Module is now basically configured. 
						Configuration::updateValue('MODULE_NIRROR_ID', $response['result']['site']['id']);
					}
					else
					{
						// Something went bad somewhere. Try to reboot your computer... 
						$top .= $this->displayError($this->l('Nirror site could not be created automatically. Please, reinstall plugin.'));
					}
				}
			}
			else
			{
				// An error occured while fetching the sites. We might need to refresh authorization.
				$top .= $this->displayError($this->l('Access to Nirror API has expired. Please renew your grant.'));
				$display_getaccesstoken = true;
			}
		}
		else
		{
			$display_getaccesstoken = true;
		}

		// Form submit handling
		if ( Tools::isSubmit('submitNirror') )
		{
			$top .= $this->nirror_saveContent();
		}

		// Display the authentication button for Nirror Access.
		if ( $display_getaccesstoken )
		{
			$message .= $this->nirror_gettoken_renderForm($auth_url);
		}
		else
		{
			// Access is ok. Let's display proper settings form.
			$message .= $this->nirror_renderForm();
		}
		return $top . $message;
	}

/*
 *		SOME HELPERS
*/

	// Little function for removing argument in URL.
	function unset_uri_var($variable, $uri)
	{   
		$parseUri = parse_url($uri);
		$arrayUri = array();
		parse_str($parseUri['query'], $arrayUri);
		unset($arrayUri[$variable]);
		$newUri = http_build_query($arrayUri);
		$newUri = $parseUri['path'].'?'.$newUri;
		return $newUri;
	}

	// Leave the Prestashop configuration table as clean as possible.
	private function nirror_deleteContent()
	{
		if ( !Configuration::deleteByName('MODULE_NIRROR_ID') ||
			 !Configuration::deleteByName('MODULE_NIRROR_TRACE_LOGGED_USER') ||
			 !Configuration::deleteByName('MODULE_NIRROR_BANNED_IP') ||
			 !Configuration::deleteByName('MODULE_NIRROR_RECORD_FIELDS') ||
			 !Configuration::deleteByName('MODULE_NIRROR_OAUTH2_ACCESS_TOKEN')
			)
		{
			return false;
		}
		return true;
	}

	/*
	 * Checks if Nirror for Prestashop module requires some configuration.
	 * Display a message in the module manager if required.
	*/
	private function nirror_checkContent()
	{
		if ( !Configuration::get('MODULE_NIRROR_ID') )
		{
			$this->warning = $this->l('This module requires configuration.');
		}
	}

	// Empty placeholder at installation.
	private function nirror_createContent()
	{
		if ( !Configuration::updateValue('MODULE_NIRROR_ID', '') )
		{
			return false;
		}
		return true;
	}

	// Sanitize and save Nirror for Prestashop configuration
	private function nirror_saveContent()
	{
		// Defaulting to error message.
		$message = $this->displayError($this->l('There was an error while saving the settings.'));

		// Filter the crap. Remove all invalid IPs from the list.
		$ips = preg_split('/\r\n|\r|\n/', Tools::getValue('MODULE_NIRROR_BANNED_IP'));
		$iplist = "";
		foreach ( $ips as $ip )
		{
			// sanitize trailing spaces
			$ip = preg_replace('/\s+/', '', $ip);
			if ( filter_var($ip, FILTER_VALIDATE_IP) ) $iplist .= $ip . "\n";
		}

		if (
			Configuration::updateValue('MODULE_NIRROR_TRACE_LOGGED_USER', Tools::getValue('MODULE_NIRROR_TRACE_LOGGED_USER')) &&
			Configuration::updateValue('MODULE_NIRROR_BANNED_IP', $iplist) &&
			Configuration::updateValue('MODULE_NIRROR_RECORD_FIELDS', Tools::getValue('MODULE_NIRROR_RECORD_FIELDS'))
		)
		{
			$message = $this->displayConfirmation($this->l('Settings have been saved.'));
		}

		return $message;
	}
}
?>
