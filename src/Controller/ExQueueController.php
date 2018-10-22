<?php

namespace Drupal\exqueue01\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ExQueueController.
 *
 * Demonstrates the use of the Queue API
 * There is two routes.
 * 1) \Drupal\exqueue01\Controller\ExQueueController::getData
 * The getData() methods allows to load external data and
 * for each array element create a queue element
 * Then on Cron run, we create a page node for each element with
 * 2) \Drupal\exqueue01\Controller\ExQueueController::deleteTheQueue
 * The deleteTheQueue() methods delete the queue "exqueue_import"
 * and all its elements
 * Once the queue is created with tha data, on Cron run
 * we create a new page node for each item in the queue with the QueueWorker
 * plugin ExQueue01.php .
 */
class ExQueueController extends ControllerBase {

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;
  /**
   * Symfony\Component\DependencyInjection\ContainerAwareInterface definition.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
   */
  protected $queueFactory;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */

  protected $client;

  /**
   * Inject services.
   */
  public function __construct(MessengerInterface $messenger, QueueFactory $queue, ClientInterface $client) {
    $this->messenger = $messenger;
    $this->queueFactory = $queue;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('queue'),
      $container->get('http_client')
    );
  }

  /**
   * Delete the queue 'exqueue_import'.
   *
   * Remember that the command drupal dq checks first for a queue worker
   * and if it exists, DC suposes that a queue exists.
   */
  public function deleteTheQueue() {
    $this->queueFactory->get('exqueue_import')->deleteQueue();
    return [
      '#type' => 'markup',
      '#markup' => $this->t('The queue "exqueue_import" has been deleted'),
    ];
  }

  /**
   * Getdata from external source and create a item queue for each data.
   *
   * @return array
   *   Return string.
   */
  public function getData() {

    // 1. Get data into an array of objects
    // 2. Get the queue and the total of items before the operations
    // 3. For each element of the array, create a new queue item
    // 1. Get data into an array of objects
    // We can choose between two methods
    // getDataFromRSS() or getFakeData()
    $data = $this->getDataFromRss();
    // $data = $this->getFakeData();
    if (!$data) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No data found'),
      ];
    }

    // 2. Get the queue and the total of items before the operations
    // Get the queue implementation for 'exqueue_import' queue.
    $queue = $this->queueFactory->get('exqueue_import');

    // Get the total of items in the queue before adding new items.
    $totalItemsBefore = $queue->numberOfItems();

    // 3. For each element of the array, create a new queue item.
    foreach ($data as $element) {
      // Create new queue item.
      $queue->createItem($element);
    }

    // 4. Get the total of item in the Queue.
    $totalItemsAfter = $queue->numberOfItems();

    // 5. Get what's in the queue now.
    $tableVariables = $this->getItemList($queue);

    $finalMessage = $this->t('The Queue had @totalBefore items. We should have added @count items in the Queue. Now the Queue has @totalAfter items.',
      [
        '@count' => count($data),
        '@totalAfter' => $totalItemsAfter,
        '@totalBefore' => $totalItemsBefore,
      ]);

    return [
      '#type' => 'table',
      '#caption' => $finalMessage,
      '#header' => $tableVariables['header'],
      '#rows' => $tableVariables['rows'],
      '#attributes' => $tableVariables['attributes'],
      '#sticky' => $tableVariables['sticky'],
      'empty' => $this->t('No items.'),
    ];
  }

  /**
   * Generate an array of objects to simulate getting data from an RSS file.
   *
   * @return array
   *   Return an array of data
   */
  protected function getFakeData() {
    // We should get the XML content and convert it to an array of item objects
    // We use now an example array of item object.
    $content = [];

    for ($i = 1; $i <= 10; $i++) {
      $item = new \stdClass();
      $item->title = 'Title ' . $i;
      $item->body = 'Body ' . $i;
      $content[] = $item;
    }

    return $content;

  }

  /**
   * Generate an array of objects from an external RSS file.
   *
   * @return array|bool
   *   Return an array or false
   */
  protected function getDataFromRss() {
    // 1. Try to get the data form the RSS
    // URI of the XML file.
    $uri = 'https://www.drupal.org/planet/rss.xml';

    // 1. Try to get the data form the RSS.
    try {
      $response = $this->client->get($uri, ['headers' => ['Accept' => 'text/plain']]);
      $data = (string) $response->getBody();
      if (empty($data)) {
        return FALSE;
      }
    }
    catch (RequestException $e) {
      return FALSE;
    }

    // 2. Retrieve data in a simple xml object.
    $data = simplexml_load_string($data);

    // 3. Transform in a array of object
    // We could transform in array
    // $data = json_decode(json_encode($data));
    // Look at all children of the channel child.
    $content = [];
    foreach ($data->children()->children() as $child) {
      if (!empty($child->title)) {
        // Create an object.
        $item = new \stdClass();
        $item->title = $child->title->__toString();
        $item->body = $child->description->__toString();
        // Place the object in an array.
        $content[] = $item;
      }
    }

    if (empty($content)) {
      return FALSE;
    }

    return $content;
    /*
    // Using simplexml_load_file
    $xml = simplexml_load_file($uri);
    $data = json_decode(json_encode($xml));
    ksm($data);
     */
  }

  /**
   * Get all items of queue.
   *
   * Next place them in an array so we can retrieve them in a table.
   *
   * @param object $queue
   *   A queue object.
   *
   * @return array
   *   A table array for rendering.
   */
  protected function getItemList($queue) {
    $retrieved_items = [];
    $items = [];

    // Claim each item in queue.
    while ($item = $queue->claimItem()) {
      $retrieved_items[] = [
        'data' => [$item->data->title, $item->item_id],
      ];
      // Track item to release the lock.
      $items[] = $item;
    }

    // Release claims on items in queue.
    foreach ($items as $item) {
      $queue->releaseItem($item);
    }

    // Put the items in a table array for rendering.
    $tableTheme = [
      'header' => [$this->t('Title'), $this->t('ID')],
      'rows'   => $retrieved_items,
      'attributes' => [],
      'caption' => '',
      'colgroups' => [],
      'sticky' => TRUE,
      'empty' => $this->t('No items.'),
    ];

    return $tableTheme;

  }

}
