<?php

namespace Drupal\config_import_missing_content\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\Importer\MissingContentEvent;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Config Import Missing Content event subscriber.
 */
class ConfigImportMissingContentSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The block content entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $blockContentStorage;

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Static cache of active block configs.
   *
   * @var array[]
   */
  protected $blockConfigs;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active configuration storage.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StorageInterface $active_storage, LoggerInterface $logger, TranslationInterface $string_translation) {
    $this->blockContentStorage = $entity_type_manager->hasHandler('block_content', 'storage')
      ? $entity_type_manager->getStorage('block_content')
      : NULL;

    $this->activeStorage = $active_storage;
    $this->logger = $logger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Creates missing content on config import.
   *
   * @param \Drupal\Core\Config\Importer\MissingContentEvent $event
   *   Missing content content import event.
   */
  public function onImportMissingContent(MissingContentEvent $event) {
    foreach ($event->getMissingContent() as $data) {
      if ($data['entity_type'] == 'block_content' && $this->blockContentStorage && $block = $this->findBlockConfig($data)) {
        $custom_block = $this->blockContentStorage->create([
          'uuid' => $data['uuid'],
          'type' => $data['bundle'],
          'info' => $block['settings']['label'],
        ]);
        $custom_block->save();

        $event->resolveMissingContent($data['uuid']);

        $this->logger->notice('Created the custom block "%name" for the "%block" block.', [
          '%name'  => $custom_block->label(),
          '%block' => $block['id'],
          'link'   => $custom_block->toLink($this->t('Edit'), 'edit-form')->toString(),
        ]);
      }
    }
  }

  /**
   * Find a block configuration that is depends on a missing block content.
   *
   * @param string[] $data
   *   Data about the block content, containing keys for 'uuid', 'entity_type'
   *   and 'bundle'.
   *
   * @return array|null
   *   A block configuration that depends on the missing block, or null if none
   *   found.
   */
  protected function findBlockConfig(array $data) {
    if (!isset($this->blockConfigs)) {
      $this->blockConfigs = array_filter(
        $this->activeStorage->readMultiple($this->activeStorage->listAll('block.block.')),
        function ($config) {
          return isset($config['dependencies']['content']);
        }
      );
    }

    $config_dependency_name = "$data[entity_type]:$data[bundle]:$data[uuid]";

    foreach ($this->blockConfigs as $block_config) {
      if (in_array($config_dependency_name, $block_config['dependencies']['content'])) {
        return $block_config;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::IMPORT_MISSING_CONTENT => ['onImportMissingContent'],
    ];
  }

}
