<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Integration;

use Piwik\Date;
use Piwik\Http\BadRequestException;
use Piwik\Piwik;
use Piwik\Plugins\TreemapVisualization\API;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group TreemapVisualization
 * @group ApiTest
 * @group Plugins
 */
class ApiTest extends IntegrationTestCase
{
    private $api;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        $this->api = API::getInstance();
    }

    /**
     * @dataProvider validMethod
     */
    public function testGetTreemapDataAllowsValidApiMethod($method): void
    {
        $result = $this->api->getTreemapData(
            $method,
            'nb_visits',
            'day',
            '2025-02-03'
        );

        $this->assertIsArray($result);
    }

    /**
     * @dataProvider inValidMethod
     */
    public function testGetTreemapDataRejectsInvalidApiMethod($method): void
    {
        $this->expectException(BadRequestException::class);
        $this->api->getTreemapData(
            $method,
            'nb_visits',
            'day',
            '2025-02-03'
        );
    }

    /**
     * @dataProvider getSitesAndTheirComparedDays
     */
    public function testGetTreemapDataComparesTheDayTheReportResolvesTodayOn(int $numberOfSites, string $expectedDate): void
    {
        // 04:37 on 2020-12-25 for the sites, still 2020-12-24 in UTC.
        Date::$now = strtotime('2020-12-24 16:37:00');
        $idSites = [];
        for ($i = 0; $i < $numberOfSites; $i++) {
            $idSites[] = Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site', false, 1, null, null, 'UTC+12');
        }
        $originalGet = $_GET;
        // As in an HTTP request: the report request takes these from the request, not from the arguments.
        $_GET = ['idSite' => implode(',', $idSites), 'period' => 'day', 'date' => 'today'];
        $requestedDates = [];
        Piwik::addAction('API.Request.dispatch', function ($finalParameters, $pluginName, $methodName) use (&$requestedDates) {
            if ($pluginName === 'Actions' && $methodName === 'getPageUrls') {
                $requestedDates[] = $finalParameters['date'];
            }
        });

        try {
            $this->api->getTreemapData('Actions.getPageUrls', 'nb_hits', 'day', 'today', false, false, true);
        } finally {
            $_GET = $originalGet;
            Date::$now = null;
        }

        $this->assertSame([$expectedDate], $requestedDates);
    }

    public function getSitesAndTheirComparedDays(): array
    {
        return [
            'one site, on its own day' => [1, '2020-12-24,2020-12-25'],
            // The archive of several sites resolves 'today' in UTC, even when they share a timezone.
            'several sites, on UTC\'s day' => [2, '2020-12-23,2020-12-24'],
        ];
    }

    public function validMethod()
    {
        return [
            ['API.getMatomoVersion'],
            ['API.getPhpVersion'],
        ];
    }

    public function inValidMethod()
    {
        return [
            ['API.getBulkRequest'],
            ['UsersManager.deleteUser'],
        ];
    }
}
