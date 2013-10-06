<?php

require __DIR__ . '/MetadataExtractor.php';
$extractor = new MetadataExtractor;

// Train on manually tagged data
$extractor->train('training-data.txt');

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'html') {
  // Print HTML
  $extractor->printMatrix('transition');
  $extractor->printMatrix('emissions');
} else {
  // Save the results of training (probability matrices)
  echo $extractor->dumpTrainedData();
}
