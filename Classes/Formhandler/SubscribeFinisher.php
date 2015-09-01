<?php
namespace Sto\Tellmatic\Formhandler;

/*                                                                        *
 * This script belongs to the TYPO3 extension "tellmatic".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Sto\Tellmatic\Tellmatic\Response\TellmaticResponse;
use Sto\Tellmatic\Utility\FormhandlerUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This finisher executes a Tellmatic subscribe request.
 * It uses the same field configuration as the DB finisher.
 */
class SubscribeFinisher extends \Tx_Formhandler_AbstractFinisher {

	/**
	 * Validates the submitted values using given settings
	 *
	 * @return array
	 */
	public function process() {

		try {
			$response = $this->getFormhandlerUtility()->sendSubscribeRequest($this->gp, $this->settings);
			if ($response->getSuccess()) {
			} else {
				$errors['tellmatic'] = $response->getFailureCode();
				$this->utilityFuncs->debugMessage('Exception during tellmatic request: ' . $response->getFailureReason());
			}
		} catch (\Exception $exception) {
			$errors['tellmatic'] = TellmaticResponse::FAILURE_CODE_UNKNOWN;
			$this->utilityFuncs->debugMessage('Exception during tellmatic request: ' . $exception->getMessage());
		}

		return $this->gp;
	}

	/**
	 * @return FormhandlerUtility
	 */
	protected function getFormhandlerUtility() {
		$formahandlerUtility = GeneralUtility::makeInstance(FormhandlerUtility::class);
		$formahandlerUtility->initialize($this);
		return $formahandlerUtility;
	}
}