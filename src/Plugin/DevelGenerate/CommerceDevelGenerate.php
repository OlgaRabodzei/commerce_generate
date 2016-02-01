<?php
/**
 * @file
 * Contains \Drupal\commerce_generate\Plugin\DevelGenerate\CommerceDevelGenerate.
 */

namespace Drupal\commerce_generate\Plugin\DevelGenerate;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Provides a CommerceDevelGenerate plugin.
 *
 * @package Drupal\commerce_generate\Plugin\DevelGenerate
 *
 * @DevelGenerate(
 *   id = "commerce",
 *   label = @Translation("commerce"),
 *   description = @Translation("Generate a given number of commerce products. Optionally delete current products."),
 *   url = "products",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 50,
 *     "kill" = FALSE
 *   }
 * )
 */
class CommerceDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * The product type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productTypeStorage;

  /**
   * The product variation storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $variationStorage;

  /**
   * The product variation type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $variationTypeStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              EntityStorageInterface $product_storage, EntityStorageInterface $product_type_storage,
                              EntityStorageInterface $variation_storage, EntityStorageInterface $variation_type_storage,
                              LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->productStorage = $product_storage;
    $this->productTypeStorage = $product_type_storage;
    $this->variationStorage = $variation_storage;
    $this->variationTypeStorage = $variation_type_storage;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity.manager')->getStorage('commerce_product'),
      $container->get('entity.manager')->getStorage('commerce_product_type'),
      $container->get('entity.manager')
        ->getStorage('commerce_product_variation'),
      $container->get('entity.manager')
        ->getStorage('commerce_product_variation_type'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  function validateDrushParams($args) {
    // TODO: Implement validateDrushParams() method.
  }

  /**
   * Create one product.
   */
  protected function generateProduct(&$results) {
    $product_type = 'default';
    // Need to be generated.
    $product_title = $this->getRandom()
      ->word(mt_rand(1, $results['title_length']));

    //$variation = $this->generateProductVariation($results);
    $product = $this->productStorage->create(array(
      'product_id' => NULL,
      'type' => $product_type,
      'langcode' => $this->getLangcode($results),
      'title' => $product_title,
      'devel_generate' => TRUE,
      //'variations' => array($variation),
    ));

    //$this->populateFields($product);
    $this->populateF($results, $product);

    $product->save();
  }

  /**
   * Create one product variation.
   */
  protected function generateProductVariation(&$results) {
    $product_variation_type = 'default';
    // Need to be generated.
    $product_variation_title = $this->getRandom()
      ->word(mt_rand(1, $results['title_length']));

    $product_variation = $this->variationStorage->create(array(
      'variation_id' => NULL,
      'type' => $product_variation_type,
      'langcode' => $this->getLangcode($results),
      'sku' => $product_variation_title,
    ));

    $this->populateFields($product_variation);

    $product_variation->save();
    return $product_variation;
  }

  /**
   * Determine language based on $results.
   */
  protected function getLangcode($results) {
    if (isset($results['add_language'])) {
      $langcodes = $results['add_language'];
      $langcode = $langcodes[array_rand($langcodes)];
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    return $langcode;
  }

  /**
   * Generates a specified number of products.
   */
  private function generateProducts($values) {
    for ($i = 1; $i <= $values['num']; $i++) {
      $this->generateProduct($values);
    }
    $this->setMessage($this->formatPlural($values['num'], '1 product created.', 'Finished creating @count products'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['num'] = array(
      '#type' => 'number',
      '#title' => $this->t('How many nodes would you like to generate?'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    );

    $form['title_length'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum number of words in titles'),
      '#default_value' => $this->getSetting('title_length'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 255,
    );

    $options = array();
    // We always need a language.
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      $options[$langcode] = $language->getName();
    }

    $form['add_language'] = array(
      '#type' => 'select',
      '#title' => $this->t('Set language on nodes'),
      '#multiple' => TRUE,
      '#description' => $this->t('Requires locale.module'),
      '#options' => $options,
      '#default_value' => array(
        $this->languageManager->getDefaultLanguage()->getId(),
      ),
    );

    $form['#redirect'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values) {
    $this->generateProducts($values);
  }

  public function populateF(&$results, EntityInterface $entity) {
    /** @var \Drupal\field\FieldConfigInterface[] $instances */
    $instances = entity_load_multiple_by_properties('field_config', array(
      'entity_type' => $entity->getEntityType()
        ->id(),
      'bundle' => $entity->bundle(),
    ));

    if ($skips = function_exists('drush_get_option') ? drush_get_option('skip-fields', '') : @$_REQUEST['skip-fields']) {
      foreach (explode(',', $skips) as $skip) {
        unset($instances[$skip]);
      }
    }

    foreach ($instances as $instance) {
      $field_storage = $instance->getFieldStorageDefinition();
      $max = $cardinality = $field_storage->getCardinality();
      $field_name = $field_storage->getName();
      if ($field_name == 'variations') {
        $entity->$field_name = $this->generateProductVariation($results);
        continue;
      }
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        // Just an arbitrary number for 'unlimited'
        $max = rand(1, 3);
      }
      $entity->$field_name->generateSampleItems($max);
    }
  }

}
