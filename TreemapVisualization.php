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
     * The list of Actions reports for whom the treemap should have a width of 100%.
     */
    private static $fullWidthActionsReports = array(
        'getPageUrls',
        'getEntryPageUrls',
        'getExitPageUrls',
        'getEntryPageTitles',
        'getExitPageTitles',
        'getPageTitles',
        'getOutlinks',
        'getDownloads',
    );

    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Visualization.addVisualizations' => 'getAvailableVisualizations',
            'Visualization.initView'          => 'configureReportViewForActions'
        );
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

    public function configureReportViewForActions(ViewDataTable $view)
    {
        list($module, $method) = explode('.', $view->requestConfig->apiMethodToRequestDataTable);

        // make sure treemap is shown on actions reports
        if ('Actions' === $module) {
            if ($view->getViewDataTableId() != Treemap::ID) {
                // make sure we're looking at data that the treemap visualization can use (a single datatable)
                // TODO: this is truly ugly code. need to think up an abstraction that can allow us to describe the
                $viewDataRequest = new Request($view->requestConfig);
                $requestArray = $viewDataRequest->getRequestArray() + $_GET + $_POST;
                $date = Common::getRequestVar('date', null, 'string', $requestArray);
                $period = Common::getRequestVar('period', null, 'string', $requestArray);
                $idSite = Common::getRequestVar('idSite', null, 'string', $requestArray);
                if (Period::isMultiplePeriod($date, $period)
                    || strpos($idSite, ',') !== false
                    || $idSite == 'all'
                ) {
                    return;
                }
            }

            $view->config->show_all_views_icons = true;
            $view->config->show_bar_chart = false;
            $view->config->show_pie_chart = false;
            $view->config->show_tag_cloud = false;

            if ($view->getViewDataTableId() == Treemap::ID) {
                // for some actions reports, use all available space
                if (in_array($method, self::$fullWidthActionsReports)) {
                    $view->config->datatable_css_class = 'infoviz-treemap-full-width';
                    $view->config->max_graph_elements = 50;
                } else {
                    $view->config->max_graph_elements = max(10, $view->config->max_graph_elements);
                }
            }

        } else if ('ExampleUI' === $module
                   && Treemap::ID == $view->getViewDataTableId()
        ) {
            $view->config->show_evolution_values = false;
        }
    }
}