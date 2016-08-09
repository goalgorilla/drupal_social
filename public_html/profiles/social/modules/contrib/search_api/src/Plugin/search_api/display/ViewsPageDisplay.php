<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\search_api\Display\DisplayPluginBase;

/**
 * Represents a views page display.
 *
 * @SearchApiDisplay(
 *   id = "views_page",
 *   deriver = "Drupal\search_api\Plugin\search_api\display\ViewsPageDisplayDeriver"
 * )
 */
class ViewsPageDisplay extends DisplayPluginBase {}
