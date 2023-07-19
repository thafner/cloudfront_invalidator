<?php

namespace Drupal\cloudfront_invalidator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cloudfront_invalidator\CloudfrontInvalidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Configure CloudFront Invalidator settings for this site.
 */
class CloudfrontInvalidatorPathForm extends ConfigFormBase {

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
   * @param \Drupal\cloudfront_invalidator\CloudfrontInvalidator $invalidator
   *   The invalidator.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CloudfrontInvalidator $invalidator, LoggerChannelFactoryInterface $logger) {
    parent::__construct($config_factory);
    $this->invalidator = $invalidator;
    $this->logger = $logger;
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
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL to invalidate'),
      '#required' => TRUE,
      '#description' => $this->t('Enter a relative URL starting with <strong>forward slash (/).</strong>'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('url'))) {
      $form_state->setErrorByName('url', $this->t('The URL to invalidate cannot be empty.'));
    }
    else {
      $firstChar = substr($form_state->getValue('url'), 0, 1);
      if ($firstChar !== '/') {
        $form_state->setErrorByName('url', $this->t('The URL to invalidate must be a relative url starting with a forward slash (/) character.'));
      }

      $config = $this->configFactory->get('cloudfront_invalidator.settings');
      if (empty($config->get('cf_distribution_id')) || empty($config->get('cf_access_key')) || empty($config->get('cf_secret_key'))) {
        $form_state->setErrorByName('url', $this->t('CloudFront Invalidation module is missing AWS Credentials.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $path = $form_state->getValue('url');
    $this->invalidator->invalidate([$path]);

    parent::submitForm($form, $form_state);
  }

}
