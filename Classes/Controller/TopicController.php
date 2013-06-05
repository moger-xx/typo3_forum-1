<?php

/*                                                                    - *
 *  COPYRIGHT NOTICE                                                    *
 *                                                                      *
 *  (c) 2012 Martin Helmich <m.helmich@mittwald.de>                     *
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
 * Controller for the Topic object. This controller implements topic-related
 * functions like displaying, creating or editing topics.
 *
 * @author     Martin Helmich <m.helmich@mittwald.de>
 * @package    MmForum
 * @subpackage Controller
 * @version    $Id$
 *
 * @copyright  2012 Martin Helmich <m.helmich@mittwald.de>
 *             Mittwald CM Service GmbH & Co. KG
 *             http://www.mittwald.de
 * @license    GNU Public License, version 2
 *             http://opensource.org/licenses/gpl-license.php
 */
class Tx_MmForum_Controller_TopicController extends Tx_MmForum_Controller_AbstractController {



	/*
	 * ATTRIBUTES
	 */



	/**
	 * The topic repository.
	 * @var Tx_MmForum_Domain_Repository_Forum_TopicRepository
	 */
	protected $topicRepository;


	/**
	 * The forum repository.
	 * @var Tx_MmForum_Domain_Repository_Forum_ForumRepository
	 */
	protected $forumRepository;


	/**
	 * The post repository.
	 * @var Tx_MmForum_Domain_Repository_Forum_PostRepository
	 */
	protected $postRepository;


	/**
	 * A factory class for creating topics.
	 * @var Tx_MmForum_Domain_Factory_Forum_TopicFactory
	 */
	protected $topicFactory;


	/**
	 * A factory class for creating posts.
	 * @var Tx_MmForum_Domain_Factory_Forum_PostFactory
	 */
	protected $postFactory;


	/**
	 * The criteria repository.
	 * @var Tx_MmForum_Domain_Repository_Forum_CriteriaRepository
	 */
	protected $criteraRepository;


	/**
	 * SessionHandling
	 * @var Tx_MmForum_Service_SessionHandlingService
	 */
	protected $sessionHandling;



	/*
	 * CONSTRUCTOR
	 */



	/**
	 * Constructor of this controller. Used primarily for dependency injection.
	 *
	 * @param Tx_MmForum_Domain_Repository_Forum_ForumRepository	$forumRepository
	 * @param Tx_MmForum_Domain_Repository_Forum_TopicRepository	$topicRepository
	 * @param Tx_MmForum_Domain_Repository_Forum_PostRepository		$postRepository
	 * @param Tx_MmForum_Domain_Factory_Forum_TopicFactory			$topicFactory
	 * @param Tx_MmForum_Domain_Factory_Forum_PostFactory			$postFactory
	 * @param Tx_MmForum_Domain_Repository_Forum_CriteriaRepository $criteraRepository
	 * @param Tx_MmForum_Service_SessionHandlingService             $sessionHandling
	 */
	public function __construct(Tx_MmForum_Domain_Repository_Forum_ForumRepository $forumRepository,
								Tx_MmForum_Domain_Repository_Forum_TopicRepository $topicRepository,
								Tx_MmForum_Domain_Repository_Forum_PostRepository $postRepository,
								Tx_MmForum_Domain_Factory_Forum_TopicFactory $topicFactory,
								Tx_MmForum_Domain_Factory_Forum_PostFactory $postFactory,
								Tx_MmForum_Domain_Repository_Forum_CriteriaRepository $criteraRepository = NULL,
								Tx_MmForum_Service_SessionHandlingService $sessionHandling) {
		parent::__construct();
		$this->forumRepository   = $forumRepository;
		$this->topicRepository   = $topicRepository;
		$this->postRepository    = $postRepository;
		$this->topicFactory      = $topicFactory;
		$this->postFactory       = $postFactory;
		$this->sessionHandling   = $sessionHandling;
		$this->criteraRepository = $criteraRepository;
	}



	/*
	 * ACTION METHODS
	 */


	/**
	 *  Listing Action.
	 * @return void
	 */
	public function listAction() {

		$showPaginate = FALSE;
		switch ($this->settings['listTopics']) {
			case '3':
				$dataset = $this->topicRepository->findQuestions(6);
				$partial = 'Topic/QuestionBox';
				break;
			case '4':
				$dataset = $this->topicRepository->findByFilter(6, array('postCount' => 'DESC'));
				$partial = 'Topic/ListBox';
				break;
			default:
				$dataset      = $this->topicRepository->findAll();
				$partial      = 'Topic/List';
				$showPaginate = TRUE;
				break;
		}
		$this->view->assign('showPaginate', $showPaginate);
		$this->view->assign('partial', $partial);
		$this->view->assign('topics', $dataset);
	}



