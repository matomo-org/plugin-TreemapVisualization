<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\TreemapVisualization;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Metrics;
use Piwik\Period\Range;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;

/**
 * Class API
 * @method static \Piwik\Plugins\TreemapVisualization\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Gets report data and converts it into data that can be used with the JavaScript Infovis
     * Toolkit's treemap visualization.
     *
     * @param string   $apiMethod               The API module & action to call. The result of this method is converted
     *                                          to data usable by the treemap visualization. E.g.
     *                                          'Actions.getPageUrls'.
     * @param string   $column                  The column to generate metric data for. If more than one column is
     *                                          supplied, the first is used and the rest discarded.
     * @param string   $period
     * @param string   $date
     * @param bool     $availableWidth          Available screen width in pixels.
     * @param bool     $availableHeight         Available screen height in pixels.
     * @param int|bool $show_evolution_values   Whether to calculate evolution values for each row or not.
     *
     * @return array
     */
    public function getTreemapData($apiMethod, $column, $period, $date, $availableWidth = false, $availableHeight = false,
                                   $show_evolution_values = false)
    {
        if ($period == 'range'
            || Common::getRequestVar('idSubtable', false)
        ) {
            $show_evolution_values = false;
        }

        $params = array();
        if ($show_evolution_values) {
            list($previousDate, $ignore) = Range::getLastDate($date, $period);
            $params['date'] = $previousDate . ',' . $date;
        }

        $params['filter_limit']           = false;
        $params['disable_queued_filters'] = true;

        $dataTable = Request::processRequest("$apiMethod", $params);

        $columns = explode(',', $column);
        $column  = reset($columns);

        $translations = Metrics::getDefaultMetricTranslations();
        $translation  = empty($translations[$column]) ? $column : $translations[$column];

        $generator = new TreemapDataGenerator($column, $translation);
        $generator->setInitialRowOffset(Common::getRequestVar('filter_offset', 0, 'int'));
        $generator->setAvailableDimensions($availableWidth, $availableHeight);
        if ($show_evolution_values) {
            $generator->showEvolutionValues();
        }

        $generator->truncateBasedOnAvailableSpace($dataTable);
        $dataTable->applyQueuedFilters();

        list($module, $method) = explode('.', $apiMethod);
        if ($module == 'Actions') {
            Treemap::configureGeneratorIfActionsUrlReport($generator, $method);
        }

        return $generator->generate($dataTable);
    }
}
