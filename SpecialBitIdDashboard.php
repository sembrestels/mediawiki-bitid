<?php
/**
 * Implements Special:BitIdDashboard parameter settings and status information
 *
 * @ingroup SpecialPage
 * @ingroup Extensions
 * @author David Llop
 * @author Thomas Gries
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link http://www.mediawiki.org/wiki/Extension:BitId Documentation
 *
 */
class SpecialBitIdDashboard extends SpecialPage {

	/**
	 * Constructor - sets up the new special page
	 * required right: bitid-dashboard-access
	 */
	public function __construct() {
		parent::__construct( 'BitIdDashboard', 'bitid-dashboard-access' );
	}

	/**
	 * Different description will be shown on Special:SpecialPage depending on
	 * whether the user has the 'bitiddashboard' right or not.
	 */
	function getDescription() {
		global $wgUser;

		if ( $wgUser->isAllowed( 'bitid-dashboard-admin' ) ) {
				return wfMessage( 'bitid-dashboard-title-admin' )->text();
		} else {
				return wfMessage( 'bitid-dashboard-title' )->text() ;
		}
	}

	/**
	 * @param $string string
	 * @param $value string
	 * @return string
	 */
	function show( $string, $value ) {

		if  ( $value === null ) {
			$value = 'null';
		} elseif ( is_bool( $value ) ) {
			$value = wfBoolToStr( $value );
		} else {
			$value = htmlspecialchars( $value, ENT_QUOTES );
		}

		return Html::rawElement( 'tr',
			array(),
			Html::rawElement( 'td',
				array(),
				$string
			) .
			Html::rawElement( 'td',
				array(),
				$value
			)
		) . "\n";

	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	function execute( $par ) {

		global $wgOut, $wgUser,
			$wgBitIdAllowExistingAccountSelection, $wgBitIdAllowNewAccountname,
			$wgBitIdUseEmailAsNickname, $wgBitIdProposeUsernameFromSREG,
			$wgBitIdLoginOnly, $wgBitIdAllowServingBitIdUserAccounts;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$totalUsers = SiteStats::users();
		$BitIddistinctUsers = $this->getBitIdUsers( 'distinctusers' );
		$BitIdUsers = $this->getBitIdUsers();

		$this->setHeaders();
		$this->outputHeader();

		$wgOut->addWikiMsg( 'bitid-dashboard-introduction', 'http://www.mediawiki.org/wiki/Extension:BitId' );

		$wgOut->addHTML(
			Html::openElement( 'table',
				array(
					'style' => 'width:50%;',
					'class' => 'mw-bitiddashboard-table wikitable'
				)
			)
		);

		# Here we show some basic version infos. Retrieval of SVN revision number of BitId appears to be too difficult
		$out  = $this->show( 'BitId ' . wfMessage( 'version-software-version' )->text(), MEDIAWIKI_BITID_VERSION );
		$out .= $this->show( 'MediaWiki ' . wfMessage( 'version-software-version' )->text(), SpecialVersion::getVersion() );
		$out .= $this->show( '$wgBitIdLoginOnly', $wgBitIdLoginOnly );

		$out .= $this->show( wfMessage( 'statistics-users' )->parse(), $totalUsers );
		$out .= $this->show( wfMessage( 'bitid-dashboard-number-bitid-users' )->text(), $BitIddistinctUsers  );
		$out .= $this->show( wfMessage( 'bitid-dashboard-number-bitids-in-database' )->text(), $BitIdUsers );
		$out .= $this->show( wfMessage( 'bitid-dashboard-number-users-without-bitid' )->text(), $totalUsers - $BitIddistinctUsers );

		$wgOut->addHTML( $out . Html::closeElement( 'table' ) . "\n" );

	}

	function error() {
		global $wgOut;
		$args = func_get_args();
		$wgOut->wrapWikiMsg( "<p class='error'>$1</p>", $args );
	}


	function getBitIdUsers ( $distinctusers = '' ) {
		wfProfileIn( __METHOD__ );
		$distinct = ( $distinctusers == 'distinctusers' ) ? 'COUNT(DISTINCT uoi_user)' : 'COUNT(*)' ;

		$dbr = wfGetDB( DB_SLAVE );
		$BitIdUserCount = (int)$dbr->selectField(
			'bitid_users',
			$distinct,
			null,
			__METHOD__,
			null
		);
		wfProfileOut( __METHOD__ );
		return $BitIdUserCount;
	}

}
