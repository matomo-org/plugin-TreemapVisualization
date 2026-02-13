<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TreemapVisualization\tests\Integration;

use Piwik\Http\BadRequestException;
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
