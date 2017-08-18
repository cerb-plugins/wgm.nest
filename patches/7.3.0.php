<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$settings = DevblocksPlatform::services()->pluginSettings();
$tables = $db->metaTables();

$consumer_key = $settings->get('wgm.nest', 'product_id', null);
$consumer_secret = $settings->get('wgm.nest', 'product_secret', null);

if(!is_null($consumer_key) || !is_null($consumer_secret)) {
	$credentials = [
		'product_id' => $consumer_key,
		'product_secret' => $consumer_secret,
	];
	
	$settings->set('wgm.nest', 'credentials', $credentials, true, true);
	$settings->delete('wgm.nest', ['product_id','product_secret']);
}

return TRUE;