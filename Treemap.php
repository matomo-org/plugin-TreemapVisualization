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
use Piwik\DataTable\Map;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\View;
use Piwik\Plugins\CoreVisualizations\Visualizations\Graph;

/**
 * DataTable visualization that displays DataTable data as a treemap (see
 * http://en.wikipedia.org/wiki/Treemapping).
 *
 * Uses the JavaScript Infovis Toolkit (see philogb.github.io/jit/).
 *
 * @property TreemapConfig $config
 */
class Treemap extends Graph
{
    const ID = 'infoviz-treemap';
    const TEMPLATE_FILE     = '@TreemapVisualization/_dataTableViz_treemap.twig';
    const FOOTER_ICON       = 'plugins/TreemapVisualization/images/treemap-icon.png';
    const FOOTER_ICON_TITLE = 'Treemap';

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
     * @var TreemapDataGenerator|null
     */
    public $generator;

    /**
     * Returns the default view property values for this visualization.
     *
     * @return array
     */
    public static function getDefaultConfig()
    {
        return new TreemapConfig();
    }

    public function beforeRender()
    {
        parent::beforeRender();

        // we determine the elements count dynamically based on available width/height
        $this->config->max_graph_elements = false;
        $this->config->datatable_js_type  = 'TreemapDataTable';
        $this->config->show_flatten_table = false;
        $this->config->show_pagination_control = false;
        $this->config->show_offset_information = false;

        if ('ExampleUI' == $this->requestConfig->getApiModuleToRequest()) {
            $this->config->show_evolution_values = false;
        }

        if ('Actions' === $this->requestConfig->getApiModuleToRequest()) {
            $this->makeSureTreemapIsShownOnActionsReports();
        }
    }

    public function makeSureTreemapIsShownOnActionsReports()
    {
        $this->config->show_graph_views_icons = true;
        $this->config->show_bar_chart = false;
        $this->config->show_pie_chart = false;
        $this->config->show_tag_cloud = false;

        $method = $this->requestConfig->getApiMethodToRequest();

        // for some actions reports, use all available space
        if (in_array($method, self::$fullWidthActionsReports)) {
            $this->config->datatable_css_class = 'infoviz-treemap-full-width';
            $this->config->max_graph_elements = 50;
        } else {
            $this->config->max_graph_elements = max(10, $this->config->max_graph_elements);
        }
    }

    public function beforeLoadDataTable()
    {
        $metric      = $this->getMetricToGraph($this->config->columns_to_display);
        $translation = empty($this->config->translations[$metric]) ? $metric : $this->config->translations[$metric];

        $availableWidth  = Common::getRequestVar('availableWidth', false);
        $availableHeight = Common::getRequestVar('availableHeight', false);
        $filterOffset    = $this->requestConfig->filter_offset ? : 0;

        $this->generator = new TreemapDataGenerator($metric, $translation);
        $this->generator->setInitialRowOffset($filterOffset);
        $this->generator->setAvailableDimensions($availableWidth, $availableHeight);

        $this->assignTemplateVar('generator', $this->generator);

        $this->handleShowEvolutionValues();
    }

    public function beforeGenericFiltersAreAppliedToLoadedDataTable()
    {
        $this->config->custom_parameters['columns'] = $this->getMetricToGraph($this->config->columns_to_display);
    }

    /**
     * Checks if the data obtained by ViewDataTable has data or not. Since we get the last period
     * when calculating evolution, we need this hook to determine if there's data in the latest
     * table.
     *
     * @return bool
     */
    public function isThereDataToDisplay()
    {
        return $this->getCurrentData($this->dataTable)->getRowsCount() != 0;
    }

    private function getCurrentData($dataTable)
    {
        if ($dataTable instanceof Map) { // will be true if calculating evolution values
            $childTables = $dataTable->getDataTables();
            return end($childTables);
        } else {
            return $dataTable;
        }
    }

    public function getMetricToGraph($columnsToDisplay)
    {
        $firstColumn = reset($columnsToDisplay);
        if ('label' == $firstColumn) {
            $firstColumn = next($columnsToDisplay);
        }
        return $firstColumn;
    }

    private function handleShowEvolutionValues()
    {
        // evolution values cannot be calculated if range period is used
        $period = Common::getRequestVar('period');
        if ($period == 'range') {
            return;
        }

        if ($this->config->show_evolution_values) {
            $date = Common::getRequestVar('date');
            list($previousDate, $ignore) = Range::getLastDate($date, $period);

            $this->requestConfig->request_parameters_to_modify['date'] = $previousDate . ',' . $date;

            $this->generator->showEvolutionValues();
        }
    }
}