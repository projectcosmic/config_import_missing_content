<?php

namespace Drupal\Tests\config_import_missing_content\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests content creation on config import.
 *
 * @group config_import_missing_content
 */
class ConfigImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'devongar_helper',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    BlockContentType::create(['id' => 'test'])->save();

    $this->installConfig(['system']);
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
  }

  /**
   * Tests import of a block.
   */
  public function testBlockImport() {
    $uuid = $this->container->get('uuid')->generate();
    $title = $this->randomMachineName();

    $config_sync = $this->container->get('config.storage.sync');
    $config_sync->write('block.block.test', [
      'dependencies' => [
        'content' => [
          "block_content:test:$uuid",
        ],
      ],
      'id' => 'about',
      'plugin' => "block_content:$uuid",
      'settings' => [
        'id' => "block_content:$uuid",
        'label' => $title,
        'provider' => 'block_content',
      ],
    ]);
    $this->configImporter()->import();

    $blocks = $this->container
      ->get('entity_type.manager')
      ->getStorage('block_content')
      ->loadByProperties(['uuid' => $uuid]);
    $this->assertCount(1, $blocks, 'Missing custom block created on import.');

    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = reset($blocks);
    $this->assertEquals($title, $block->info->value, 'Created block has description from block label.');
  }

}
