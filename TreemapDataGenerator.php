<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\TreemapVisualization;

use Piwik\Archive\DataTableFactory;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Filter\CalculateEvolutionFilter;
use Piwik\DataTable\Map;
use Piwik\Piwik;

/**
 * A utility class that generates JSON data meant to be used with the JavaScript
 * Infovis Toolkit's treemap visualization.
 */
class TreemapDataGenerator
{
    const DEFAULT_MAX_ELEMENTS = 10;
    const MIN_NODE_AREA = 400; // 20px * 20px

    /**
     * The list of row metadata that should appear in treemap JSON data, if in the row.
     *
     * @var array
     */
    private static $rowMetadataToCopy = array('logo', 'url');

    /**
     * The name of the root node.
     *
     * @var string
     */
    private $rootName = '';

    /**
     * The offset of the first row in the DataTable. When exploring aggregate rows (ie, the
     * 'Others' row), the DataTable used won't have the initial rows, so the row offsets
     * aren't the same as the row IDs. In order to make sure each node has a unique ID,
     * we need to to know the actual row offset of each row.
     *
     * @var int
     */
    private $firstRowOffset = 0;

    /**
     * The name of the metric to generate a treemap for.
     *
     * @var string
     */
    private $metricToGraph;

    /**
     * The internationalized label of the metric to graph. Used in the tooltip of each node.
     *
     * @var string
     */
    private $metricTranslation;

    /**
     * The available screen width for the treemap visualization.
     *
     * @var int
     */
    private $availableWidth;

    /**
     * The available screen height for the treemap visualization.
     *
     * @var int
     */
    private $availableHeight;

    /**
     * Whether to include evolution values in the output JSON.
     *
     * @var bool
     */
    private $showEvolutionValues = false;

    /**
     * Holds the date of the past period. Implementation detail.
     *
     * @var string
     */
    private $pastDataDate = null;

    /**
     * Callback used to format row labels before they are used in treemap nodes.
     *
     * @var callback
     */
    private $labelFormatter = null;

    /**
     * Constructor.
     *
     * @param string $metricToGraph @see self::$metricToGraph
     * @param string $metricTranslation
     */
    public function __construct($metricToGraph, $metricTranslation)
    {
        $this->metricToGraph     = $metricToGraph;
        $this->metricTranslation = $metricTranslation;
    }

    /**
     * Sets the name of the root node.
     *
     * @param string $name
     */
    public function setRootNodeName($name)
    {
        $this->rootName = $name;
    }

    /**
     * Sets the offset of the first row in the converted DataTable.
     *
     * @param int $offset
     */
    public function setInitialRowOffset($offset)
    {
        $this->firstRowOffset = (int)$offset;
    }

    /**
     * Configures the generator to calculate the evolution of column values and include
     * this data in the outputted tree structure.
     */
    public function showEvolutionValues()
    {
        $this->showEvolutionValues = true;
    }

    /**
     * Sets the callback used to format row labels before they are used in treemap nodes.
     *
     * @param callback $formatter
     */
    public function setLabelFormatter($formatter)
    {
        $this->labelFormatter = $formatter;
    }

    /**
     * Sets the available screen dimensions for this visualization.
     *
     * @param int $availableWidth  The available screen width for the display.
     * @param int $availableHeight The available screen height for the display.
     */
    public function setAvailableDimensions($availableWidth, $availableHeight)
    {
        $this->availableWidth  = $availableWidth;
        $this->availableHeight = $availableHeight;
    }

    /**
     * Generates an array that can be encoded as JSON and used w/ the JavaScript Infovis Toolkit.
     *
     * @param \Piwik\DataTable $dataTable
     * @return array
     */
    public function generate($dataTable)
    {
        // sanity check: if the dataTable is not a Map, we don't have the data to calculate evolution
        // values, so make sure we don't try
        if (!($dataTable instanceof Map)) {
            $this->showEvolutionValues = false;
        }

        // if showEvolutionValues is true, $dataTable must be a DataTable\Map w/ two child tables
        $pastData = false;
        if ($this->showEvolutionValues) {
            $pastData  = $dataTable->getFirstRow();
            $dataTable = $dataTable->getLastRow();

            $this->pastDataDate = $pastData->getMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX)->getLocalizedShortString();
        }

        $root = $this->makeNode('treemap-root', $this->rootName);

