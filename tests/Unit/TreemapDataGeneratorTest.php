<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Unit;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\TreemapVisualization\TreemapDataGenerator;
use Piwik\Tests\Framework\TestCase\UnitTestCase;

/**
 * @group TreemapVisualization
 * @group TreemapDataGeneratorTest
 * @group Plugins
 */
class TreemapDataGeneratorTest extends UnitTestCase
{
    /**
     * @dataProvider getUrlMetadataTestData
     */
    public function testGenerateOnlyKeepsUrlMetadataThatIsSafeToOpen($url, $expectedUrl): void
    {
        $dataTable = new DataTable();
        $dataTable->addRow(new Row(array(
            Row::COLUMNS  => array('label' => 'row label', 'nb_visits' => 5),
            Row::METADATA => array('url' => $url),
        )));

        $generator = new TreemapDataGenerator('nb_visits', 'visits');
        $result    = $generator->generate($dataTable);

        $metadata = $result['children'][0]['data']['metadata'];

        if ($expectedUrl === null) {
            $this->assertArrayNotHasKey('url', $metadata);
        } else {
            $this->assertSame($expectedUrl, $metadata['url']);
        }
    }

    /**
     * Whether a scheme is safe is decided by `UrlHelper::isLookLikeSafeUrl()`, which core tests
     * separately, so the cases below only need to cover allowed and not allowed schemes.
     */
    public function getUrlMetadataTestData(): array
    {
        return array(
            'allowed scheme is kept'           => array('http://example.com/path', 'http://example.com/path'),
            'allowed secure scheme is kept'    => array('https://example.com/path', 'https://example.com/path'),
            'other allowed scheme is kept'     => array('mailto:someone@example.com', 'mailto:someone@example.com'),
            'url without scheme gets a scheme' => array('example.com/path', 'http://example.com/path'),
            'scheme that is not allowed'       => array('cylon://example.com/path', null),
            'scheme without a host'            => array('rtp:0/path', null),
            'url with a control character'     => array("http://example.com/\x01", null),
            'empty url'                        => array('', null),
            'whitespace only url'              => array('  ', null),
        );
    }
}
