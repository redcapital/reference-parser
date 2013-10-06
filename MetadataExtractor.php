<?php

class MetadataExtractor
{
  const MIN_PROBABILITY = 0.0000001;

  protected $canonicalStates = array(
    'T', 'A', 'D', 'P', 'V', 'J', 'N', 'U', 'B', 'L'
  );

  protected $symbolRegexps = array(
    'comma' => ',',
    'dot' => '\.',
    'hyphen' => '[\-—–]',
    'colon' => ':',
    'semicolon' => ';',
    'question' => '\?',
    'quote' => '"',
    'leftParen' => '\(',
    'rightParen' => '\)',
    'leftBracket' => '\[',
    'rightBracket' => '\]',
    'openQuote' => '«',
    'closeQuote' => '»',
    'slash' => '\/',
    'misc' => '[_\*&\^%]',
    'apostrophe' => "'",
    'number' => 'no|num(ber)?|№|номер',
    'volume' => 'vol|т(ом)?',
    'pages' => 'p(ages?)?|pp|с(тр)?',
    'press' => 'изд(ательство)?|press',
    'release' => 'вып(уск)?',
    'protocol' => 'https?|ftp',
    'other' => 'др(угие)?',
    'upperLetter' => '\p{Lu}',
    'lowerLetter' => '\p{Ll}',
    'upperWord' => '\p{Lu}+',
    'titleWord' => '\p{Lu}\p{Ll}+',
    'fourDigit' => '\d{4}',
    'digit' => '\d+',
    'word' => '\p{L}+',
  );
  protected $caselessSymbols = array(
    'number', 'volume', 'pages', 'press', 'release', 'protocol',
    'other'
  );

  protected $states, $symbols;

  public $transitions, $emissions;

  public function __construct()
  {
    mb_regex_encoding('UTF-8');
    mb_internal_encoding('UTF-8');

    $this->states = array();

    foreach ($this->canonicalStates as $state) {
      $this->states[] = $state . 'S';
      $this->states[] = $state . 'R';
    }
    $this->symbols = array_keys($this->symbolRegexps);
    $this->symbols[] = 'unknown';
  }

