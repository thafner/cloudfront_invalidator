<?php

namespace Drupal\cloudfront_invalidator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure CloudFront Invalidator settings for this site.
 */
class CloudfrontInvalidatorKeySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cloudfront_invalidator_settings';
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
    $form['cf_distribution_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CloudFront Distribution ID'),
      '#default_value' => $this->config('cloudfront_invalidator.settings')->get('cf_distribution_id'),
      '#required' => TRUE,
    ];
    $form['cf_access_key'] = [
      '#type' => 'password',
      '#title' => $this->t('CloudFront Access Key'),
      '#attributes' => [
        'value' => $this->config('cloudfront_invalidator.settings')->get('cf_access_key'),
      ],
      '#required' => TRUE,
    ];
    $form['cf_secret_key'] = [
      '#type' => 'password',
      '#title' => $this->t('CloudFront Secret Key'),
      '#attributes' => [
        'value' => $this->config('cloudfront_invalidator.settings')->get('cf_secret_key'),
      ],
      '#required' => TRUE,
    ];
    $form['messenger_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Messenger Mode'),
      '#description' => $this->t('When Messenger Mode is enabled, a message will be displayed after an successful CloudFront invalidation request.'),
      '#default_value' => $this->config('cloudfront_invalidator.settings')->get('messenger_mode'),
    ];
    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Mode'),
      '#description' => $this->t('When Debug Mode is enabled, successful CloudFront invalidations will be logged.'),
      '#default_value' => $this->config('cloudfront_invalidator.settings')->get('debug_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('cloudfront_invalidator.settings')
      ->set('cf_distribution_id', $form_state->getValue('cf_distribution_id'))
      ->set('cf_access_key', $form_state->getValue('cf_access_key'))
      ->set('cf_secret_key', $form_state->getValue('cf_secret_key'))
      ->set('messenger_mode', $form_state->getValue('messenger_mode'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
