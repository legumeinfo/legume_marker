<?php
/**
 * @file
 * Implementation of hooks to create a feature content type
 */



/**
 * Implements hook_node_view().
 * Acts on all content types.
 *
 * @ingroup legume_marker
 */
function legume_marker_node_view($node, $view_mode, $langcode) {
  if ($node->type != 'chado_feature') { return; }

  $path = drupal_get_path('module', 'legume_marker');
  
  switch ($view_mode) {
    case 'full':
      if ($node->feature->type_id->name == 'genetic_marker') {
        // we want to override the base feature theme
        $node->content['tripal_feature_base'] = array(
          '#markup' => theme("tripal_feature_marker_base",
                             array('node' => $node)),
          '#tripal_toc_id'    => 'base',
          '#tripal_toc_title' => 'Marker Overview',
          '#weight' => -100,
        );
        $node->content['tripal_feature_marker_base'] = array(
          '#markup' => theme("tripal_feature_marker_base",
                             array('node' => $node)),
          '#tripal_toc_id'    => 'marker',
          '#tripal_toc_title' => 'Marker Overview',
          '#weight' => -100,
        );
        
        $node->content['tripal_feature_relationships'] = array(
          '#markup' => theme('tripal_feature_marker_positions', array('node' => $node)),
          '#tripal_toc_id'    => 'positions',
          '#tripal_toc_title' => 'Marker Positions',
        );
        $node->content['tripal_feature_marker_positions'] = array(
          '#markup' => theme('tripal_feature_marker_positions', array('node' => $node)),
          '#tripal_toc_id'    => 'positions',
          '#tripal_toc_title' => 'Marker Positions',
        );

      }
      break;
      
    case 'teaser':
      break;
      
    default:
      break;
  }
//echo "<pre>";var_dump(array_keys($node->content));echo "</pre>";
}


