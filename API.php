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
use Piwik\DataTable;
use Piwik\Http\BadRequestException;
use Piwik\Metrics;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;

/**
 * Exposes report data formatted for the Treemap visualization.
 *
 * @method static \Piwik\Plugins\TreemapVisualization\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Returns report data formatted for the Treemap visualization.
     *
     * @param string $apiMethod The API module and action to call. Must reference a supported `get*`
     *                          action, for example `Actions.getPageUrls`.
     * @param string $column The metric column to generate treemap data for. If multiple columns are
     *                       provided, only the first one is used.
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data for
     *                                                    the period containing the specified date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX, previousX).
     * @param int|false $availableWidth Available screen width in pixels used to truncate the
     *                                  treemap to the visible area.
     * @param int|false $availableHeight Available screen height in pixels used to truncate the
     *                                   treemap to the visible area.
     * @param bool|int $show_evolution_values Whether to include evolution values for each row.
     *                                        This is ignored for range periods and subtable
     *                                        requests.
     *
     * @return array<string, mixed> The treemap root node data, or an empty array when the
     *                              request does not produce a DataTable.
     */
    public function getTreemapData(
        $apiMethod,
        $column,
        $period,
        $date,
        $availableWidth = false,
        $availableHeight = false,
        $show_evolution_values = false
    ) {
        if (!Request::isCurrentApiRequestTheRootApiRequest() && !defined('PIWIK_TEST_MODE')) {
            return [];
        }
        list($module, $method) = explode('.', $apiMethod);
        $disAllowedApiActions = ['getBulkRequest'];
        // Block if API action does not start with get
        if (!$method || in_array($method, $disAllowedApiActions) || stripos($method, 'get') !== 0) {
            throw new BadRequestException(Piwik::translate('TreemapVisualization_InvalidApiMethodException'));
        }

        if (
            $period == 'range'
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

        if (!$dataTable instanceof DataTable) {
            return [];
        }

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
