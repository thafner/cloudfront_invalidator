<?php

namespace Drupal\cloudfront_invalidator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManager;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\State\State;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Invalidate service.
 */
class CloudfrontInvalidator {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * the EntityTypeManager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $pathAlias;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager $queueWorker
   */
  protected $queueWorker;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   * @param \Drupal\path_alias\AliasManager $pathAlias
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   * @param \Drupal\Core\Queue\QueueWorkerManager $queueWorker
   * @param \Drupal\Core\State\State $state
   * @param \Drupal\Component\Datetime\TimeInterface $time
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $logger, EntityTypeManagerInterface $entityTypeManager, CurrentPathStack $currentPath, AliasManager $pathAlias, MessengerInterface $messenger, QueueFactory $queueFactory, QueueWorkerManager $queueWorker, State $state, TimeInterface $time) {
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentPath = $currentPath;
    $this->pathAlias = $pathAlias;
    $this->messenger = $messenger;
    $this->queueFactory = $queueFactory;
    $this->queueWorker = $queueWorker;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Create a CloudFront invalidation.
   *
   * @param array $paths
   *   Paths to invalidate in CloudFront.
   *
   * @return array
   *   Array of message result values.
   */
  public function invalidate(array $paths): array {
    $configValues = $this->configFactory->getEditable('cloudfront_invalidator.settings');
    $distribution_id = $configValues->get('cf_distribution_id');
    $access_key = $configValues->get('cf_access_key');
    $secret_key = $configValues->get('cf_secret_key');

    if (empty($distribution_id) || empty($access_key) || empty($secret_key)) {
      $message = [
        'status' => 'error',
        'message' => 'CloudFront Invalidation module is missing AWS Credentials.',
        'code' => '403',
      ];
    }
    else {
      $randomObj = new Random();
      $random = $randomObj->string();

      $cloudFrontClient = new CloudFrontClient([
        'version' => 'latest',
        'region' => 'us-east-2',
        'credentials' => [
          'key' => $access_key,
          'secret' => $secret_key,
        ],
      ]);

      $message = $this->createInvalidation($cloudFrontClient, $distribution_id, $random, $paths, $count);

      if ($configValues->get('debug_mode')) {
        $this->writeLogs($message);
      }
      if ($configValues->get('messenger_mode')) {
        $this->writeMessage($message);
      }
    }

    return $message;
  }

  /**
   * Invalidates a cached object in an Amazon CloudFront distribution.
   *
   * @param \Aws\CloudFront\CloudFrontClient $cloudFrontClient
   *   An initialized AWS SDK for PHP SDK client for CloudFront.
   * @param mixed $distributionId
   *   The distribution's ID.
   * @param string $callerReference
   *   Any value that uniquely identifies this request.
   * @param iterable $paths
   *   The list of paths to the cached objects you want to invalidate.
   * @param int $quantity
   *   The number of invalidation paths specified.
   *
   * @return array
   *   Information about the invalidation request.
   */
  private function createInvalidation(CloudFrontClient $cloudFrontClient, $distributionId, string $callerReference, iterable $paths, int $quantity): array {
    try {
      $result = $cloudFrontClient->createInvalidation([
        'DistributionId' => $distributionId,
        'InvalidationBatch' => [
          'CallerReference' => $callerReference,
          'Paths' => [
            'Items' => $paths,
            'Quantity' => $quantity,
          ],
        ],
      ]);

      $results = $result->toArray();
      $message = [
        'status' => 'normal',
        'message' => 'Invalidation created at ' . $results['@metadata']['headers']['date'],
        'invalidation_id' => $results["Invalidation"]["Id"],
        'code' => $results['@metadata']['statusCode'],
      ];

      return $message;
    }
    catch (AwsException $e) {
      $message = [
        'status' => 'error',
        'message' => $e->getAwsErrorMessage(),
        'code' => $e->getAwsErrorCode(),
      ];
    }
    return $message;
  }

  /**
   * Create an array of paths to invalidate.
   *
   * @param string $path_list
   *   String of paths to invalidate from textarea.
   *
   * @return array
   *   Array of paths to invalidate.
   */
  public function createPathList(string $path_list) {
    $paths = [];
    $lines = explode(PHP_EOL, trim($path_list));
    foreach ($lines as $line) {
      $trimmed_line = trim($line);
      if ($trimmed_line == '<front>') {
        $config = $this->configFactory->get('system.site');
        $front_uri = $config->get('page.front');
        $front_alias = $this->pathAlias->getAliasByPath($front_uri);
        $paths[] = $front_alias;
      }
      elseif (str_contains($trimmed_line, '*')) {
        $pre_wildcard = explode('*', $trimmed_line);
        $query = $this->entityTypeManager->getStorage('path_alias')->getQuery();
        $query->condition('alias', $pre_wildcard[0], 'STARTS_WITH');
        $entity_ids = $query->execute();
        $entities = $this->entityTypeManager->getStorage('path_alias')->loadMultiple($entity_ids);
        /** @var \Drupal\path_alias\Entity\PathAlias $entity */
        foreach ($entities as $entity) {
          $test = $entity->getAlias();
          $paths[] = $test;
        }
      }
      else {
        $paths[] = $trimmed_line;
      }
    }

    return $paths;
  }

  /**
   * Write Drupal messages.
   *
   * @param array $result
   *   Results from CloudFront invalidation creation.
   */
  public function writeMessage(array $result): void {
    if ($result['status'] == 'error') {
      $this->messenger->addError('There was an error invalidating the CloudFront cache. Status: ' . $result['code'] . 'Message: ' . $result['message']);
    }
    else {
      $this->messenger->addMessage('Invalidation successfully submitted to CloudFront. Status: ' . $result['code'] . ' ID: ' . $result['invalidation_id'] . 'Message: ' . $result['message']);
    }
  }

  /**
   * Log Drupal messages.
   *
   * @param array $result
   *   Results from CloudFront invalidation creation.
   */
  public function writeLogs(array $result): void {
    if ($result['status'] == 'error') {
      $this->logger->get('cloudfront_invalidator')
        ->error('There was an error invalidating the CloudFront cache.<br/>Status: @status_code <br/> @message', [
          '@status_code' => $result['code'],
          '@message' => $result['message'],
        ]);

    }
    else {
      $this->logger->get('cloudfront_invalidator')
        ->info('Invalidation successfully submitted to CloudFront. <br/>Status: @status_code <br/>ID: @id <br/> @message', [
          '@status_code' => $result['code'],
          '@message' => $result['message'],
          '@id' => $result['invalidation_id'],
        ]);
    }
  }

}
