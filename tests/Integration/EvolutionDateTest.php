<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Integration;

use Piwik\Date;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group TreemapVisualization
 * @group EvolutionDateTest
 * @group Plugins
 */
class EvolutionDateTest extends IntegrationTestCase
{
    private array $originalGet;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        $this->originalGet = $_GET;
    }

    public function tearDown(): void
    {
        Date::$now = null;
        $_GET = $this->originalGet;

        parent::tearDown();
    }

    /**
     * @dataProvider getSitesDays
     */
    public function testGetEvolutionDateComparesTheSitesDayWithTheDayBefore(string $now, string $timezone, string $expected): void
    {
        Date::$now = strtotime($now);
        $_GET['idSite'] = (string) $this->createSiteInTimezone($timezone);

        $evolutionDate = Treemap::getEvolutionDate('today', 'day');

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
        $_GET['idSite'] = (string) $this->createSiteInTimezone('UTC+12');

        $this->assertSame('2020-12-21,2020-12-28', Treemap::getEvolutionDate('today', 'week'));
    }

    public function testGetEvolutionDateComparesUtcsDayForSeveralSites(): void
    {
        Date::$now = strtotime('2020-12-24 16:37:00');
        $_GET['idSite'] = $this->createSiteInTimezone('UTC+12') . ',' . $this->createSiteInTimezone('UTC+12');

        // The archive of several sites resolves 'today' in UTC, even when they share a timezone.
        $this->assertSame('2020-12-23,2020-12-24', Treemap::getEvolutionDate('today', 'day'));
    }

    private function createSiteInTimezone(string $timezone): int
    {
        return Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site', false, 1, null, null, $timezone);
    }
}
