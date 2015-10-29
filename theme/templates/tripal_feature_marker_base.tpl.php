<?php
  $feature  = $variables['node']->feature;  
  $feature_id = $feature->feature_id;
//echo "<pre>";var_dump($feature);echo "</pre>";
  
  // Always want to expand joins as arrays regardless of how many matches
  //   there are
  $table_options = array('return_array' => true);
  
  // Get all markers and synonyms related to this marker
  $sql = "SELECT * FROM chado.marker_search WHERE cmarker_id=$feature_id";
  if ($res = chado_query($sql, array())) {
    $marker = $res->fetchObject();
  }
  
  // Get marker type, if known
  $marker_types = array();
  $sql = "
    SELECT c.name FROM chado.marker_search ms
      INNER JOIN chado.feature_cvterm fc 
        ON fc.feature_id=ms.cmarker_id 
           OR fc.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
      INNER JOIN chado.cvterm c ON c.cvterm_id=fc.cvterm_id
    WHERE cmarker_id=$feature_id";
  if ($res = chado_query($sql, array())) {
    while ($row=$res->fetchObject()) {
      array_push($marker_types, $row->name);
    }
  }
  if (count($marker_types) > 0) {
    $marker_type = implode(', ', $marker_types);
  }
  else {
    $marker_type = 'unknown';
  }
  
  // Get properties
  $properties = array();
  $feature = chado_expand_var($feature, 'table', 'featureprop', $table_options);
//echo "<br>expanded feature: <pre>";var_dump($feature);echo "</pre>";
  $props = $feature->featureprop;
  foreach ($props as $prop){
    $prop = chado_expand_var($prop, 'field', 'featureprop.value');
    $properties[$prop->type_id->name] = $prop->value;
  }
//echo "<pre>";var_dump($props);echo "</pre>";
  
  // Get maps
  $maps = array();
  $sql = "
    SELECT cmarker,
           ARRAY_TO_STRING(ARRAY_AGG(fm.name), ',') AS maps, 
           ARRAY_TO_STRING(ARRAY_AGG(fm.featuremap_id), ',') AS map_ids, 
           ARRAY_TO_STRING(ARRAY_AGG(cfm.nid), ',') AS map_nids
    FROM chado.marker_search ms
      INNER JOIN chado.featurepos pos 
        ON pos.feature_id=ms.cmarker_id 
           OR pos.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
      INNER JOIN chado.featuremap fm ON fm.featuremap_id=pos.featuremap_id
      INNER JOIN public.chado_featuremap cfm 
        ON cfm.featuremap_id=fm.featuremap_id
    WHERE cmarker_id=$feature_id
    GROUP BY cmarker";
  if ($res = chado_query($sql)) {
    if ($row=$res->fetchObject()) {
      $all_maps = explode(',', $row->maps);
      $all_map_ids = explode(',', $row->map_ids);
      $all_nids = explode(',', $row->map_nids);
      for ($i=0; $i<count($all_maps); $i++) {
        $url = '/node/' . $all_nids[$i];  // NOTE: assumes all maps are sync-ed!
        $map_html = "<td>" . l($all_maps[$i], $url);
        
        // Check for CMap link
        $sql = "
          SELECT dx.accession, db.urlprefix
          FROM chado.featuremap_dbxref fd
            INNER JOIN chado.dbxref dx ON dx.dbxref_id=fd.dbxref_id
            INNER JOIN chado.db ON db.db_id=dx.db_id
          WHERE featuremap_id=" . $all_map_ids[$i];
        if ($cmap_res = chado_query($sql)) {
          $cmap_row = $cmap_res->fetchObject();
          if ($cmap_row->urlprefix) {
            $url = $cmap_row->urlprefix . $cmap_row->accession;
            // don't use l() here; it url-encodes chars we need in the CMap url
            $map_html .= " [<a href=\"$url\">CMap</a>]";
          }
        }//handle CMap link
        
        $map_html .= "</td><td>";
        
        // Get linkage group, if any
        $sql = "
          SELECT lg.feature_id, lg.name AS lg, lgx.accession, lgdb.urlprefix
          FROM chado.marker_search ms
            INNER JOIN chado.featurepos pos 
              ON pos.feature_id=ms.cmarker_id 
                 OR pos.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
            INNER JOIN chado.feature lg ON lg.feature_id=pos.map_feature_id
            LEFT OUTER JOIN chado.feature_dbxref lgdx ON lgdx.feature_id=lg.feature_id
            LEFT OUTER JOIN chado.dbxref lgx ON lgx.dbxref_id=lgdx.dbxref_id
            LEFT OUTER JOIN chado.db lgdb ON lgdb.db_id=lgx.db_id
          WHERE ms.cmarker_id=$feature_id
            AND pos.featuremap_id=" . $all_map_ids[$i];
        if ($lg_res = chado_query($sql)) {
          $lg_row = $lg_res->fetchObject();
          $map_html .= " <b>linkage group:</b> " . $lg_row->lg;
          if ($lg_row->accession) {
            $url = $lg_row->urlprefix . $lg_row->accession;
            // don't use l() here; it url-encodes chars we need in the CMap url
            $map_html .= " [<a href=\"$url\">CMap</a>]";
          }
        }// handle linkage group
        
        $map_html .= "</td>";
        $maps[] = $map_html;
      }//each map
    }
  }

  // Publications
  $pubs = array();
  $sql = "
    SELECT DISTINCT p.uniquename, cp.nid
    FROM chado.marker_search ms
      INNER JOIN chado.feature_pub fp
        ON fp.feature_id=ms.cmarker_id 
          OR fp.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
      LEFT OUTER JOIN chado.featurepos pos
        ON pos.feature_id=ms.cmarker_id 
           OR pos.feature_id::text=ANY(STRING_TO_ARRAY(ms.marker_ids, ','))
      LEFT OUTER JOIN chado.featuremap fm 
        ON fm.featuremap_id=pos.featuremap_id
      LEFT OUTER JOIN chado.featuremap_pub fmp 
        ON fmp.featuremap_id=fm.featuremap_id
      LEFT OUTER JOIN chado.pub p 
        ON p.pub_id=fp.pub_id OR p.pub_id=fmp.pub_id
      LEFT OUTER JOIN public.chado_pub cp ON cp.pub_id=p.pub_id
    WHERE cmarker_id=$feature_id";
