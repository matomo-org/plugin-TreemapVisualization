<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Integration;

use Piwik\Access;
use Piwik\Date;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\ViewDataTable\Factory as ViewDataTableFactory;

/**
 * @group TreemapVisualization
 * @group EvolutionDateTest
 * @group Plugins
 */
class EvolutionDateTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
    }

    public function tearDown(): void
    {
        Date::$now = null;

        parent::tearDown();
    }

    /**
     * @dataProvider getSitesDays
     */
    public function testGetEvolutionDateComparesTheSitesDayWithTheDayBefore(string $now, string $timezone, string $expected): void
    {
        Date::$now = strtotime($now);
        $idSite = (string) $this->createSiteInTimezone($timezone);

        $evolutionDate = Treemap::getEvolutionDate('today', 'day', $idSite);

        $this->assertSame($expected, $evolutionDate);
        // The archive builds both days, so the treemap has a previous period to compare against.
        $this->assertSame(2, PeriodFactory::build('day', $evolutionDate, $timezone)->getNumberOfSubperiods());
    }

    public function getSitesDays(): array
    {
        return [
            // 04:37 on 2020-12-25 for the site, still 2020-12-24 in UTC.
            ['2020-12-24 16:37:00', 'UTC+12', '2020-12-24,2020-12-25'],
            ['2020-12-24 16:37:00', 'UTC', '2020-12-23,2020-12-24'],
            // 07:00 on 2020-12-24 for the site, the same day as UTC's.
            ['2020-12-24 12:00:00', 'UTC-5', '2020-12-23,2020-12-24'],
            // 21:00 on 2020-12-23 for the site, a day behind UTC.
            ['2020-12-24 02:00:00', 'UTC-5', '2020-12-22,2020-12-23'],
        ];
    }

    public function testGetEvolutionDateComparesTheSitesWeekWithTheWeekBefore(): void
    {
        // 04:37 on Monday 2020-12-28 for the site, still Sunday 2020-12-27 in UTC.
        Date::$now = strtotime('2020-12-27 16:37:00');
        $idSite = (string) $this->createSiteInTimezone('UTC+12');

        $this->assertSame('2020-12-21,2020-12-28', Treemap::getEvolutionDate('today', 'week', $idSite));
    }

    /**
     * @dataProvider getRelativePeriods
     */
    public function testGetEvolutionDateResolvesLastWeekMonthAndYearOnTheSitesDay(string $date, string $period, string $expected): void
    {
        // 04:37 on Monday 2020-12-28 for the site, still Sunday 2020-12-27 in UTC.
        Date::$now = strtotime('2020-12-27 16:37:00');
        $idSite = (string) $this->createSiteInTimezone('UTC+12');

        $this->assertSame($expected, Treemap::getEvolutionDate($date, $period, $idSite));
    }

    public function getRelativePeriods(): array
    {
        return [
            ['lastWeek', 'day', '2020-12-20,2020-12-21'],
            ['last-week', 'week', '2020-12-14,2020-12-21'],
            ['lastMonth', 'month', '2020-10-01,2020-11-01'],
            ['lastYear', 'year', '2018-01-01,2019-01-01'],
        ];
    }

    /**
     * @dataProvider getDatesOfOtherPeriods
     */
    public function testGetEvolutionDateComparesThePeriodContainingTheDateWithTheOneBefore(string $date, string $period, string $expected): void
    {
        // 04:37 on 2020-12-25 for the site, still 2020-12-24 in UTC.
        Date::$now = strtotime('2020-12-24 16:37:00');
        $idSite = (string) $this->createSiteInTimezone('UTC+12');

        $this->assertSame($expected, Treemap::getEvolutionDate($date, $period, $idSite));
    }

    public function getDatesOfOtherPeriods(): array
    {
        return [
            ['yesterday', 'day', '2020-12-23,2020-12-24'],
            // A date inside a period names the start of that period.
            ['2020-12-24', 'week', '2020-12-14,2020-12-21'],
            ['2020-03-31', 'month', '2020-02-01,2020-03-01'],
            ['2020-08-09', 'year', '2019-01-01,2020-01-01'],
        ];
    }

    public function testGetEvolutionDateComparesUtcsDayForASiteWithoutViewAccess(): void
    {
        Date::$now = strtotime('2020-12-24 16:37:00');
        $idSite = (string) $this->createSiteInTimezone('UTC+12');
        Access::getInstance()->setSuperUserAccess(false);

        // The site's timezone stays hidden, and the report request then fails its own access check.
        $this->assertSame('2020-12-23,2020-12-24', Treemap::getEvolutionDate('today', 'day', $idSite));
    }

    public function testGetEvolutionDateReadsASiteListTheWayTheArchiveDoes(): void
    {
        Date::$now = strtotime('2020-12-24 16:37:00');
        $idSite = [(string) $this->createSiteInTimezone('UTC+12')];

        $this->assertSame('2020-12-24,2020-12-25', Treemap::getEvolutionDate('today', 'day', $idSite));
    }

    public function testGetEvolutionDateComparesUtcsDayForSeveralSites(): void
    {
        Date::$now = strtotime('2020-12-24 16:37:00');
        $idSite = $this->createSiteInTimezone('UTC+12') . ',' . $this->createSiteInTimezone('UTC+12');

        // The archive of several sites resolves 'today' in UTC, even when they share a timezone.
        $this->assertSame('2020-12-23,2020-12-24', Treemap::getEvolutionDate('today', 'day', $idSite));
    }

    public function testTreemapViewComparesTheDayOfTheSiteItsReportRequests(): void
    {
        Fixture::loadAllTranslations();
        Date::$now = strtotime('2020-12-24 16:37:00');
        $originalGet = $_GET;
        $_GET = ['idSite' => (string) $this->createSiteInTimezone('UTC'), 'period' => 'day', 'date' => 'today'];
        $requestedDates = [];
        Piwik::addAction('API.Request.dispatch', function ($finalParameters, $pluginName, $methodName) use (&$requestedDates) {
            if ($pluginName === 'Actions' && $methodName === 'getPageUrls') {
                $requestedDates[] = $finalParameters['date'];
            }
        });

        try {
            $view = ViewDataTableFactory::build(Treemap::ID, 'Actions.getPageUrls', 'Actions.getPageUrls', true, false);
            // A report that requests another site than the URL names, as a plugin can configure it.
            $view->requestConfig->request_parameters_to_modify['idSite'] = (string) $this->createSiteInTimezone('UTC+12');
            $view->render();
        } finally {
            $_GET = $originalGet;
            Fixture::resetTranslations();
        }

        $this->assertSame(['2020-12-24,2020-12-25'], $requestedDates);
    }

    private function createSiteInTimezone(string $timezone): int
    {
        return Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site', false, 1, null, null, $timezone);
    }
}
