<?php
/**
 * Config schema for the ads module (namespace: modules.ads.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{arrayField, intField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Ads',

	'ADS_STICKY_ROTATE_SECONDS' => intField('Sticky ad rotate (s)', 45, 'Seconds between sticky ad rotations.'),
	'ADS_INLINE_EVERY_N_THREADS' => intField('Inline ad every N threads', 4, 'Insert an inline ad after every N threads.'),
	'ADS_INLINE_COUNT' => intField('Inline ads per row', 2, 'Number of ads shown side-by-side in each inline row (1-5).'),
	'ADS_POST_AD_EVERY_N_POSTS' => intField('Post ad every N posts', 15, 'Insert a post-style ad after every N reply posts within a thread.'),
	'ADS_SLOT_DIMENSIONS' => arrayField('Ad slot dimensions', [
  'top' => 
  array (
    'width' => 728,
    'height' => 90,
  ),
  'mobile' => 
  array (
    'width' => 300,
    'height' => 250,
  ),
  'above' => 
  array (
    'width' => 728,
    'height' => 150,
  ),
  'below' => 
  array (
    'width' => 728,
    'height' => 150,
  ),
  'inline' => 
  array (
    'width' => 728,
    'height' => 150,
  ),
  'post_ad' => 
  array (
    'width' => 300,
    'height' => 250,
  ),
], 'JSON object of slot name => {width, height}.'),
];
