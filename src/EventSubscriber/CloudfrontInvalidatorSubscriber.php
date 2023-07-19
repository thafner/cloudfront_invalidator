<?php

namespace Drupal\cloudfront_invalidator\EventSubscriber;

use Drupal\cloudfront_invalidator\Event\EntityCreateEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\cloudfront_invalidator\Event\EntityUpdateEvent;
use Drupal\cloudfront_invalidator\Event\EntityDeleteEvent;
use Drupal\cloudfront_invalidator\CloudfrontInvalidator;
use Drupal\Core\Entity\EntityInterface;
use Drupal\path_alias\AliasManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * CloudFront Invalidator event subscriber.
 */
class cloudfrontCloudfrontInvalidatorSubscriber implements EventSubscriberInterface {

  /**
   * Cloudfront invalidator.
   *
   * @var \Drupal\cloudfront_invalidator\CloudfrontInvalidator;
   */
  protected $cloudfrontInvalidator;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $pathAlias;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\cloudfront_invalidator\CloudfrontInvalidator $cloudfrontInvalidator
   *   The invalidator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\path_alias\AliasManager $pathAlias
   *   The path alias manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   */
  public function __construct(CloudfrontInvalidator $cloudfrontInvalidator, ConfigFactoryInterface $configFactory, AliasManager $pathAlias, LoggerChannelFactoryInterface $logger) {
    $this->cloudfrontInvalidator = $cloudfrontInvalidator;
    $this->configFactory = $configFactory;
    $this->pathAlias = $pathAlias;
    $this->logger = $logger;
  }

  /**
   * Subscribe to the Entity update and delete events.
   *
   * @param EntityUpdateEvent|EntityCreateEvent|EntityDeleteEvent $event
   *   Custom Entity update event.
   */
  public function onEntityUpdate(EntityUpdateEvent|EntityCreateEvent|EntityDeleteEvent $event) {
    $config = $this->configFactory->get('cloudfront_invalidator.settings');
    if (!empty($config->get('cf_distribution_id')) && !empty($config->get('cf_access_key')) && !empty($config->get('cf_secret_key'))) {
      $entity = $event->entity;
      $base_type = $entity->getEntityTypeId();
      if ($base_type == 'node') {
        $type = $entity->getType();
        if ($config->get($type . '_enabled')) {
          $paths = $this->getPathInvalidationList($entity);
          if (!empty($config->get($type . '_paths'))) {
            $additional_paths = $this->cloudfrontInvalidator->createPathList($config->get($type . '_paths'));
            $paths = array_merge($paths, $additional_paths);
          }
          $result = $this->cloudfrontInvalidator->invalidate($paths);
        }
      }
      else {
        $paths = $this->getPathInvalidationList($entity);
        $result = $this->cloudfrontInvalidator->invalidate($paths);
      }
      if ($config->get('messenger_mode')) {
        $this->cloudfrontInvalidator->writeMessage($result);
      }

      if ($config->get('debug_mode')) {
        $this->cloudfrontInvalidator->writeLogs($result);
      }
    }
    else {
      $this->logger->get('cloudfront_invalidator')->warning("AWS CloudFront credentials are not present in the CloudFront Invalidator module.");
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityCreateEvent::CREATE => ['onEntityUpdate'],
      EntityUpdateEvent::UPDATE => ['onEntityUpdate'],
      EntityDeleteEvent::DELETE => ['onEntityUpdate'],
    ];
  }

  /**
   * Generate list of paths to invalidate.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity that is being updated.
   *
   * @return array
   *   Paths to invalidate.
   */
  private function getPathInvalidationList(EntityInterface $entity): array {
    $id = $entity->id();
    $type = $entity->getEntityTypeId();
    $value = '/' . $type . '/' . $id;
    $paths = [$value];
    $alias = $this->pathAlias->getAliasByPath($value);
    if (!empty($alias) && $alias != $value) {
      $paths[] = $alias;
    }
    return $paths;
  }

}
