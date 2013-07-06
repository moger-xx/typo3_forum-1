<?php
/*                                                                    - *
 *  COPYRIGHT NOTICE                                                    *
 *                                                                      *
 *  (c) 2013 Ruven Fehling <r.fehling@mittwald.de>                     *
 *           Mittwald CM Service GmbH & Co KG                           *
 *           All rights reserved                                        *
 *                                                                      *
 *  This script is part of the TYPO3 project. The TYPO3 project is      *
 *  free software; you can redistribute it and/or modify                *
 *  it under the terms of the GNU General Public License as published   *
 *  by the Free Software Foundation; either version 2 of the License,   *
 *  or (at your option) any later version.                              *
 *                                                                      *
 *  The GNU General Public License can be found at                      *
 *  http://www.gnu.org/copyleft/gpl.html.                               *
 *                                                                      *
 *  This script is distributed in the hope that it will be useful,      *
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of      *
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the       *
 *  GNU General Public License for more details.                        *
 *                                                                      *
 *  This copyright notice MUST APPEAR in all copies of the script!      *
 *                                                                      */

/**
 * Lorem Ipsum
 *
 * @author	Ruven Fehling <r.fehling@mittwald.de>
 * @package	TYPO3
 * @subpackage	mm_forum
 */
class tx_mmforum_scheduler_notification extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	/**
	 * @var string
	 */
	protected $forumPids;

	/**
	 * @var string
	 */
	protected $userPids;

	/**
	 * @var int
	 */
	protected $notificationPid;

	/**
	 * @var int
	 */
	protected $lastExecutedCron = 0;

	/**
	 * @return string
	 */
	public function getForumPids() {
		return $this->forumPids;
	}

	/**
	 * @return string
	 */
	public function getUserPids() {
		return $this->userPids;
	}

	/**
	 * @return int
	 */
	public function getNotificationPid() {
		return $this->notificationPid;
	}


	/**
	 * @return int
	 */
	public function getLastExecutedCron() {
		return intval($this->lastExecutedCron);
	}

	/**
	 * @param string $forumPids
	 */
	public function setForumPids($forumPids) {
		$this->forumPids = $forumPids;
	}

	/**
	 * @param string $userPids
	 */
	public function setUserPids($userPids) {
		$this->userPids = $userPids;
	}

	/**
	 * @param int $notificationPid
	 */
	public function setNotificationPid($notificationPid) {
		$this->notificationPid = $notificationPid;
	}

	/**
	 * @param int $lastExecutedCron
	 * @return void
	 */
	public function setLastExecutedCron($lastExecutedCron) {
		$this->lastExecutedCron = $lastExecutedCron;
	}


	/**
	 * @return bool
	 */
	public function execute() {
		if($this->getForumPids() == false || $this->getUserPids() == false) return false;

		$this->setLastExecutedCron(intval($this->findLastCronExecutionDate()));

		$query = 'SELECT t.uid
				  FROM tx_mmforum_domain_model_forum_topic AS t
				  INNER JOIN tx_mmforum_domain_model_forum_post AS p ON p.uid = t.last_post
				  WHERE t.pid IN ('.$this->getForumPids().') AND t.deleted=0
				  		AND p.crdate > '.$this->getLastExecutedCron().'
				  GROUP BY t.uid';

		$topicRes = $GLOBALS['TYPO3_DB']->sql_query($query);
		$executedOn = time();

		while($topicRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($topicRes)) {
			$involvedUser = $this->getUserInvolvedInTopic($topicRow['uid']);
			$query = 'SELECT uid, author
					  FROM tx_mmforum_domain_model_forum_post
					  WHERE topic='.intval($topicRow['uid']).' AND crdate > '.$this->getLastExecutedCron().'
					  	 	AND deleted=0 AND pid IN ('.$this->getForumPids().')';
			$postRes = $GLOBALS['TYPO3_DB']->sql_query($query);
			while($postRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($postRes)) {
				foreach($involvedUser AS $user) {
					if($user['author'] == $postRow['author']) continue;
					if($user['firstPostOfUser'] > $postRow['uid']) continue;

					$insert = array(
						'crdate'	=> $executedOn,
						'pid'		=> $this->getNotificationPid(),
						'feuser'	=> intval($user['author']),
						'post'		=> intval($postRow['uid']),
						'type'		=> 'Tx_MmForum_Domain_Model_Forum_Post',
						'user_read'	=> (($this->getLastExecutedCron() == 0)?1:0)

					);
					$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_mmforum_domain_model_user_notification',$insert);
				}
			}
		}
		return true;
	}


	/**
	 * Get the CrDate of the last inserted notification
	 * @return int
	 */
	private function findLastCronExecutionDate() {
		$query = 'SELECT crdate
				  FROM tx_mmforum_domain_model_user_notification
				  WHERE pid ='.$this->getNotificationPid().'
				  ORDER BY crdate DESC
				  LIMIT 1';
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return intval($row['crdate']);
	}

	/**
	 * Get all users who are involved in this topic
	 * @param int $topicUid
	 * @return array
	 */
	private function getUserInvolvedInTopic($topicUid) {
		$user = array();
		$query = 'SELECT author, uid
				  FROM tx_mmforum_domain_model_forum_post
				  WHERE pid IN ('.$this->getForumPids().') AND deleted=0 AND author > 0
				  		AND topic='.intval($topicUid).'
				  GROUP BY author';
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$user[] = array('author' => intval($row['author']), 'firstPostOfUser' => intval($row['uid']));
		}
		return $user;
	}


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mm_forum/Scheduler/class.tx_mmforum_scheduler_notification.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mm_forum/Scheduler/class.tx_mmforum_scheduler_notification.php']);
}