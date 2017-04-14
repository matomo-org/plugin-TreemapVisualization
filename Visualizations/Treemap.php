<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\TreemapVisualization\Visualizations;

use Piwik\Common;
use Piwik\DataTable\Map;
use Piwik\Period\Range;
use Piwik\Plugins\CoreVisualizations\Visualizations\Graph;
use Piwik\Plugins\TreemapVisualization\TreemapConfig;
use Piwik\Plugins\TreemapVisualization\TreemapDataGenerator;

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
     * The list of Actions reports whose labels are parts of URLs.
     */
    private static $actionsUrlReports = array(
        'getPageUrls',
        'getEntryPageUrls',
        'getExitPageUrls'
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
        $this->config->datatable_js_type  = 'TreemapDataTable';
        $this->config->show_flatten_table = false;
        $this->config->show_pagination_control = false;
        $this->config->show_offset_information = false;

        if ('ExampleUI' == $this->requestConfig->getApiModuleToRequest()) {
            $this->config->show_evolution_values = false;
        }

        if ('Actions' === $this->requestConfig->getApiModuleToRequest()) {
            $this->configureForActionsReports();
        }

        $this->assignTemplateVar('generator', $this->generator);
    }

    public function configureForActionsReports()
    {
        $this->config->show_all_views_icons = true;
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

        self::configureGeneratorIfActionsUrlReport($this->generator, $method);
    }

    public static function configureGeneratorIfActionsUrlReport($generator, $method)
    {
        if (in_array($method, self::$actionsUrlReports)) {
            $generator->setLabelFormatter(function ($row, $label) {
                if ($row->getIdSubDataTable() !== null) {
                    return $label . '/';
                } else {
                    return $label;
                }
            });
        }
    }

    public function beforeLoadDataTable()
    {
        $this->config->max_graph_elements = false;

        parent::beforeLoadDataTable();

        $metric      = $this->getMetricToGraph($this->config->columns_to_display);
        $translation = empty($this->config->translations[$metric]) ? $metric : $this->config->translations[$metric];

        $this->generator = new TreemapDataGenerator($metric, $translation);

        $filterOffset    = $this->requestConfig->filter_offset ? : 0;
        $this->generator->setInitialRowOffset($filterOffset);

        $this->handleShowEvolutionValues();

        $availableWidth = false;
        if (!empty($this->config->custom_parameters['availableWidth'])) {
            $availableWidth = (int) $this->config->custom_parameters['availableWidth'];
        }

        $availableHeight = false;
        if (!empty($this->config->custom_parameters['availableHeight'])) {
            $availableHeight = (int) $this->config->custom_parameters['availableHeight'];
        }

        $availableWidth  = Common::getRequestVar('availableWidth', $availableWidth);
        $availableHeight = Common::getRequestVar('availableHeight', $availableHeight);
        $this->generator->setAvailableDimensions($availableWidth, $availableHeight);

        $this->assignTemplateVar('availableWidth', $availableWidth);
        $this->assignTemplateVar('availableHeight', $availableHeight);
    }

    public function afterGenericFiltersAreAppliedToLoadedDataTable()
    {
        $this->generator->truncateBasedOnAvailableSpace($this->getCurrentData($this->dataTable));
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
        // evolution values cannot be calculated if range period is used or subtable is being loaded
        $period = Common::getRequestVar('period');
        if ($period == 'range'
            || $this->requestConfig->idSubtable
        ) {
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