        $tableId = Common::getRequestVar('idSubtable', '');
        $this->addDataTableToNode($root, $dataTable, $pastData, $tableId, $this->firstRowOffset);
        return $root;
    }

    /**
     * Computes the maximum number of elements allowed in the report to display, based on the
     * available screen width/height, and truncates the report data so the number of rows
     * will not exceed the max.
     *
     * @param DataTable $dataTable       The report data. Must be sorted by the metric to graph.
     * @param int       $availableWidth  Available width in pixels.
     * @param int       $availableHeight Available height in pixels.
     */
    public function truncateBasedOnAvailableSpace($dataTable)
    {
        $truncateAfter = self::DEFAULT_MAX_ELEMENTS - 1;
        if (is_numeric($this->availableWidth)
            && is_numeric($this->availableHeight)
        ) {
            $totalArea = $this->availableWidth * $this->availableHeight;

            $dataTable->filter('ReplaceColumnNames');

            $metricValues = $dataTable->getColumn($this->metricToGraph);
            $metricSum    = array_sum($metricValues);

            if ($metricSum != 0) {
                // find the row index in $dataTable for which all rows after it will have treemap
                // nodes that are too small. this is the row from which we truncate.
                // Note: $dataTable is sorted at this point, so $metricValues is too
                $result = 0;
                foreach ($metricValues as $value) {
                    $nodeArea = ($totalArea * $value) / $metricSum;

                    if ($nodeArea < self::MIN_NODE_AREA) {
                        break;
                    } else {
                        ++$result;
                    }
                }
                $truncateAfter = $result;
            }
        }

        $dataTable->filter('Truncate', array($truncateAfter));
    }

    private function addDataTableToNode(&$node, $dataTable, $pastData = false, $tableId = '', $offset = 0)
    {
        foreach ($dataTable->getRows() as $rowId => $row) {
            $pastRow = $pastData ? $pastData->getRowFromLabel($row->getColumn('label')) : false;

            $childNode = $this->makeNodeFromRow($tableId, $rowId, $row, $pastRow);
            if (empty($childNode)) {
                continue;
            }

            if ($rowId == DataTable::ID_SUMMARY_ROW) {
                $childNode['data']['aggregate_offset'] = $offset + $dataTable->getRowsCount() - 1;
            } else if ($row->getIdSubDataTable() !== null) {
                $childNode['data']['idSubtable'] = $row->getIdSubDataTable();
            }

            $node['children'][] = $childNode;
        }
    }

    private function makeNodeFromRow($tableId, $rowId, $row, $pastRow)
    {
        $label = $row->getColumn('label');
        if ($this->labelFormatter) {
            $formatter = $this->labelFormatter;
            $label     = $formatter($row, $label);
        }

        $columnValue = $row->getColumn($this->metricToGraph) ?: 0;

        if ($columnValue == 0) { // avoid issues in JIT w/ 0 $area values
            return false;
        }

        $data          = array();
        $data['$area'] = $columnValue;

        // add metadata
        if ($rowId !== DataTable::ID_SUMMARY_ROW) {
            foreach (self::$rowMetadataToCopy as $metadataName) {
                $metadataValue = $row->getMetadata($metadataName);
                if ($metadataValue !== false) {
                    $data['metadata'][$metadataName] = $metadataValue;
                }
            }
        }

        // add evolution
        if ($rowId !== DataTable::ID_SUMMARY_ROW
            && $this->showEvolutionValues
        ) {
            if ($pastRow === false) {
                $data['evolution'] = 100;
            } else {
                $pastValue         = $pastRow->getColumn($this->metricToGraph) ?: 0;
                $data['evolution'] = CalculateEvolutionFilter::calculate(
                    $columnValue, $pastValue, $quotientPrecision = 0, $appendPercentSign = false);
            }
        }

        // add node tooltip
        $data['metadata']['tooltip'] = "\n" . $columnValue . ' ' . $this->metricTranslation;
        if (isset($data['evolution'])) {
            $plusOrMinus     = $data['evolution'] >= 0 ? '+' : '-';
            $evolutionChange = $plusOrMinus . abs($data['evolution']) . '%';

            $data['metadata']['tooltip'] = Piwik::translate('General_XComparedToY', array(
                $data['metadata']['tooltip'] . "\n" . $evolutionChange,
                $this->pastDataDate
            ));
        }

        return $this->makeNode($this->getNodeId($tableId, $rowId), $label, $data);
    }

    private function getNodeId($tableId, $rowId)
    {
        if ($rowId == DataTable::ID_SUMMARY_ROW) {
            $rowId = $this->firstRowOffset . '_' . $rowId;
        } else {
            $rowId = $this->firstRowOffset += $rowId;
        }

        return $tableId . '_' . $rowId;
    }

    private function makeNode($id, $title, $data = array())
    {
        return array('id' => $id, 'name' => $title, 'data' => $data, 'children' => array());
    }
}
