<?php

namespace Drupal\exqueue01\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Save queue item in a node.
 *
 * To process the queue items whenever Cron is run,
 * we need a QueueWorker plugin with an annotation witch defines
 * to witch queue it applied.
 *
 * @QueueWorker(
 *   id = "exqueue_import",
 *   title = @Translation("Import Content From RSS"),
 *   cron = {"time" = 5}
 * )
 */
class ExQueue01 extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              EntityTypeManagerInterface $entityTypeManager,
                              LoggerChannelFactoryInterface $loggerChannelFactory) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );

  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    // Save the queue item in a node
    // Check the values of $item.
    $title = isset($item->title) && $item->title ? $item->title : NULL;
    $body = isset($item->body) && $item->body ? $item->body : NULL;

    try {
      // Check if we have a title and a body.
      if (!$title || !$body) {
        throw new \Exception('Missing Title or Body');
      }

      $storage = $this->entityTypeManager->getStorage('node');
      $node = $storage->create(
        [
          'type' => 'page',
          'title' => $item->title,
          'body' => [
            'value' => $item->body,
            'format' => 'basic_html',
          ],
        ]
      );
      $node->save();

      // Log in the watchdog for debugging purpose.
      $this->loggerChannelFactory->get('debug')
        ->debug('Create node @id from queue %item',
          [
            '@id' => $node->id(),
            '%item' => print_r($item, TRUE),
          ]);
    }
    catch (\Exception $e) {
      $this->loggerChannelFactory->get('Warning')
        ->warning('Exception trow for queue @error',
          ['@error' => $e->getMessage()]);
    }

  }

}
