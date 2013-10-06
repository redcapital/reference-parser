<?php

require __DIR__ . '/MetadataExtractor.php';

// Load data from training
$trainedData = require __DIR__ . '/trained-data.php';

$extractor = new MetadataExtractor;

// Set extractor's probability matrices
$extractor->transitions = $trainedData['transitions'];
$extractor->emissions = $trainedData['emissions'];

// Run extraction on a sample reference
$sampleReference = 'Pivovarova T. Phylogenetic heterogeneity of the species Acidithiobacillus ferrooxidans // International Journal of Systematic and Evolutionary Microbiology, 2003.  Vol. 3';
$result = $extractor->extract($sampleReference);

// Join tokens in corresponding states together
$stateStrings = array();
foreach ($result as $record) {
  $stateName = $record['state'][0];
  if (!isset($stateStrings[$stateName])) {
    $stateStrings[$stateName] = '';
  }
  $stateStrings[$stateName] .= $record['token'] . ' ';
}
print_r($stateStrings);

