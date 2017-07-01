<?php 
function _arraysAreEqual($arrA, $arrB) {
  if (!is_array($arrA) || !is_array($arrB))
  {
    throw new TypeError("both parameters have to be an array");
  }
  if (strlen($arrA) !== strlen($arrB))
  {
    return false;
  }
  for ($i = 0; $i < strlen($arrA); $i++) {
    // No deep equal necessary
    if ($arrA[i] !== $arrB[i]){
      return false;
    }
  }
  return true;
}

// This function converts a boolean query to a 2 dimensional array.
// a AND (b OR c)
// Becomes:
// [[a, b],[a,c]]
// This works recursively and generates an array of all possible combination
// of a matching query.
// The output is meant to be easily parsed to see if there are any matches.
// There are more efficient ways to match content to this query, though this is
// the one that is most easy to maintain and limits risk of side-effects.
// Especially when considering recursively nested queries.
function parseBooleanQuery($searchPhrase){
  // Remove outer brackets if they exist. EX: (a OR b) -> a OR b
  $searchPhrase = removeOuterBrackets($searchPhrase);
  // remove double whitespaces
  $searchPhrase = removeDoubleWhiteSpace($searchPhrase);
  // Split the phrase on the term 'OR', but don't do this on 'OR' that's in
  // between brackets. EX: a OR (b OR c) should not parse the `OR` in between b
  // and c.
  $ors = splitRoot('OR', $searchPhrase);
  // Each parsed string returns a parsed array in this map function.
  foreach($ors as $okay){
    $ands = splitRoot('AND', $okay);
    $nestedPaths = [];
    $andPath = [];
    for ($i = 0; $i < count($ands); $i++) {
      if (containsBrackets($ands[$i])) {        
        $nestedPaths[] = parseBooleanQuery($ands[$i]);
      }
      else {
        $andPath[] = $ands[$i];        
      }
    }
    // Merge the andPath and the nested OR paths together as one `AND` path
    array_push($nestedPaths, [$andPath]);
    // Merge all `ANDs` and `ORs` together in one OR query
    $orPath[] = orsAndMerge($nestedPaths);
  }

  return mergeOrs($orPath);
}

// Removes double whitespace in a string
// In: a b  c\nd\te
// Out: a b c d e
function removeDoubleWhiteSpace($phrase) {
  return preg_replace('/\s/', ' ', $phrase);
}

// Merges 2 or paths together in an AND fashion
// in:
//  orPathA: [ [ a ], [ b ] ]
//  orPathB: [ [ c, d ], [ e ] ]
// out:
//  [
//    [ a, c, d ],
//    [ b, c, d],
//    [ a, e ],
//    [ b, e ]
//  ]
function orAndOrMerge($orPathA, $orPathB) {  
  $result = [];
  foreach($orPathA as $andPathA){
      foreach($orPathB as $andPathB){
        $result[] = andAndMerge($andPathA, $andPathB);
      }
    
  }  
  return $result;
}

// Merges multiple OR paths into one OR path, in an AND fashion
// in:
//  [
//    [ [ a ], [ b ] ],
//    [ [ c, d ], [ e ] ]
//    [ [ f ] ]
//  ]
// out:
//  [
//    [ a, c, d, f ],
//    [ b, c, d, f ],
//    [ a, e, f ],
//    [ b, e, f ]
//  ]
function orsAndMerge($ors) {
  $result = [[]];
  for ($i = 0; $i < count($ors); $i++) {
    $result = orAndOrMerge($result, $ors[$i]);
  }

  return $result;
}

// Removes duplicate and paths within an or path
// in:
//  [ [ a, b ], [ c ], [ b, a ] ]
// out:
//  [ [ a, b ], [ c ] ]
//
// with order matters
// in:
//  [ [ a, b ], [ c ], [ b, a ] ]
// out:
//  [ [ a, b ], [ c ], [ b, a ] ]
// function deduplicateOr(orPath, orderMatters) {
//   var path = orderMatters ?
//     orPath :
//     orPath.map(function(item) { return item.sort() });

//   return path.reduce(function(memo, current){
//     for (var i = 0; i < memo.length; i++) {
//       if (_arraysAreEqual(memo[i], current)) {
//         return memo;
//       }
//     }
//     memo.push(current);
//     return memo;
//   }, []);
// }

// in -> x = [ a, b ], y = [ c, d ]
// out -> [ a, b, c, d ]
function andAndMerge($a, $b) {
  return array_merge($a,$b);
}

// Merges an array of OR queries, containing AND queries to a single OR query
// In:
// [ [ [ a, b ], [ c ] ],
//   [ [ d ] ],
//   [ [ e ], [ f, g ] ] ]
// Out:
// [ [ a, b ], [ c ], [ d ], [ e ], [ f, g ] ]
function mergeOrs($ors) {
  $result = $ors[0];
  for ($i = 1; $i < count($ors); $i++) {    
    $result = array_merge($result,$ors[$i]);
  }

  return $result;
}

