/*!
 * Matomo - free/libre analytics platform
 *
 * Screenshot test for TasksTimetable main page.
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("Treemap", function () {
    this.timeout(0);

    var generalParams = 'idSite=1&period=year&date=2012-08-09',
        urlBase = 'module=CoreHome&action=index&' + generalParams,
        normalUrl = "?module=Widgetize&action=iframe&moduleToWidgetize=DevicesDetection&idSite=1&period=year&date=2012-08-09&"
                  + "actionToWidgetize=getBrowsers&viewDataTable=table&filter_limit=5&isFooterExpandedInDashboard=1",
        actionsUrl = "?" + urlBase + "#?" + generalParams + "&category=General_Actions&subcategory=General_Pages"
        ;

    it('should load a normal report w/ the treemap visualization correctly', async function () {
        await page.goto(normalUrl);
        await page.evaluate(function () {
            $('.tableIcon[data-footer-icon-id=infoviz-treemap]').click();
        });
        await page.waitForNetworkIdle();
        await page.waitForTimeout(1000);
        expect(await page.screenshotSelector('.widget')).to.matchImage('normal_treemap');
    });

    it('should load a report directly as treemap visualization correctly', async function () {
        await page.goto(normalUrl + "&viewDataTable=infoviz-treemap");
        await page.waitForTimeout(1000);
        expect(await page.screenshotSelector('.widget')).to.matchImage('initial_treemap');
    });

    it('should load an actions report on the actions page w/ the treemap visualization correctly', async function (){
        await page.goto(actionsUrl);
        await page.evaluate(function () {
            $('.tableIcon[data-footer-icon-id=infoviz-treemap]').click();
        });
        await page.waitForNetworkIdle();
        await page.waitForTimeout(1000);
        expect(await page.screenshotSelector('.pageWrap')).to.matchImage('actions_treemap');
    });
});