	/**
	 * Show action. Displays a single topic and all posts contained in this topic.
	 *
	 * @param Tx_MmForum_Domain_Model_Forum_Topic $topic
	 *                                                         The topic that is to be displayed.
	 *
	 * @return void
	 */
	public function showAction(Tx_MmForum_Domain_Model_Forum_Topic $topic) {
		$posts = $this->postRepository->findForTopic($topic);
		// AdHandling Start
		$actDatetime = new DateTime();
		if (!$this->sessionHandling->get('adTime')) {
			$this->sessionHandling->set('adTime', $actDatetime);
			$adDateTime = $actDatetime;
		}
		else {
			$adDateTime = $this->sessionHandling->get('adTime');
		}
		if ($actDatetime->getTimestamp() - $adDateTime->getTimestamp() > $this->settings['ads']['timeInterval']) {
			$this->sessionHandling->set('adTime', $actDatetime);
			$this->view->assign('showAd', TRUE);
			$max = count($posts);
			if ($max > $this->settings['topicController']['show']['pagebrowser']['itemsPerPage']) {
				$max = $this->settings['topicController']['show']['pagebrowser']['itemsPerPage'];
			}
			$showAd = array('enabled' => TRUE, 'position' => mt_rand(1,$max-1));
			$this->view->assign('showAd', $showAd);
		}
		// AdHandling End
		$this->authenticationService->assertReadAuthorization($topic);
		$this->markTopicRead($topic);
		$this->view->assign('topic', $topic)->assign('posts', $posts);
	}



	/**
	 * New action. Displays a form for creating a new topic.
	 *
	 * @param Tx_MmForum_Domain_Model_Forum_Forum $forum
	 *                                                          The forum in which the new topic is to be created.
	 * @param Tx_MmForum_Domain_Model_Forum_Post  $post
	 *                                                          The first post of the new topic.
	 * @param string                              $subject      The subject of the new topic
	 *
	 * @dontvalidate $post
	 */
	public function newAction(Tx_MmForum_Domain_Model_Forum_Forum $forum,
							  Tx_MmForum_Domain_Model_Forum_Post $post = NULL, $subject = NULL) {
		$this->authenticationService->assertNewTopicAuthorization($forum);
		$this->view->assign('forum', $forum)->assign('post', $post)->assign('subject', $subject)
			->assign('currentUser', $this->frontendUserRepository->findCurrent())
			->assign('criteria', $forum->getCriteria());
	}



	/**
	 * Creates a new topic.
	 *
	 * @param Tx_MmForum_Domain_Model_Forum_Forum $forum       The forum in which the new topic is to be created.
	 * @param Tx_MmForum_Domain_Model_Forum_Post  $post        The first post of the new topic.
	 * @param string                              $subject     The subject of the new topic
	 * @param array                               $attachments File attachments for the post.
	 * @param int								  $question    The flag if the new topic is declared as question
	 * @param array								  $criteria    All submitted criteria with option.
	 *
	 * @validate $subject NotEmpty
	 */
	public function createAction(Tx_MmForum_Domain_Model_Forum_Forum $forum, Tx_MmForum_Domain_Model_Forum_Post $post,
								 $subject, array $attachments = array(), $question = 0, array $criteria = array()) {
		
		// Assert authorization
		$this->authenticationService->assertNewTopicAuthorization($forum);

		// Create the new post; add the new post to a new topic and add the new
		// topic to the forum. Then persist the forum object. Not as complicated
		// as is sounds, honestly!
		$this->postFactory->assignUserToPost($post);
		$topic = $this->topicFactory->createTopic($forum, $post, $subject, intval($question));

		// Notify potential listeners.
		$this->signalSlotDispatcher->dispatch('Tx_MmForum_Domain_Model_Forum_Topic', 'topicCreated',
											  array('topic' => $topic));

		// Redirect to single forum display view
		$this->controllerContext->getFlashMessageQueue()->addMessage(
			new \TYPO3\CMS\Core\Messaging\FlashMessage(
				Tx_MmForum_Utility_Localization::translate('Topic_Create_Success')
			)
		);
		$this->clearCacheForCurrentPage();
		$this->redirect('show', 'Forum', NULL, array('forum' => $forum));
	}



	/*
	 * HELPER METHODS
	 */



	/**
	 * Marks a topic as read by the current user.
	 *
	 * @param  Tx_MmForum_Domain_Model_Forum_Topic $topic
	 *                             The topic that is to be marked as read.
	 *
	 * @return void
	 */
	protected function markTopicRead(Tx_MmForum_Domain_Model_Forum_Topic $topic) {
		$currentUser = $this->getCurrentUser();
		if ($currentUser === NULL || $currentUser->isAnonymous()) {
			return;
		}
		else {
			$currentUser->addReadObject($topic);
			$this->frontendUserRepository->update($currentUser);
		}
	}



}
