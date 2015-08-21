<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\TreemapVisualization;

use Piwik\Common;
use Piwik\Period;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;

/**
 * @see plugins/TreemapVisualization/Treemap.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/TreemapVisualization/Visualizations/Treemap.php';

/**
 * Plugin that contains the Treemap DataTable visualization.
 */
class TreemapVisualization extends \Piwik\Plugin
{
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'ViewDataTable.addViewDataTable'  => 'getAvailableVisualizations', // Piwik 2.X
            'ViewDataTable.filterViewDataTable'  => 'removeTreemapVisualizationIfFlattenIsUsed' // Piwik 3.X
        );
    }

    public function getAvailableVisualizations(&$visualizations)
    {
        // treemap doesn't work w/ flat=1
        if (Common::getRequestVar('flat', 0)) {
            $key = array_search('Piwik\\Plugins\\TreemapVisualization\\Visualizations\\Treemap', $visualizations);
            if ($key !== false) {
                unset($visualizations[$key]);
            }
        }
    }

    public function removeTreemapVisualizationIfFlattenIsUsed(&$visualizations)
    {
        // treemap doesn't work w/ flat=1
        if (Common::getRequestVar('flat', 0) && isset($visualizations[Treemap::ID])) {
            unset($visualizations[Treemap::ID]);
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemap.less';
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemapColors.less';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/TreemapVisualization/libs/Jit/jit-2.0.1-yc.js';
        $jsFiles[] = 'plugins/TreemapVisualization/javascripts/treemapViz.js';
    }

}