//echo "<br>$sql<br>";
  if ($res=chado_query($sql)) {
    while ($row=$res->fetchObject()) {
      $url = '/node/' . $row->nid;  // NOTE: assumes all maps are sync-ed!
      $pub_html = l($row->uniquename, $url);
      $pubs[] = $pub_html;
    }//each pub row
  }

  // Check for physical position
  
?>

<div class="tripal_feature-data-block-desc tripal-data-block-desc"></div> <?php
 
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
  
  // Name row
  $rows[] = array(
    array(
      'data' => 'Name',
      'header' => TRUE,
      'width' => '20%',
    ),
    $feature->name
  );

  // Synonyms, if any
  if (($marker->synonyms && $marker->synonyms != '{}')
        || ($marker->markers && $marker->markers != '{}')) {
    $synonyms = array_unique(
                  array_merge(
                    explode(',', preg_replace('/[\{\}]/', '', $marker->synonyms)),
                    explode(',', preg_replace('/[\{\}]/', '', $marker->markers)))
    );
    // messy, but might be blank synonyms
    for ($i=0;$i<count($synonyms); $i++) {
      if ($synonyms[$i] == '') {
        unset($synonyms[$i]);
  //      $i--;
      }
    }
    $rows[] = array(
      array(
        'data' => 'Synonyms',
        'header' => TRUE
      ),
      implode(', ', $synonyms),
    );
  }

  // Type row
  $rows[] = array(
    array(
      'data' => 'Marker Type',
      'header' => TRUE
    ),
    $marker_type
  );

  // Linkout (if exits)
  $feature = chado_expand_var($feature, 'table', 'feature_dbxref', $table_options);
  $dbxref = $feature->feature_dbxref->dbxref_id;
  //echo "<pre>";var_dump($dbxref);echo "</pre>";
  if ($dbxref) {
    $url = $dbxref->db_id->urlprefix . $dbxref->accession;
    $acc_text = "<a href=\"$url\">" . $dbxref->accession . "</a>";
  }
  else {
    $acc_text = "n/a";
  }
  $rows[] = array(
    array(
      'data' => 'Accession',
      'header' => TRUE,
      'width' => '20%',
    ),
    $acc_text,
  );

  // Organism row
  $organism = $feature->organism_id->genus 
            . " " . $feature->organism_id->species 
            ." (" . $feature->organism_id->common_name .")";
  if (property_exists($feature->organism_id, 'nid')) {
    $text = "<i>" . $feature->organism_id->genus . " " 
           . $feature->organism_id->species 
           . "</i> (" . $feature->organism_id->common_name .")";
    $url = "node/".$feature->organism_id->nid;
    $organism = l($text, $url, array('html' => TRUE));
  } 
  $rows[] = array(
    array(
      'data' => 'Organism',
      'header' => TRUE,
    ),
    $organism
  );

  // Repeat motif (if applicable and exists)
  //TODO: can marker type be tested for relevance of repeat motif?
  $key = 'Repeat Motif';
  if (array_key_exists($key, $properties)) {
    $rows[] = array(
      array(
        'data' => $key,
        'header' => TRUE,
      ),
      $properties[$key],
    );
  }
  
  // Source description
  $rows[] = array(
    array(
      'data' => 'Source Description',
      'header' => TRUE,
    ),
    $properties['Source Description'],
  );
  
  //TODO: how to determine if primers are relevant?
  // Primer rows (if any exist)
  $primers = array();
  $feature = chado_expand_var($feature, 'table', 'feature_relationship', $table_options);
  $related_features = $feature->feature_relationship->object_id;
  foreach ($related_features as $related_feature) {
    $subject = $related_feature->subject_id;
    if ($subject->type_id->name == 'primer') {
      $subject = chado_expand_var($subject, 'field', 'feature.residues');
  //echo "<pre>";var_dump($subject);echo "</pre>";
      $primers[$subject->name] = $subject->residues;
    }
  }//each related feature
  ksort($primers);
  if (count($primers) > 0) {
    $count = 1;
    foreach ($primers as $name => $primer) {
      $rows[] = array(
        array(
          'data' => "Primer$count Name",
          'header' => TRUE,
        ),
        $name,
      );
      $rows[] = array(
        array(
          'data' => "Primer$count Sequence",
          'header' => TRUE,
        ),
        $primer,
      );
      $count++;
    }//each primer
  }//primers found
  
  // Maps
  if (count($maps) == 0) {
    $html = "This marker has not placed on any genetic maps";
  }
  else {
    $html = "<table><tr>" . implode($maps, '</tr><tr>') . "</tr></table>";
  }
  $rows[] = array(
    array(
      'data' => 'Genetic Map(s)',
      'header' => TRUE
    ),
    $html,
  );
  
  // Publications
  $rows[] = array(
    array(
      'data' => 'Publication(s)',
      'header' => TRUE
    ),
    implode($pubs, '<br>'),
  );

  // Comments
  if (array_key_exists('comment', $properties)) {
    $rows[] = array(
      array(
        'data' => 'Comments',
        'header' => TRUE,
      ),
      $properties['comment'],
    );
  }

  /////// SEPARATOR /////////
  $rows[] = array(
    array(
      'data' => '',
      'header' => TRUE,
      'height' => 6,
      'style' => 'background-color:white',
    ),
    array(
      'data' => '',
      'style' => 'background-color:white',
    ),
  );

  // Sequence rows
  if ($feature->seqlen > 0) {
    $rows[] = array(
      array(
        'data' => 'Sequence length',
        'header' => TRUE,
      ),
      $feature->seqlen . 'bp'
    );
    $feature = chado_expand_var($feature, 'field', 'feature.residues');
    $sequence = 'unknown';
    if ($feature->residues && $feature->residues != 'NULL') {
      // construct the definition line
      $defline = '>' . 
                 $feature->uniquename . " " . 
                 'ID=' . $feature->uniquename . "|" .
                 'Name=' . $feature->name . "|" . 
                 'organism=' . $feature->organism_id->genus . " " . $feature->organism_id->species .  "|" .
                 'type=' . $marker_type; 
      $sequences = str_split($feature->residues, 80);
      $sequence = "<pre>$defline\n" . implode('<br>', $sequences) . "</pre>";
    }
    $rows[] = array(
      array(
        'data' => 'Sequence',
        'header' => TRUE,
      ),
      $sequence,
    );
  }
  else {
  //TODO: sequence isn't always relevant: how to determine?
    $rows[] = array(
      array(
        'data' => 'Sequence',
        'header' => TRUE,
      ),
      'unknown'
    );
  }

  // allow site admins to see the feature ID
  if (user_access('view ids')) { 
    // Feature ID
    $rows[] = array(
      array(
        'data' => 'Feature ID',
        'header' => TRUE,
        'class' => 'tripal-site-admin-only-table-row',
      ),
      array(
        'data' => $feature->feature_id,
        'class' => 'tripal-site-admin-only-table-row',
      ),
    );
  }
  
  // the $table array contains the headers and rows array as well as other
  // options for controlling the display of the table.  Additional
  // documentation can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  $table = array(
    'header' => $headers,
    'rows' => $rows,
    'attributes' => array(
      'id' => 'tripal_feature-table-base',
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
