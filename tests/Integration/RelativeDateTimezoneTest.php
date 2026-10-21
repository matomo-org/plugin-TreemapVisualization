<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Integration;

use Piwik\DataTable;
use Piwik\DataTable\DataTableInterface;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\ViewDataTable\Factory as ViewDataTableFactory;

/**
 * The archive resolves a relative date on the site's own day when the request is for that one
 * site, so an evolution value has to compare that day with the day before it, not UTC's.
 *
 * @group TreemapVisualization
 * @group Plugins
 */
class RelativeDateTimezoneTest extends IntegrationTestCase
{
    /** 04:37 on 2020-12-25 for the site, still 2020-12-24 in UTC. */
    private const NOW = '2020-12-24 16:37:00';

    /** 17:00 on 2020-12-24 for the site behind UTC, already 2020-12-25 in UTC. */
    private const NOW_BEHIND_UTC = '2020-12-25 03:00:00';

    /** 04:37 on Monday 2020-12-21 for the site, still Sunday in UTC. */
    private const NOW_ON_MONDAY = '2020-12-20 16:37:00';

    private int $idSite;

    private int $idSiteInUtc;

    private int $idSiteBehindUtc;

    /** @var array<string, mixed> */
    private array $originalGet;

    /** @var list<string>|null */
    private ?array $archivedDays = null;

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::$superUser = true;

        $this->idSite = Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site ahead of UTC', false, 1, null, null, 'UTC+12');
        $this->idSiteInUtc = Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site in UTC', false, 1, null, null, 'UTC');
        $this->idSiteBehindUtc = Fixture::createWebsite('2020-12-01 00:00:00', 0, 'Site behind UTC', false, 1, null, null, 'UTC-10');

        Date::$now = (int) strtotime(self::NOW . ' UTC');
        $this->originalGet = $_GET;
    }

    public function tearDown(): void
    {
        $_GET = $this->originalGet;
        Date::$now = null;

        parent::tearDown();
    }

    /**
     * @dataProvider getRequestsAndTheDaysTheyCompare
     *
     * @param list<string> $expectedDays
     */
    public function testEvolutionComparesTheDaysTheArchiveResolvesTheDateOn(string $sites, string $date, array $expectedDays): void
    {
        $idSite = str_replace('site', (string) $this->idSite, $sites);

        self::assertSame($expectedDays, $this->getDaysTheTreemapCompares($idSite, $date));
    }

    public function getRequestsAndTheDaysTheyCompare(): iterable
    {
        // UTC's days are a day before the site's.
        yield 'today' => ['site', 'today', ['2020-12-24', '2020-12-25']];
        yield 'yesterday' => ['site', 'yesterday', ['2020-12-23', '2020-12-24']];
        yield 'yesterday same time' => ['site', 'yesterdaySameTime', ['2020-12-23', '2020-12-24']];
        // Core matches a relative range end in any case and padded.
        yield 'other spelling' => ['site', ' Today ', ['2020-12-24', '2020-12-25']];
        yield 'list of the site only' => ['site,site', 'today', ['2020-12-24', '2020-12-25']];
        yield 'absolute date' => ['site', '2020-12-20', ['2020-12-19', '2020-12-20']];
    }

    public function testEvolutionComparesUtcsDaysForSeveralSites(): void
    {
        // Only the API serves several sites, and it returns no data with evolution values, so
        // check the date it requests.
        $_GET = ['idSite' => $this->idSite . ',' . $this->idSiteInUtc];

        self::assertSame('2020-12-23,2020-12-24', Treemap::getEvolutionDateOfRequestedSite('today', 'day'));
    }

    public function testEvolutionComparesTheDaysOfASiteBehindUtc(): void
    {
        Date::$now = (int) strtotime(self::NOW_BEHIND_UTC . ' UTC');

        $days = $this->getDaysTheTreemapCompares((string) $this->idSiteBehindUtc, 'today');

        self::assertSame(['2020-12-23', '2020-12-24'], $days);
    }

    public function testEvolutionComparesTheWeeksOfTheSitesDay(): void
    {
        Date::$now = (int) strtotime(self::NOW_ON_MONDAY . ' UTC');

        $days = $this->getDaysTheTreemapCompares((string) $this->idSite, 'today', 'week');

        self::assertSame(['2020-12-14', '2020-12-21'], $days);
    }

    public function testEvolutionComparesTheSitesDaysForAllSitesOfASingleSiteInstallation(): void
    {
        // The archive applies the single site rule to the sites idSite=all resolves to. Only the
        // API serves idSite=all, so check the date it requests.
        SitesManagerAPI::getInstance()->deleteSite($this->idSiteInUtc);
        SitesManagerAPI::getInstance()->deleteSite($this->idSiteBehindUtc);

        $_GET = ['idSite' => 'all'];

        self::assertSame('2020-12-24,2020-12-25', Treemap::getEvolutionDateOfRequestedSite('today', 'day'));
    }

    /**
     * Renders the treemap with evolution values and returns the first days of the periods it compares.
     *
     * @return list<string>
     */
    private function getDaysTheTreemapCompares(string $idSite, string $date, string $period = 'day'): array
    {
        $_GET = ['idSite' => $idSite, 'period' => $period, 'date' => $date, 'segment' => ''];

        Piwik::addAction('API.Referrers.getReferrerType.end', function ($returnedValue) {
            $this->archivedDays = $this->getDaysOf($returnedValue);
            throw new \RuntimeException('Treemap API response captured.');
        });

        try {
            ViewDataTableFactory::build(Treemap::ID, 'Referrers.getReferrerType', 'Referrers.getReferrerType', true, false)->render();
        } catch (\Exception $e) {
            // The API layer re-wraps the abort, so only a failure before the response is rethrown.
            if ($this->archivedDays === null) {
                throw $e;
            }
        }

        self::assertNotNull($this->archivedDays, 'The treemap made no API request.');

        return $this->archivedDays;
    }

    /**
     * @return list<string>
     */
    private function getDaysOf(DataTableInterface $table): array
    {
        if ($table instanceof DataTable\Map) {
            $days = [];
            foreach ($table->getDataTables() as $childTable) {
                $days = array_merge($days, $this->getDaysOf($childTable));
            }

            $days = array_values(array_unique($days));
            sort($days);

            return $days;
        }

        return [$table->getMetadata('period')->getDateStart()->toString()];
    }
}
