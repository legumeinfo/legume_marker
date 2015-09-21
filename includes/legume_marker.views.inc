<?php
function legume_marker_views_query_alter(&$view, &$query) {
/*doesn't work ... try group 1?
drupal_set_message('query:<pre>' . print_r($query, true) . '</pre>');

  $search_term = false;
  if ($view->name == 'marker_search') {
    // Traverse through the 'where' part of the query.
    for ($i=0; $i<count($query->where); $i++) {
      $condition_group = $query->where[$i];
drupal_set_message('where:<pre>' . print_r($query->where, true) . '</pre>');
//drupal_set_message('There are ' . count($condition_group['conditions']) . ' conditions');
      for ($j=0; $j<count($condition_group['conditions']); $j++) {
        $condition = $condition_group['conditions'][$j];
drupal_set_message('condition:<pre>' . print_r($condition, true) . '</pre>');
        if ($condition['field'] == 'marker_search.cmarker') {
          // Get the search term
          $search_term = $condition['value'];

          // Replace with a nested condition checking all three columns
drupal_set_message("replace condition $j");
          unset($query->where[$i]['conditions'][$j]);
          $group = count($condition_group['conditions']);
          $args = array(':term' => $search_term);
          $sql = "cmarker LIKE ':term'";
          $query->add_where_expression($group, $sql, $args);
          break;
//drupal_set_message('condition groups are now:<pre>' . print_r($query->where, true) . '</pre>');
        }//found cmarker field
      }//each condition
    }//each where clause
  }//view=marker_search
  
drupal_set_message('now query is:<pre>' . print_r($query, true) . '</pre>');
*/
}//legume_marker_views_query_alter


