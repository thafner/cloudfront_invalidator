<?php

namespace Drupal\cloudfront_invalidator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cloudfront_invalidator\CloudfrontInvalidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\path_alias\AliasManager;

/**
 * Configure CloudFront Invalidator settings for this site.
 */
class CloudfrontInvalidatorEntityForm extends ConfigFormBase {

  /**
   * @var \Drupal\cloudfront_invalidator\CloudfrontInvalidator
   */
  protected $invalidator;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
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
   * @param \Drupal\cloudfront_invalidator\CloudfrontInvalidator $invalidator
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   * @param \Drupal\path_alias\AliasManager $pathAlias
   */
  public function __construct(ConfigFactoryInterface $config_factory, CloudfrontInvalidator $invalidator, LoggerChannelFactoryInterface $logger, EntityTypeManager $entityTypeManager, CurrentPathStack $currentPath, AliasManager $pathAlias) {
    parent::__construct($config_factory);
    $this->invalidator = $invalidator;
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentPath = $currentPath;
    $this->pathAlias = $pathAlias;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('config.factory'),
      $container->get('cloudfront_invalidator.invalidate'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cloudfront_invalidator_cloudfront_invalidator_path';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cloudfront_invalidator.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $form['entity_types']['#tree'] = TRUE;
    foreach ($content_types as $type => $content_type) {
      $form['entity_types'][$type] = [
        '#type' => 'details',
        '#title' => $type,
      ];
      $form['entity_types'][$type]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#description' => $this->t('Check to invalidate an entity when that entity is created, updated, or deleted.'),
        '#default_value' => $this->configFactory->getEditable('cloudfront_invalidator.settings')->get($type . '_enabled'),
      ];
      $form['entity_types'][$type]['paths'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Invalidation paths'),
        '#default_value' => $this->configFactory->getEditable('cloudfront_invalidator.settings')->get($type . '_paths'),
        '#description' => $this->t('When an entity of this type is created, updated, or deleted, these paths (which can include wildcards) will be invalidated.<br/>Place one path per line and use the star (*) to represent wildcards and &lt;front&gt; to include the homepage.</br>Note: If there is a path alias for the entity, only include the aliased path here.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = $form_state->getValue('entity_types');
    foreach ($types as $type => $value) {
      $this->configFactory->getEditable('cloudfront_invalidator.settings')
        ->set(
          $type . '_enabled',
          $form_state->getValue(['entity_types', $type, 'enabled'])
        )
        ->set(
          $type . '_paths',
          $form_state->getValue(['entity_types', $type, 'paths'])
        )
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