// Removes the bracket at the beginning and end of a string. Only if they both
// exist. Otherwise it returns the original phrase.
// Ex: (a OR b) -> a OR b
// But yet doesn't remove the brackets when the last bracket isn't linked to
// the first bracket.
// Ex: (a OR b) AND (x OR y) -> (a OR b) AND (x OR y)
function removeOuterBrackets($phrase) {
  // echo 'Remove brackets'.$phrase;
  // If the first character is a bracket
  // if (phrase.charAt(0) === '(') {
  if(substr($phrase,0,1)==='('){
    
    // Now we'll see if the closing bracket to the first character is the last
    // character. If so. Remove the brackets. Otherwise, leave it as it is.
    // We'll check that by incrementing the counter with every opening bracket,
    // and decrement it with each closing bracket.
    // When the counter hits 0. We are at the end bracket.
    $counter = 0; 
    for ($i = 0; $i< strlen($phrase); $i++) {

      // Increment the counter at each '('
      if (substr($phrase,$i,1) === '(') $counter++;
      // Decrement the counter at each ')'
      else if (substr($phrase,$i,1) === ')') $counter--;
      // If the counter is at 0, we are at the closing bracket.
       if ($counter === 0) {
        // echo "Counter 0";
        // If we are not at the end of the sentence, Return the
        // phrase as-is without modifying it
        if ($i !== (strlen($phrase)- 1)) {
          return $phrase;
         }
        // If we are at the end, return the phrase without the surrounding brackets.
        else {
          return substr($phrase, 1, (strlen($phrase)-2));
        }
      }
    }

  }

  return $phrase;
}

// Returns boolean true when string contains brackets '(' or ')', at any
// position within the string
// Ex: (b AND c)  -> true
// Ex: b AND c    -> false

function containsBrackets($str) {

  // return !!~str.search(/\(|\)/);  
  return !!preg_match('/\(|\)/',$str,$match);
  // return (count($match)>0 ? true:false);
}

// Splits a phrase into multiple strings by a split term. Like the split
// function.
// But then ignores the split terms that occur in between brackets
// Example when splitting on AND:
// In: a AND (b AND c)
// Out: ['a', '(b AND c)']
// We do this by using the built in 'split' function. But as soon as we notice
// our string contains brackets, we create a temporary string, append any
// folling string from the `split` results. And stop doing that when we counted
// as many opening brackets as closing brackets. Then append that string to the
// results as a single string.
function splitRoot($splitTerm, $phrase) {
  $termSplit = explode(' ' . $splitTerm . ' ',$phrase);    
  $result = array();
  $tempNested = array();
  for ($i = 0; $i < COUNT($termSplit); $i++) {
    // If we are dealing with a split in a nested query,
    // add it to the tempNested array, and rebuild the incorrectly parsed nested query
    // later, by re-joining the array with the `splitTerm`, to make it look
    // like it's original state.
    if (containsBrackets($termSplit[$i]) || count($tempNested) > 0) {      
      array_push($tempNested, $termSplit[$i]);

      // When the tempNested contains just as much opening brackets as closing
      // brackets, we can declare it as 'complete'.
      // print_r($tempNested);
      $tempNestedString =  '' . implode(",",$tempNested);
      // $tempNestedString =  $tempNested[0];
      $matches[0] = array(); $matches2[0] = array();
      preg_match_all('/\(/',$tempNestedString,$matches);
      $countOpeningBrackets = count($matches[0]);
      preg_match_all('/\)/',$tempNestedString,$matches2);
      $countClosingBrackets = count($matches2[0]);

      // var countOpeningBrackets = (tempNestedString.match(/\(/g) || []).length;
      // var countClosingBrackets = (tempNestedString.match(/\)/g) || []).length;

      // If the amouth of opening brackets is the same as the amount of
      // closing brackets, then the string is complete.
      if ($countOpeningBrackets === $countClosingBrackets) {
        // result.push(tempNested.join(' ' + splitTerm + ' '));
        array_push($result, implode( " ".$splitTerm." ", $tempNested));

        // Clear the tempNested for the next round
          $tempNested = array();
      }
    }

    // In case we are NOT dealing with a nested query
    else {
      array_push($result, $termSplit[$i]);
      // result.push(termSplit[i]);
    }
  }
  return $result;
}


// echo $searchPhrase = '(Java OR (spring AND hibernate)) AND (Bangalore OR chennai)';
echo $searchPhrase = 'Bangalore AND chennai OR mumbai AND calcutta';

echo "<pre>";

print_r(parseBooleanQuery($searchPhrase));

?>
