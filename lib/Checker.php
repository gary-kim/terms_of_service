<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TermsOfService;

use OCA\TermsOfService\AppInfo\Application;
use OCA\TermsOfService\Db\Mapper\SignatoryMapper;
use OCA\TermsOfService\Db\Mapper\TermsMapper;
use OCP\IConfig;
use OCP\ISession;
use OCP\IUserManager;

class Checker {
	/** @var string */
	private $userId;
	/** @var IUserManager */
	private $userManager;
	/** @var ISession */
	private $session;
	/** @var SignatoryMapper */
	private $signatoryMapper;
	/** @var TermsMapper */
	private $termsMapper;
	/** @var CountryDetector */
	private $countryDetector;
	/** @var IConfig */
	private $config;

	public function __construct(
		$userId,
		IUserManager $userManager,
		ISession $session,
		SignatoryMapper $signatoryMapper,
		TermsMapper $termsMapper,
		CountryDetector $countryDetector,
		IConfig $config
	) {
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->session = $session;
		$this->signatoryMapper = $signatoryMapper;
		$this->termsMapper = $termsMapper;
		$this->countryDetector = $countryDetector;
		$this->config = $config;
	}

	/**
	 * Whether the currently logged-in user has signed the terms and conditions
	 * for the login action
	 *
	 * @return bool
	 */
	public function currentUserHasSigned(): bool {
		$uuid = $this->config->getAppValue(Application::APPNAME, 'term_uuid', '');
		if ($this->userId === null) {
			return ($this->session->get('term_uuid') === $uuid);
		} else if ($this->session->get('term_uuid') === $uuid) {
			return true;
		}

		$user = $this->userManager->get($this->userId);

		$countryCode = $this->countryDetector->getCountry();
		$terms = $this->termsMapper->getTermsForCountryCode($countryCode);
		if (empty($terms)) {
			// No terms that would need accepting
			return true;
		}

		$signatories = $this->signatoryMapper->getSignatoriesByUser($user);
		if (!empty($signatories)) {
			foreach($signatories as $signatory) {
				foreach($terms as $term) {
					if((int)$term->getId() === (int)$signatory->getTermsId()) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
