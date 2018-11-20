EXQUEU01
========

This is a simple example of the Queue API with a controller and
a QueueWorker plugin in Drupal 8.

Read the blog post here:
http://karimboudjema.com/en/drupal/20180807/create-queue-controller-drupal8

In this module we generate a queue with a controller, importing the
title and the description tags form the Drupal Planet RSS file.

Next, when Cron runs, with a QueueWorker plugin, for each item in the queue
we create a node page.

This module has to main parts:

1. A controller class src/Controller/ExQueueController.php with its
corresponding route in exqueue01.routing.yml

2. A QueueWorker plugin src/Plugin/QueueWorker/ExQueue01.php


Module Tree
-----------

|-- exqueue01.info.yml
|-- exqueue01.module
|-- exqueue01.permissions.yml
|-- exqueue01.routing.yml
|-- README.txt
`-- src
    |-- Controller
    |   `-- ExQueueController.php
    `-- Plugin
        `-- QueueWorker
            `-- ExQueue01.php


The Controller
--------------
The controller manages two routes.

1) \Drupal\exqueue01\Controller\ExQueueController::getData
The getData() method allows to load external data in a a RSS file and
creates an array of object from it.
Then, for each array element, it creates a queue element in the queue
"exqueue_import".
Next it lists all the items in the queue

Once the queue is created with the data, on Cron run
we create a new page node for each item in the queue with the QueueWorker
plugin ExQueue01.php


2) \Drupal\exqueue01\Controller\ExQueueController::deleteTheQueue
The deleteTheQueue() method delete the queue "exqueue_import"
and all its elements

The QueueWorker plugin
----------------------
The QueueWorker simply create a node Page for each element in the Queue
when the Cron runs.


Tips for Drupal Console.
------------------------
To debug the Queue you can use the command drupal debug:queue (dq)

Be aware that this command will work only if a QueueWorker plugin exists
for the queue. 

To launch the QueueWorker use the command drupal queue:run exqueue_import.
