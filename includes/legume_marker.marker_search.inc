<?php
/*
 *  >>>>>>>>>>>>>>>>>>>>>> UNUSED! <<<<<<<<<<<<<<<<<<<<<<
 *                        (for now)
 */

function legume_marker_search() {
  $term = (array_key_exists('term', $_POST)) ? $_POST['term'] : '';
  
  $html = "
  <h2>Search for markers</h2>
  <form method=\"POST\" action=\"\">
    Enter a marker name 
    <input type=\"text\" name=\"term\" id=\"term\" value=\"$term\"> 
    (can be a partial name)
    <input type=\"submit\" value=\"Search\">
  </form>";
  
  if ($term && $term != '') {
    $sql = "
      SELECT cmarker, cmarker_id, cmarker_nid, markers, synonyms 
      FROM marker_search
      WHERE cmarker LIKE '%$term%' 
      OR markers LIKE '%$term%'
      OR synonyms LIKE '%$term%'";
//$html .= "<br>$sql<br>";
    $marker_count = 0;
    $result_html = '';
    if ($res = chado_query($sql)) {
      while ($row=$res->fetchObject()) {
        $marker_count++;
        $row->synonyms = preg_replace('/NULL/', '', preg_replace('/[\{\}]/', '', $row->synonyms));
        $row->markers = preg_replace('/NULL/', '', preg_replace('/[\{\}]/', '', $row->markers));
        $markers = ($row->markers != '') ? explode(',', $row->markers) : array();
        $synonyms = ($row->synonyms != '') ? explode(',', $row->synonyms) : array();
        $all_names = array_unique(array_merge($markers, $synonyms));
        $url = "/node/" . $row->nid;
        $result_html .= l($row->cmarker, $url);
        if ($all_names && count($all_names) > 0) {
          $result_html .= " (also known as " . implode(', ', $all_names) . ')';
        }
        $result_html .= "<br>";
      }//each found record
    }
    if ($marker_count == 0) {
      $result_html .= '<br>No markers found.';
    }
  }
  
  $html = $html . "<br>Found $marker_count marker(s)<br>" . $result_html;
  
  return $html;
}//legume_marker_search

