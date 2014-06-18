<?php

/**
 * This file is part of the MediaWiki extension BitID.
 * Copyright (C) 2014 David Llop <sembrestels@riseup.net>
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * Extension:BitId is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Extension:BitId is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Extension:BitId.  If not, see <http://www.gnu.org/licenses/>.
 */

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'BitId',
	'version' => 0.1,
	'author' => 'David Llop',
	'url' => 'https://www.mediawiki.org/wiki/Extension:BitId',
	'descriptionmsg' => 'bitid-desc'
);

$wgBitIdLoginAnywhere = true;

$wgHooks['PersonalUrls'][] = 'BitIdHooks::onPersonalUrls';
$wgHooks['BeforePageDisplay'][] = 'BitIdHooks::onBeforePageDisplay';
$wgHooks['SpecialPage_initList'][] = 'BitIdHooks::onSpecialPage_initList';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'BitIdHooks::onLoadExtensionSchemaUpdates';
$wgHooks['DeleteAccount'][] = 'BitIdHooks::onDeleteAccount';
$wgHooks['MergeAccountFromTo'][] = 'BitIdHooks::onMergeAccountFromTo';
$wgHooks['GetPreferences'][] = 'BitIdHooks::onGetPreferences';

//$wgAutoloadClasses['ApiBitId'] = __DIR__ . '/ApiBitId.php';
//$wgAPIModules['bitid'] = 'ApiBitId';
$wgExtensionMessagesFiles['BitId'] = __DIR__ . '/BitId.i18n.php';
$wgAutoloadClasses['SpecialBitIdLogin'] = __DIR__ . '/SpecialBitIdLogin.php';
$wgAutoloadClasses['BitIdHooks'] = __DIR__ . '/BitId.hooks.php';
$wgSpecialPages['BitIdLogin'] = 'SpecialBitIdLogin';

$wgResourceModules['ext.bitid'] = array(
	'scripts' => array('js/bitid_hooks.js'),
	'styles' => array(),
	'messages' => array(),
	'dependencies' => array( 'mediawiki.api', 'mediawiki.Title' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'BitId'
);

class BitId {
	
	/**
	 * Find the user with the given bitid
	 *
	 * @param $user
	 * @return array return the registered BitID addresses and registration timestamps (if available)
	 */
	public static function getUserBitIdInformation( $user ) {
		$bitid_addrs_registration = array();

		if ( $user instanceof User && $user->getId() != 0 ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				array( 'bitid_users' ),
				array( 'uoi_bitid', 'uoi_user_registration' ),
				array( 'uoi_user' => $user->getId() ),
				__METHOD__
			);

			foreach ( $res as $row ) {
				$bitid_addrs_registration[] = $row;
			}
			$res->free();
		}
		return $bitid_addrs_registration;
	}

}