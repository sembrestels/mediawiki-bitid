<?php
/**
 * Implements Special:BitId
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

require_once dirname(__FILE__) . "/vendors/BitID.php";

/**
 * Implements Special:BitId
 *
 * @ingroup SpecialPage
 */
class SpecialBitId extends SpecialPage {
	function __construct() {
		parent::__construct('BitId');
	}
	function execute($par) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		
		$bitid = new BitID();
		$nonce = $bitid->generateNonce();

		$bitid_uri = Title::newFromText('Special:BitId')->getFullURL();
		$bitid_uri = $bitid->buildURI($bitid_uri, $nonce);
		
		$bitid_qr = $bitid->qrCode($bitid_uri);

		$this->main_view($output, $bitid_uri, $bitid_qr);
		$this->manual_view($output, $bitid_uri);
	}
	
	private function main_view($output, $bitid_uri, $bitid_qr) {
	
		$output->addHTML('<span id="qr-code">');
		
		$output->addWikiText(
"===Scan this QRcode with your BitID enabled mobile wallet.===
You can also click on the QRcode if you have a BitID enabled desktop wallet.");

		$output->addHTML(
"<a href=\"$bitid_uri\"><img alt=\"Click on QRcode to activate compatible desktop wallet\" border=\"0\" src=\"$bitid_qr\" /></a>");

		$output->addWikiText("No compatible wallet? Use [[Special:BitId#manual-signing | manual signing]].");
		
		$output->addHTML('</span>');
	}
	
	private function manual_view($output, $bitid_uri) {
		$output->addHTML('<span id="manual-signing" style="display:none">');
		
		$output->addWikiText(		
"===Manual signing===
The user experience is quite combersome, but it has the advangage of being compatible with all wallets including Bitcoin Core.

Please sign the challenge in the box below using the private key of this Bitcoin address you want to identify yourself with. Copy the text, open your wallet, choose your Bitcoin address, select the sign message function, paste the text into the message input and sign. After it is done, copy and paste the signature into the field below.

Cumbersome. Yep. Much better with a simple scan or click using a compatible wallet :)");

		$output->addWikiText("Back to [[Special:BitId#qr-code | QR code]].");
		
		$output->addHTML('</span>');
	}
}
