<?php
  $feature  = $variables['node']->feature;
  $feature_id = $feature->feature_id;
 
  // Always want to expand joins as arrays regardless of how many matches
  //   there are
  $table_options = array('return_array' => true);


  /////// Get information about physical position ///////
  
  $phys_pos = array();
  foreach ($feature->all_featurelocs as $featureloc) {
//echo "featureloc: <pre>";var_dump($featureloc);echo "</pre>";
    // get linkage group and version
    $srcfeature = $featureloc->record->srcfeature_id;
//echo "srcfeature: <pre>";var_dump($srcfeature);echo "</pre>";    
    $sql = "
      SELECT a.name FROM chado.feature f
        INNER JOIN chado.analysisfeature af ON af.feature_id=f.feature_id
        INNER JOIN chado.analysis a ON a.analysis_id=af.analysis_id
      WHERE f.feature_id=" . $srcfeature->feature_id;
//echo "$sql<br>";
    if ($res=chado_query($sql)) {
      $row = $res->fetchObject();
      
      // Get GBrowse link
      $srcfeature = chado_expand_var($srcfeature, 'table', 'featureprop', $table_options);
      $props = $srcfeature->featureprop;
      foreach ($props as $prop){
        if ($prop->type_id->name == 'Browser Track Name') {
          $pos['track_name'] = $prop->value;
        }
      }

      $pos['chr']   = $srcfeature->name;
      $pos['ver']   = $row->name;
      $pos['start'] = $featureloc->fmin;
      $pos['end']   = $featureloc->fmax;
      array_push($phys_pos, $pos);
    }
  }//all featureloc records
//echo "phys positions: <pre>";var_dump($phys_pos);echo "</pre>";    

  $pos_table = '';
  if (count($phys_pos) == 0) {
    $pos_table = 'physical position unknown';
  }
  else {
    $pos_table = "
      <table>
        <tr>
          <td><b>Assembly version</b></td>
          <td><b>Chromosome</b></td>
          <td><b>Start</b></td>
          <td><b>End</b></td>
        </tr>";
    foreach ($phys_pos as $pos) {
//TODO: get GBrowse link from db
//      $ver = (isset($pos['track_name']) 
//           ?  '<a href="' .
$ver = $pos['ver'];
      $pos_table .= "
        <tr>
          <td>$ver</td>
          <td>" . $pos['chr'] . "</td>
          <td>" . $pos['start'] . "</td>
          <td>" . $pos['end'] . "</td>
        </tr>";
    }//each physical position
    $pos_table .= "
      </table>";
  }
  
  
  /////// Get maps ///////
  
  $pos_html = "This marker is not placed on any map.";
  $sql = "
    SELECT cmarker, fm.name AS map, fm.featuremap_id AS map_id, 
           cfm.nid AS map_nid
    FROM chado.marker_search ms
      INNER JOIN chado.featurepos pos 
        ON pos.feature_id=ms.cmarker_id 
           OR pos.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
      INNER JOIN chado.featuremap fm ON fm.featuremap_id=pos.featuremap_id
      INNER JOIN public.chado_featuremap cfm 
        ON cfm.featuremap_id=fm.featuremap_id
    WHERE cmarker_id=$feature_id";
  if ($res=chado_query($sql)) {
    $pos_rows = array();
    while ($row=$res->fetchObject()) {
      $map_id = $row->map_id;
      $sql = "
        SELECT m.name AS map, cf.nid, lg.name AS lg, lgx.accession, 
               lgdb.urlprefix, fp.mappos 
        FROM chado.featurepos fp
          INNER JOIN chado.feature lg ON lg.feature_id=fp.map_feature_id
          INNER JOIN chado.featuremap m ON m.featuremap_id=fp.featuremap_id
          INNER JOIN public.chado_featuremap as cf ON cf.featuremap_id=m.featuremap_id
          RIGHT OUTER JOIN chado.feature_dbxref lgdx ON lgdx.feature_id=lg.feature_id
          RIGHT OUTER JOIN chado.dbxref lgx ON lgx.dbxref_id=lgdx.dbxref_id
          RIGHT OUTER JOIN chado.db lgdb ON lgdb.db_id=lgx.db_id
        WHERE fp.feature_id=$feature_id AND fp.featuremap_id=$map_id";
      if ($pos_res=chado_query($sql)) {
        while ($pos_row=$pos_res->fetchObject()) {
          $one_row = "<td><b>map:</b> "
                   . l($pos_row->map, "/node/".$pos_row->nid)
                   . "</td><td><b>linkage group:</b> "
                   . $pos_row->lg
                   . "</td><td><b>position:</b> "
                   . $pos_row->mappos . "cM";
          if ($pos_row->accession) {
            $url = $pos_row->urlprefix . $pos_row->accession;
            $one_row .= " [<a href=\"$url\">CMap</a>]";
          } 
          $pos_rows[] = $one_row;
        }
      }//each position record
    }
    if (count($pos_rows) > 0) {
      $pos_html = "<table><tr>" . implode('</tr><tr>', $pos_rows) . "</tr></tables>";
    }
  }

  // the $headers array is an array of fields to use as the colum headers. 
  // additional documentation can be found here 
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  // This table for the analysis has a vertical header (down the first column)
  // so we do not provide headers here, but specify them in the $rows array below.
  $headers = array();
  
  // the $rows array contains an array of rows where each row is an array
  // of values for each column of the table in that row.  Additional documentation
  // can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7 
  $rows = array();

  $rows[] = array(
    array(
      'data' => 'Name',
      'header' => TRUE,
      'width' => '20%',
    ),
    $feature->name
  );

  // physical position (if given)
  $rows[] = array(
    array(
      'data' => 'Physical position(s)',
      'header' => TRUE,
      'width' => '20%',
    ),
    $pos_table,
  );
   
  // map position(s)
  $rows[] = array(
    array(
      'data' => 'Map Position(s)',
      'header' => TRUE,
      'width' => '20%',
    ),
    $pos_html,
  );

// the $table array contains the headers and rows array as well as other
// options for controlling the display of the table.  Additional
// documentation can be found here:
// https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
$table = array(
  'header' => $headers,
  'rows' => $rows,
  'attributes' => array(
    'id' => 'tripal_feature-table-positions',
    'class' => 'tripal-data-table'
  ),
  'sticky' => FALSE,
  'caption' => '',
  'colgroups' => array(),
  'empty' => '',
);

// once we have our table array structure defined, we call Drupal's theme_table()
// function to generate the table.
print theme_table($table); 