  protected function tokenize($string)
  {
    return preg_split('/(\p{P}|\p{S}|\d+)|\s+/u', $string, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
  }

  protected function symbolize($token)
  {
    foreach ($this->symbolRegexps as $symbol => $regexp) {
      $options = 'u';
      if (in_array($symbol, $this->caselessSymbols)) {
        $options .= 'i';
      }
      if (preg_match("/^(?:$regexp)$/$options", $token)) {
        return $symbol;
      }
    }
    return 'unknown';
  }

  protected function resetMatrices()
  {
    $this->transitions = $this->emissions = array();

    foreach ($this->states as $from) {
      $this->transitions[$from] = array_fill_keys($this->states, 0);
      $this->transitions[$from]['total'] = 0;
    }
    $this->transitions['START'] = array_fill_keys($this->states, 0);
    $this->transitions['START']['total'] = 0;

    foreach ($this->states as $from) {
      $this->emissions[$from] = array_fill_keys($this->symbols, 0);
      $this->emissions[$from]['total'] = 0;
    }
  }

  protected function parseTaggedReference($reference)
  {
    static $regexp;
    if (!isset($regexp)) {
      $statesStr = implode('', $this->canonicalStates);
      $regexp = "/<([$statesStr])>(.*?)(?=<[$statesStr]>|$)/";
    }
    preg_match_all($regexp, $reference, $matches, PREG_SET_ORDER);
    return array_map(function($match) {
      return array('state' => $match[1], 'string' => $match[2]);
    }, $matches);
  }

  protected function countEvents($reference)
  {
    $currentState = 'START';
    foreach ($this->parseTaggedReference($reference) as $record) {
      $parsedSymbols = array_map(
        array($this, 'symbolize'),
        $this->tokenize($record['string'])
      );
      $first = true;
      foreach ($parsedSymbols as $parsedSymbol) {
        $refState = $record['state'];
        if ($first) {
          $refState .= 'S';
          $first = false;
        } else {
          $refState .= 'R';
        }
        $this->transitions[$currentState][$refState]++;
        $this->transitions[$currentState]['total']++;

        $currentState = $refState;

        $this->emissions[$currentState]['total']++;
        $this->emissions[$currentState][$parsedSymbol]++;
      }
    }
  }

  protected function computeProbabilities(array $counts, array $fromArr, array $toArr)
  {
    $probabilityMatrix = array();
    foreach ($fromArr as $i => $from) {
      $zero = $nonZero = 0;
      $probabilityMatrix[$i] = array();
      foreach ($toArr as $j => $to) {
        $probabilityMatrix[$i][$j] =
          $counts[$from][$to] * 1.0 / $counts[$from]['total'];
        if ($counts[$from][$to] == 0) {
          $zero++;
        } else {
          $nonZero++;
        }
      }
      // Smooth probabilities
      $smoothDelta = $zero * self::MIN_PROBABILITY / $nonZero;
      foreach ($probabilityMatrix[$i] as $to => &$probability) {
        if ($probability == 0) {
          $probability += self::MIN_PROBABILITY;
        } else {
          $probability -= $smoothDelta;
        }
      }
      unset($probability);
    }

    return $probabilityMatrix;
  }

  protected function printMatrixInternal(array $matrix, array $fromArr, array $toArr)
  {
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>&nbsp;</th>';
    foreach ($toArr as $to) {
      echo "<th>$to</th>";
    }
    echo '<th>TOTAL prob</th>';
    echo '</tr>';

    foreach ($fromArr as $i => $from) {
      echo '<tr>';
      echo "<td><strong>$from</strong></td>";
      $total = 0;
      foreach ($toArr as $j => $to) {
        $total += $matrix[$i][$j];
        echo sprintf('<td>%.7f</td>', $matrix[$i][$j]);
      }
      echo sprintf('<td>%.8f</td>', $total);
      echo '</td>';
      echo '</tr>';
    }
    echo '</table><br>';
  }

  public function train($filename)
  {
    $this->resetMatrices();
    $id = null;
    foreach (file($filename) as $line) {
      $line = trim($line);
      $this->countEvents($line);
    }

    $this->transitions = $this->computeProbabilities(
      $this->transitions,
      $this->states + array('START' => 'START'),
      $this->states
    );

    $this->emissions = $this->computeProbabilities(
      $this->emissions,
      $this->states,
      $this->symbols
    );
  }

  public function dumpTrainedData()
  {
    $data = array(
      'transitions' => $this->transitions,
      'emissions' => $this->emissions
    );
    return "<?php return \n" . var_export($data, true) . ';';
  }

  public function printMatrix($type)
  {
    if ($type == 'transition') {
      $this->printMatrixInternal(
        $this->transitions,
        $this->states + array('START' => 'START'),
        $this->states
      );
    } else {
      $this->printMatrixInternal(
        $this->emissions,
        $this->states,
        $this->symbols
      );
    }
  }

  public function extract($rawReference)
  {
    $tokens = $this->tokenize($rawReference);
    if (empty($tokens)) {
      return array();
    }
    $symbols = $this->symbols;
    $symbolSequence = array_map(array($this, 'symbolize'), $tokens);
    $symbolSequence = array_map(function($symbol) use ($symbols) {
      return array_search($symbol, $symbols);
    }, $symbolSequence);
    $states = $this->viterbi($symbolSequence);
    for ($result = array(), $c = count($states), $i = 0; $i < $c; $i++) {
      $result[] = array('state' => $states[$i], 'token' => $tokens[$i], 'symbol' => $symbols[$symbolSequence[$i]]);
    }
    return $result;
  }

  protected function viterbi(array $symbolSequence)
  {
    $stateCount = count($this->states);
    $sequenceCount = count($symbolSequence);
    $t1 = $t2 = array();
    for ($i = 0; $i < $stateCount; $i++) {
      $t1[$i] = array(
        0 => $this->transitions['START'][$i] * $this->emissions[$i][$symbolSequence[0]],
      );
      $t2[$i] = array(0 => 0);
    }
    for ($i = 1; $i < $sequenceCount; $i++) {
      for ($j = 0; $j < $stateCount; $j++) {
        $maxProb = $maxInd = -1;
        for ($k = 0; $k < $stateCount; $k++) {
          $prob = $t1[$k][$i - 1] * $this->transitions[$k][$j] *
            $this->emissions[$j][$symbolSequence[$i]];
          if ($prob > $maxProb) {
            $maxProb = $prob;
            $maxInd = $k;
          }
        }
        $t1[$j][$i] = $maxProb;
        $t2[$j][$i] = $maxInd;
      }
    }

    $z = array();
    $maxInd = -1;
    for ($k = 0; $k < $stateCount; $k++) {
      if ($maxInd < 0 || $t1[$k][$sequenceCount - 1] > $t1[$maxInd][$sequenceCount - 1]) {
        $maxInd = $k;
      }
    }
    $z[$sequenceCount - 1] = $maxInd;
    $x = array($sequenceCount - 1 => $this->states[$z[$sequenceCount - 1]]);
    for ($i = $sequenceCount - 1; $i > 0; $i--) {
      $z[$i - 1] = $t2[$z[$i]][$i];
      $x[$i - 1] = $this->states[$z[$i - 1]];
    }

    return $x;
  }
}

