<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/TimedMediaHandler',
		'../../extensions/Wikibase/lib',
		'../../extensions/Wikibase/repo',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/TimedMediaHandler',
		'../../extensions/Wikibase/lib',
		'../../extensions/Wikibase/repo',
	]
);

return $cfg;
