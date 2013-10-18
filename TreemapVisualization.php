<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package TreemapVisualization
 */

namespace Piwik\Plugins\TreemapVisualization;

use Piwik\Common;
use Piwik\Period;
use Piwik\Plugin\ViewDataTable;
use Piwik\ViewDataTable\Request;

/**
 * @see plugins/TreemapVisualization/Treemap.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/TreemapVisualization/Treemap.php';

/**
 * Plugin that contains the Treemap DataTable visualization.
 */
class TreemapVisualization extends \Piwik\Plugin
{

    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Visualization.addVisualizations' => 'getAvailableVisualizations',
            'ViewDataTable.configure'         => 'configureViewDataTable'
        );
    }

    public function configureViewDataTable(ViewDataTable $view)
    {
        if ('Actions' === $view->requestConfig->getApiModuleToRequest()) {
            $this->makeSureTreemapIsShownOnActionsReports($view);
        }
    }

    public function makeSureTreemapIsShownOnActionsReports(ViewDataTable $view)
    {
        if ($view->isRequestingSingleDataTable() || $view->isViewDataTableId(Treemap::ID)) {
            $view->config->show_all_views_icons = true;
            $view->config->show_bar_chart = false;
            $view->config->show_pie_chart = false;
            $view->config->show_tag_cloud = false;
        }
    }

    public function getAvailableVisualizations(&$visualizations)
    {
        // treemap doesn't work w/ flat=1
        if (!Common::getRequestVar('flat', 0)) {
            $visualizations[] = 'Piwik\\Plugins\\TreemapVisualization\\Treemap';
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemap.less';
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemapColors.less';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'libs/Jit/jit-2.0.1-yc.js';
        $jsFiles[] = 'plugins/TreemapVisualization/javascripts/treemapViz.js';
    }

}