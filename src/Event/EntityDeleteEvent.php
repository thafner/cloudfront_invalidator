<?php

namespace Drupal\cloudfront_invalidator\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Event that is fired when an entity is updated.
 */
class EntityDeleteEvent extends Event {

  const DELETE = 'entity_event_delete';

  /**
   * The updated entity object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  public $entity;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The updated entity.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

}
