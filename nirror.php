<?php
/*
 *  Author  : Antoine Dubourg
 *  Email   : tcheko@no-log.org
 *  Licence : WTFPL 
 * 
 *  For more information about Nirror, please visit http://www.nirror.com
 */

if (!defined('_PS_VERSION_'))
	exit;

class Nirror extends Module
{

/*
 * 			SETUP, INSTALL, UNINSTALL
*/

	public function __construct()
	{
		$this->name = 'nirror';
		$this->tab = 'analytics_stats';
		$this->version = '1.0';
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
*/

	private function nirror_getTag()
	{
		global $cookie;

		$tag = "";

		// Handling of banned IPs
		if( !in_array($_SERVER['REMOTE_ADDR'], explode("\n", Configuration::get('MODULE_NIRROR_BANNED_IP'))) )
		{
			$tag = "\n" . '<script type="text/javascript">' . "\n";
			$tag .= '(function(i,s,o,g,r,a,m){i[\'NirrorObject\']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();i[r].scriptURL=g;a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,\'script\',\'https://static.nirror.com/client/nirrorclient.js\',\'Ni\');' . "\n";
			$tag .= 'Ni(\'site\', \'' .  Configuration::get('MODULE_NIRROR_ID')  . '\');' . "\n";
		
			// User tracking
			if ( $this->context->customer->isLogged() && Configuration::get('MODULE_NIRROR_TRACE_LOGGED_USER') )
			{
				$tag .= "Ni('user', 'username', '" . $cookie->email . "');\n";
				$tag .= "Ni('user', 'cid', '" . $this->context->customer->id . "');\n";
			}

			$tag .= "\n</script>";
		}
		return $tag;
	}

	public function hookHeader()
	{
		if ( Configuration::get('MODULE_NIRROR_ID') )
		{
			return $this->nirror_getTag();
		}
	}

/*
 * 			SETTINGS
*/

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
									'type' => 'text',
									'label' => $this->l('Nirror ID'),
									'name' => 'MODULE_NIRROR_ID',
									'class' => 'fixed-width-xl',
									'desc' => $this->l('Paste the Nirror ID for this site.'),
								),
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
			'fields_value' => $this->nirror_getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);
		return $helper->generateForm(array($fields_form));
	}

	private function nirror_getConfigFieldsValues()
	{
		return array(
			'MODULE_NIRROR_ID'					=> Configuration::get('MODULE_NIRROR_ID'),
			'MODULE_NIRROR_TRACE_LOGGED_USER'	=> Configuration::get('MODULE_NIRROR_TRACE_LOGGED_USER'),
			'MODULE_NIRROR_BANNED_IP'			=> Configuration::get('MODULE_NIRROR_BANNED_IP')
		);
	}

/*
 *		FORM HANDLING 
*/

	public function getContent()
	{
		$message = "";
		// Form submit handling
		if ( Tools::isSubmit('submitNirror') )
		{
			$message = $this->nirror_saveContent();
		}
		return $message . $this->nirror_renderForm();
	}

/*
 *		SOME HELPERS
*/
	private function nirror_deleteContent()
	{
		if ( !Configuration::deleteByName('MODULE_NIRROR_ID') ||
			 !Configuration::deleteByName('MODULE_NIRROR_TRACE_LOGGED_USER') ||
			 !Configuration::deleteByName('MODULE_NIRROR_BANNED_IP')
			)
		{
			return false;
		}
		return true;
	}

	private function nirror_checkContent()
	{
		if ( !Configuration::get('MODULE_NIRROR_ID') )
		{
			$this->warning = $this->l('This module requires configuration.');
		}
	}

	private function nirror_createContent()
	{
		if ( !Configuration::updateValue('MODULE_NIRROR_ID', '') )
		{
			return false;
		}
		return true;
	}

	private function nirror_saveContent()
	{
		$message = '';

		// Filter the crap. Remove all invalid IPs from the list.
		$ips = preg_split('/\r\n|\r|\n/', Tools::getValue('MODULE_NIRROR_BANNED_IP'));
		$iplist = "";
		foreach($ips as $ip)
		{
			// sanitize trailing spaces
			$ip = preg_replace('/\s+/', '', $ip);
			if(filter_var($ip, FILTER_VALIDATE_IP)) $iplist .= $ip . "\n";
		}

		// Some sanitization to avoid dumb error due to space chars
		$nirrorid = preg_replace('/\s+/', '', Tools::getValue('MODULE_NIRROR_ID'));

		// Nirror ID length is 24 and shall be hexadecimal 
		if(ctype_xdigit($nirrorid) && strlen($nirrorid) == 24)
		{
			if (Configuration::updateValue('MODULE_NIRROR_ID', $nirrorid) && 
				Configuration::updateValue('MODULE_NIRROR_TRACE_LOGGED_USER', Tools::getValue('MODULE_NIRROR_TRACE_LOGGED_USER')) &&
				Configuration::updateValue('MODULE_NIRROR_BANNED_IP', $iplist))
			{
				$message = $this->displayConfirmation($this->l('Settings have been saved.'));
			}
			else
			{
				$message = $this->displayError($this->l('There was an error while saving the settings.'));
			}
		}
		else
		{
			$message = $this->displayError($this->l('Nirror ID isn\'t valid. Please provide a valid Nirror ID.'));
		}
		return $message;
	}
}

?>
