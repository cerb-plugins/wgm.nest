<?php
if(class_exists('Extension_PageMenuItem')):
class WgmNest_SetupMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgm.nest.setup.menu.item';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.nest::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmNest_SetupPageSection extends Extension_PageSection {
	const ID = 'wgm.nest.setup.section';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'nest');
		
		$params = array(
			'product_id' => DevblocksPlatform::getPluginSetting('wgm.nest','product_id',''),
			'product_secret' => DevblocksPlatform::getPluginSetting('wgm.nest','product_secret',''),
		);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.nest::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$product_id = DevblocksPlatform::importGPC($_REQUEST['product_id'],'string','');
			@$product_secret = DevblocksPlatform::importGPC($_REQUEST['product_secret'],'string','');
			
			if(empty($product_id) || empty($product_secret))
				throw new Exception("Both the 'Product ID' and 'Product Secret' are required.");
			
			DevblocksPlatform::setPluginSetting('wgm.nest','product_id', $product_id);
			DevblocksPlatform::setPluginSetting('wgm.nest','product_secret', $product_secret);
			
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
	
	private function _getAppKeys() {
		$product_id = DevblocksPlatform::getPluginSetting('wgm.nest','product_id','');
		$product_secret = DevblocksPlatform::getPluginSetting('wgm.nest','product_secret','');
		
		if(empty($product_id) || empty($product_secret))
			return false;
		
		return array(
			'key' => $product_id,
			'secret' => $product_secret,
		);
	}
	
	function renderPopup() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$_SESSION['oauth_view_id'] = $view_id;
		
		$url_writer = DevblocksPlatform::getUrlService();
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
		
		$url = sprintf("https://home.nest.com/login/oauth2?client_id=%s&state=STATE", $app_keys['key']);
		
		header('Location: ' . $url);
	}
	
	function oauthCallback() {
		@$state = DevblocksPlatform::importGPC($_REQUEST['state'], 'string', '');
		@$code = DevblocksPlatform::importGPC($_REQUEST['code'], 'string', '');
		
		$view_id = $_SESSION['oauth_view_id'];
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
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
		
		// [TODO] Verify the token with API
		
		$id = DAO_ConnectedAccount::create(array(
			DAO_ConnectedAccount::NAME => 'Nest',
			DAO_ConnectedAccount::EXTENSION_ID => ServiceProvider_Nest::ID,
			DAO_ConnectedAccount::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
			DAO_ConnectedAccount::OWNER_CONTEXT_ID => $active_worker->id,
		));
		
		DAO_ConnectedAccount::setAndEncryptParams($id, $params);
		
		if($view_id) {
			echo sprintf("<script>window.opener.genericAjaxGet('view%s', 'c=internal&a=viewRefresh&id=%s');</script>",
				rawurlencode($view_id),
				rawurlencode($view_id)
			);
			
			C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_CONNECTED_ACCOUNT, $id);
		}
		
		echo "<script>window.close();</script>";
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(!isset($credentials['access_token']))
			return false;
			
		$headers[] = sprintf('Authorization: Bearer %s', $credentials['access_token']);
		return true;
	}
}