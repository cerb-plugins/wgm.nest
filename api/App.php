<?php
if(class_exists('Extension_PageMenuItem')):
class WgmNest_SetupMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgm.nest.setup.menu.item';
	
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.nest::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmNest_SetupPageSection extends Extension_PageSection {
	const ID = 'wgm.nest.setup.section';
	
	function render() {
		$tpl = DevblocksPlatform::services()->template();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'nest');
		
		$credentials = DevblocksPlatform::getPluginSetting('wgm.nest','credentials',false,true,true);
		$tpl->assign('credentials', $credentials);
		
		$tpl->display('devblocks:wgm.nest::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$product_id = DevblocksPlatform::importGPC($_REQUEST['product_id'],'string','');
			@$product_secret = DevblocksPlatform::importGPC($_REQUEST['product_secret'],'string','');
			
			if(empty($product_id) || empty($product_secret))
				throw new Exception("Both the 'Product ID' and 'Product Secret' are required.");
			
			$credentials = [
				'product_id' => $product_id,
				'product_secret' => $product_secret,
			];
			DevblocksPlatform::setPluginSetting('wgm.nest','credentials', $credentials, true, true);
			
			echo json_encode(array('status'=>true,'message'=>'Saved!'));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
};
endif;

class ServiceProvider_Nest extends Extension_ServiceProvider implements IServiceProvider_HttpRequestSigner, IServiceProvider_OAuth {
	const ID = 'wgm.nest.service.provider';

	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl->assign('account', $account);
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.nest::provider/nest.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
		
		$active_worker = CerberusApplication::getActiveWorker();
		$encrypt = DevblocksPlatform::services()->encryption();
		
		// Decrypt OAuth params
		if(isset($edit_params['params_json'])) {
			if(false == ($outh_params_json = $encrypt->decrypt($edit_params['params_json'])))
				return "The connected account authentication is invalid.";
				
			if(false == ($oauth_params = json_decode($outh_params_json, true)))
				return "The connected account authentication is malformed.";
			
			if(is_array($oauth_params))
			foreach($oauth_params as $k => $v)
				$params[$k] = $v;
		}
		
		return true;
	}
	
	private function _getAppKeys() {
		if(false == ($credentials = DevblocksPlatform::getPluginSetting('wgm.nest','credentials',false,true,true)))
			return false;
		
		@$product_id = $credentials['product_id'];
		@$product_secret = $credentials['product_secret'];
		
		if(empty($product_id) || empty($product_secret))
			return false;
		
		return array(
			'key' => $product_id,
			'secret' => $product_secret,
		);
	}
	
	function oauthRender() {
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		@$form_id = DevblocksPlatform::importGPC($_REQUEST['form_id'], 'string', '');
		
		// Store the $form_id in the session
		$_SESSION['oauth_form_id'] = $form_id;
		
		$url_writer = DevblocksPlatform::services()->url();
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
		
		$url = sprintf("https://home.nest.com/login/oauth2?client_id=%s&state=STATE", $app_keys['key']);
		
		header('Location: ' . $url);
	}
	
	function oauthCallback() {
		@$state = DevblocksPlatform::importGPC($_REQUEST['state'], 'string', '');
		@$code = DevblocksPlatform::importGPC($_REQUEST['code'], 'string', '');
		
		$form_id = $_SESSION['oauth_form_id'];
		unset($_SESSION['oauth_form_id']);
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$active_worker = CerberusApplication::getActiveWorker();
		$encrypt = DevblocksPlatform::services()->encryption();
		
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
		$oauth->setTokens($code);
		
		$access_token_url = sprintf("https://api.home.nest.com/oauth2/access_token?client_id=%s&code=%s&client_secret=%s&grant_type=authorization_code",
			$app_keys['key'],
			$code,
			$app_keys['secret']
		);
		
		$params = $oauth->getAccessToken($access_token_url, array(), array());

		if(!is_array($params) || !isset($params['access_token']))
			return false;
			
		$oauth->setTokens($params['access_token']);
		
		// Output
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('form_id', $form_id);
		$tpl->assign('label', 'Nest');
		$tpl->assign('params_json', $encrypt->encrypt(json_encode($params)));
		$tpl->display('devblocks:cerberusweb.core::internal/connected_account/oauth_callback.tpl');
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(!isset($credentials['access_token']))
			return false;
			
		$headers[] = sprintf('Authorization: Bearer %s', $credentials['access_token']);
		return true;
	}
}